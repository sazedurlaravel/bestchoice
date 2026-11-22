<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderReviewService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public const SHIPPING_CHARGE = 60;

    public const DEFAULT_OUTSIDE_DHAKA_CHARGE = 120;

    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array{items: array<int|string, array{product_id:int,name:string,sku:string,price:float,buying_price:?float,image:string,qty:int,variant_id?:int,variant_name?:string}>, subtotal:float, shippingCharge:int, total:float, pricesChanged:bool}
     */
    public function quote(array $items, bool $lockProducts = false, ?string $couponCode = null, string $deliveryZone = 'inside_dhaka'): array
    {
        $validated = Validator::make(['items' => array_values($items)], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $query = Product::query()->withCount('variants')
            ->whereIn('id', array_column($validated['items'], 'product_id'))
            ->orderBy('id');

        if ($lockProducts) {
            $query->lockForUpdate();
        }

        $products = $query->get()->keyBy('id');
        $currentItems = [];
        $subtotalInCents = 0;
        $pricesChanged = false;

        foreach ($validated['items'] as $index => $item) {
            $product = $products->get($item['product_id']);

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages(['cart' => 'কার্টের একটি পণ্য এখন আর পাওয়া যাচ্ছে না।']);
            }

            $variant = null;
            if (isset($item['variant_id'])) {
                $variantQuery = ProductVariant::query()->where('product_id', $product->id)->whereKey($item['variant_id']);
                if ($lockProducts) {
                    $variantQuery->lockForUpdate();
                }
                $variant = $variantQuery->first();
                if (! $variant || ! $variant->is_active || ! $variant->combination_key || (int) $product->stock_quantity !== 0) {
                    throw ValidationException::withMessages([
                        'cart' => 'নির্বাচিত সাইজ বা রঙ এখন কেনার জন্য উপলব্ধ নয়।',
                        'items.'.$index.'.variant_id' => 'নির্বাচিত সাইজ বা রঙ এখন কেনার জন্য উপলব্ধ নয়।',
                    ]);
                }
                $variant->setRelation('product', $product);
            } elseif ($product->variants_count > 0) {
                throw ValidationException::withMessages(['cart' => 'পণ্যের সাইজ বা রঙ নির্বাচন করুন।']);
            }

            $quantity = (int) $item['qty'];
            if ($quantity > (int) ($variant?->stock_quantity ?? $product->stock_quantity)) {
                throw ValidationException::withMessages(['cart' => 'একটি পণ্যের পর্যাপ্ত স্টক নেই। কার্টের পরিমাণ পরিবর্তন করুন।']);
            }

            $price = $variant ? $variant->effectiveSellingPrice() : (float) $product->selling_price;
            $regularPrice = $variant ? $variant->effectiveRegularPrice() : ($product->regular_price === null ? null : (float) $product->regular_price);
            if ($price < 0 || ($regularPrice !== null && $regularPrice < $price)) {
                throw ValidationException::withMessages(['cart' => 'একটি পণ্যের মূল্য সঠিক নয়। অনুগ্রহ করে পরে চেষ্টা করুন।']);
            }

            $priceInCents = (int) round($price * 100);
            $subtotalInCents += $priceInCents * $quantity;
            $pricesChanged = $pricesChanged || $priceInCents !== (int) round((float) $item['price'] * 100);
            $key = CartService::lineKey($product->id, $variant?->id);
            if (isset($currentItems[$key])) {
                throw ValidationException::withMessages(['cart' => 'কার্টে একই পণ্যের একাধিক লাইন গ্রহণযোগ্য নয়।']);
            }
            $currentItems[$key] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'price' => $priceInCents / 100,
                'buying_price' => $product->buying_price === null ? null : (float) $product->buying_price,
                'image' => $product->display_image,
                'qty' => $quantity,
            ];
            if ($variant) {
                $currentItems[$key]['variant_id'] = $variant->id;
                $currentItems[$key]['variant_name'] = $variant->name;
            }
        }

        $shippingCharge = $this->shippingCharge($deliveryZone);
        if ($subtotalInCents + $shippingCharge * 100 > 9999999999) {
            throw ValidationException::withMessages(['cart' => 'অর্ডারের মোট মূল্য গ্রহণযোগ্য সীমার বেশি।']);
        }

        $discountInCents = 0;
        $coupon = null;
        if ($couponCode !== null && $couponCode !== '') {
            $coupon = Coupon::query()->available()->where('code', strtoupper(trim($couponCode)))->first();
            if (! $coupon || $subtotalInCents < (int) round((float) $coupon->minimum_subtotal * 100)) {
                throw ValidationException::withMessages(['coupon_code' => 'কুপনটি অবৈধ, মেয়াদোত্তীর্ণ, অথবা ন্যূনতম ক্রয়মূল্য পূরণ হয়নি।']);
            }
            $discountInCents = $coupon->type === 'percent'
                ? (int) round($subtotalInCents * min((float) $coupon->value, 100) / 100)
                : min($subtotalInCents, (int) round((float) $coupon->value * 100));
        }

        return [
            'items' => $currentItems,
            'subtotal' => $subtotalInCents / 100,
            'shippingCharge' => $shippingCharge,
            'discountAmount' => $discountInCents / 100,
            'couponCode' => $coupon?->code,
            'total' => ($subtotalInCents + $shippingCharge * 100 - $discountInCents) / 100,
            'pricesChanged' => $pricesChanged,
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     */
    public function fingerprint(array $items, ?int $userId, ?string $couponCode = null, string $deliveryZone = 'inside_dhaka'): string
    {
        ksort($items);
        $lines = array_map(fn (array $item): array => [
            $item['product_id'] ?? null,
            $item['qty'] ?? null,
            $item['price'] ?? null,
            $item['variant_id'] ?? null,
        ], array_values($items));

        return hash('sha256', json_encode([$userId, $lines, $couponCode], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array{customer_name:string,customer_phone:string,customer_address:string,payment_method:string,customer_additional_phone?:?string,note?:?string}  $customer
     * @param  array<int|string, array<string, mixed>>  $items
     */
    public function place(array $customer, array $items, string $checkoutToken, ?int $userId, ?string $couponCode = null, string $deliveryZone = 'inside_dhaka'): Order
    {
        if ($existing = Order::query()->where('checkout_token', $checkoutToken)->first()) {
            return $existing;
        }

        $order = null;
        try {
            $order = DB::transaction(function () use ($customer, $items, $checkoutToken, $userId, $couponCode, $deliveryZone): Order {
                /** The unique insert reserves the attempt before any stock is deducted. */
                $order = Order::create([
                    ...$customer,
                    'checkout_token' => $checkoutToken,
                    'user_id' => $userId,
                    'order_number' => Order::generateOrderNumber(),
                    'subtotal' => 0,
                    'shipping_charge' => $this->shippingCharge($deliveryZone),
                    'total' => 0,
                    'discount_amount' => 0,
                    'status' => 'pending',
                ]);

                $quote = $this->quote($items, lockProducts: true, couponCode: $couponCode, deliveryZone: $deliveryZone);

                if ($quote['pricesChanged']) {
                    throw ValidationException::withMessages(['cart' => 'পণ্যের দাম পরিবর্তিত হয়েছে। নতুন মূল্য দেখে আবার অর্ডার নিশ্চিত করুন।']);
                }

                $order->update([
                    'subtotal' => $quote['subtotal'],
                    'shipping_charge' => $quote['shippingCharge'],
                    'total' => $quote['total'],
                    'discount_amount' => $quote['discountAmount'],
                    'coupon_code' => $quote['couponCode'],
                ]);

                if ($quote['couponCode']) {
                    $coupon = Coupon::query()->available()->where('code', $quote['couponCode'])->lockForUpdate()->firstOrFail();
                    $coupon->increment('usage_count');
                }

                foreach ($quote['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $orderItem = $order->items()->create([
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'product_sku' => $item['sku'],
                        'quantity' => $item['qty'],
                        'variant_id' => $item['variant_id'] ?? null,
                        'variant_name' => $item['variant_name'] ?? null,
                        'stock_deducted_quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'buying_price' => $item['buying_price'],
                        'subtotal' => ((int) round($item['price'] * 100) * $item['qty']) / 100,
                    ]);
                    $variant = isset($item['variant_id']) ? ProductVariant::findOrFail($item['variant_id']) : null;
                    $this->inventory->stockOut($product, $item['qty'], 'অর্ডার '.$order->order_number,
                        $userId, $variant, 'order-item:'.$orderItem->id.':deduct', 'order_item', $orderItem->id);
                }

                return $order;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            /** A competing request may have committed this exact attempt while our insert waited. */
            $order = Order::query()->where('checkout_token', $checkoutToken)->first() ?? throw $exception;
        }

        // Run COD review outside the inventory transaction so read queries see committed stock.
        app(OrderReviewService::class)->applyToOrder($order);

        return $order;
    }

    public function shippingCharge(string $deliveryZone): int
    {
        $settings = StoreSetting::query()
            ->whereIn('key', ['delivery_charge', 'delivery_charge_inside_dhaka', 'delivery_charge_outside_dhaka'])
            ->pluck('value', 'key');

        if ($deliveryZone === 'outside_dhaka') {
            return (int) round((float) ($settings['delivery_charge_outside_dhaka'] ?? self::DEFAULT_OUTSIDE_DHAKA_CHARGE));
        }

        return (int) round((float) ($settings['delivery_charge_inside_dhaka'] ?? $settings['delivery_charge'] ?? self::SHIPPING_CHARGE));
    }
}
