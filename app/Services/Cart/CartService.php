<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Session\Store as Session;

class CartService
{
    private const SESSION_KEY = 'cart';

    public function __construct(private Session $session) {}

    /** @return array<int|string, array{product_id:int,name:string,sku:string,price:float,image:string,qty:int,variant_id?:int,variant_name?:string}> */
    public function items(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    public static function lineKey(int $productId, ?int $variantId = null): int|string
    {
        return $variantId === null ? $productId : $productId.':'.$variantId;
    }

    public function add(Product $product, int $qty = 1, ?ProductVariant $variant = null): void
    {
        $cart = $this->items();
        $key = self::lineKey($product->id, $variant?->id);

        if (isset($cart[$key])) {
            $cart[$key]['qty'] += $qty;
        } else {
            $cart[$key] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'price' => $variant ? $variant->effectiveSellingPrice() : (float) $product->selling_price,
                'image' => $product->display_image,
                'qty' => $qty,
            ];
            if ($variant) {
                $cart[$key]['variant_id'] = $variant->id;
                $cart[$key]['variant_name'] = $variant->name;
            }
        }

        $this->session->put(self::SESSION_KEY, $cart);
    }

    public function remove(int $productId, ?int $variantId = null): void
    {
        $cart = $this->items();
        unset($cart[self::lineKey($productId, $variantId)]);
        $this->session->put(self::SESSION_KEY, $cart);
    }

    public function updateQty(int $productId, int $qty, ?int $variantId = null): void
    {
        $cart = $this->items();
        $key = self::lineKey($productId, $variantId);

        if (isset($cart[$key])) {
            if ($qty <= 0) {
                unset($cart[$key]);
            } else {
                $cart[$key]['qty'] = $qty;
            }
        }

        $this->session->put(self::SESSION_KEY, $cart);
    }

    /** @param array<int|string, float> $prices */
    public function refreshPrices(array $prices): void
    {
        $items = $this->items();
        foreach ($items as $key => &$item) {
            if (array_key_exists($key, $prices)) {
                $item['price'] = $prices[$key];
            }
        }
        unset($item);
        $this->session->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return array_sum(array_column($this->items(), 'qty'));
    }

    public function subtotal(): float
    {
        return array_reduce($this->items(), fn ($carry, $item) => $carry + $item['price'] * $item['qty'], 0.0);
    }

    public function isEmpty(): bool
    {
        return empty($this->items());
    }
}
