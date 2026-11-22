<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CheckoutService;
use App\Services\Orders\OrderLifecycleService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class VariantCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_details_require_selection_and_show_each_variants_selling_and_regular_prices(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        $fallback = $this->variant($product, ['size' => 'L', 'price_override' => null, 'regular_price' => null]);
        $inactive = $this->variant($product, ['size' => 'XL', 'is_active' => false]);

        $response = $this->get(route('products.show', $product))->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $selectors = $xpath->query('//form//select[@name="variant_id"]');
        $this->assertCount(2, $selectors);
        foreach ($selectors as $selector) {
            $this->assertTrue($selector->hasAttribute('required'));
            $this->assertCount(1, $xpath->query('option[@value=""]', $selector));
            $explicitOption = $xpath->query('option[@value="'.$variant->id.'"]', $selector);
            $this->assertCount(1, $explicitOption);
            $this->assertStringContainsString($variant->sku, $explicitOption->item(0)->textContent);
            $this->assertStringContainsString('মূল্য: ৳490', $explicitOption->item(0)->textContent);
            $this->assertStringContainsString('নিয়মিত মূল্য: ৳650', $explicitOption->item(0)->textContent);
            $fallbackOption = $xpath->query('option[@value="'.$fallback->id.'"]', $selector);
            $this->assertCount(1, $fallbackOption);
            $this->assertStringContainsString('মূল্য: ৳800', $fallbackOption->item(0)->textContent);
            $this->assertStringContainsString('নিয়মিত মূল্য: ৳1,000', $fallbackOption->item(0)->textContent);
            $this->assertCount(0, $xpath->query('option[@value="'.$inactive->id.'"]', $selector));
        }
    }

    #[DataProvider('variantPrices')]
    public function test_valid_variant_purchase_uses_current_effective_price_and_records_variant_deduction(?float $selling, ?float $regular, float $unitPrice, float $subtotal, float $total): void
    {
        $product = $this->product();
        $variant = $this->variant($product, ['price_override' => $selling, 'regular_price' => $regular]);
        $this->post(route('cart.add'), ['product_id' => $product->id, 'variant_id' => $variant->id, 'qty' => 2])
            ->assertRedirect(route('cart.index'))->assertValid();
        $token = $this->checkoutToken();

        $this->post(route('checkout.store'), $this->customer($token))
            ->assertRedirect(route('order.confirmation'))->assertSessionMissing('cart');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['checkout_token' => $token, 'subtotal' => $subtotal, 'shipping_charge' => 60, 'total' => $total, 'payment_method' => 'cash_on_delivery']);
        $this->assertDatabaseCount('order_items', 1);
        $item = OrderItem::query()->sole();
        $this->assertDatabaseHas('order_items', [
            'id' => $item->id, 'product_id' => $product->id, 'variant_id' => $variant->id,
            'variant_name' => $variant->name, 'product_sku' => $variant->sku,
            'quantity' => 2, 'unit_price' => $unitPrice, 'subtotal' => $subtotal,
            'stock_deducted_quantity' => 2, 'stock_restored_quantity' => 0,
        ]);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertSame(8, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDeduction($item, $variant, 2, 10, 8);
    }

    public static function variantPrices(): array
    {
        return [
            'explicit variant prices' => [490.25, 650.0, 490.25, 980.5, 1040.5],
            'parent price fallback' => [null, null, 800.0, 1600.0, 1660.0],
        ];
    }

    public function test_two_variants_of_one_product_remain_separate_cart_and_order_lines_with_independent_stock(): void
    {
        $product = $this->product();
        $first = $this->variant($product);
        $second = $this->variant($product, ['size' => 'L', 'price_override' => 600, 'regular_price' => 750, 'stock_quantity' => 5]);
        $this->post(route('cart.add'), ['product_id' => $product->id, 'variant_id' => $first->id, 'qty' => 2])->assertRedirect(route('cart.index'));
        $this->post(route('cart.add'), ['product_id' => $product->id, 'variant_id' => $second->id, 'qty' => 3])->assertRedirect(route('cart.index'));
        $cart = app(CartService::class)->items();
        $this->assertCount(2, $cart);
        $this->assertSame(2, $cart[$product->id.':'.$first->id]['qty']);
        $this->assertSame(3, $cart[$product->id.':'.$second->id]['qty']);
        $this->assertSame($first->name, $cart[$product->id.':'.$first->id]['variant_name']);
        $this->assertSame($second->name, $cart[$product->id.':'.$second->id]['variant_name']);

        $this->post(route('checkout.store'), $this->customer($this->checkoutToken()))->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseHas('orders', ['subtotal' => 2780.5, 'total' => 2840.5]);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', ['variant_id' => $first->id, 'quantity' => 2, 'subtotal' => 980.5]);
        $this->assertDatabaseHas('order_items', ['variant_id' => $second->id, 'quantity' => 3, 'subtotal' => 1800]);
        $this->assertSame(8, $first->refresh()->stock_quantity);
        $this->assertSame(2, $second->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDeduction(OrderItem::query()->where('variant_id', $first->id)->sole(), $first, 2, 10, 8);
        $this->assertDeduction(OrderItem::query()->where('variant_id', $second->id)->sole(), $second, 3, 5, 2);
    }

    public function test_cart_update_accepts_exact_variant_stock_and_rejects_excess_without_changing_either_line(): void
    {
        $product = $this->product();
        $first = $this->variant($product, ['stock_quantity' => 3]);
        $second = $this->variant($product, ['size' => 'L']);
        app(CartService::class)->add($product, 1, $first);
        app(CartService::class)->add($product, 2, $second);

        $this->post(route('cart.update'), ['product_id' => $product->id, 'variant_id' => $first->id, 'qty' => 3])->assertRedirect()->assertValid();
        $cart = app(CartService::class)->items();
        $this->assertSame(3, $cart[$product->id.':'.$first->id]['qty']);
        $this->assertSame(2, $cart[$product->id.':'.$second->id]['qty']);
        $this->post(route('cart.update'), ['product_id' => $product->id, 'variant_id' => $first->id, 'qty' => 4])->assertInvalid(['cart']);

        $this->assertSame($cart, app(CartService::class)->items());
        $this->assertSame(3, $first->refresh()->stock_quantity);
        $this->assertSame(10, $second->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('removalActions')]
    public function test_cart_removal_targets_only_the_requested_variant(string $route, array $extra): void
    {
        $product = $this->product();
        $first = $this->variant($product);
        $second = $this->variant($product, ['size' => 'L']);
        app(CartService::class)->add($product, 1, $first);
        app(CartService::class)->add($product, 2, $second);
        $remaining = app(CartService::class)->items()[$product->id.':'.$second->id];

        $this->post(route($route), ['product_id' => $product->id, 'variant_id' => $first->id, ...$extra])->assertRedirect();

        $this->assertSame([$product->id.':'.$second->id => $remaining], app(CartService::class)->items());
        $this->assertSame(10, $first->refresh()->stock_quantity);
        $this->assertSame(10, $second->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public static function removalActions(): array
    {
        return ['remove endpoint' => ['cart.remove', []], 'zero quantity update' => ['cart.update', ['qty' => 0]]];
    }

    public function test_cart_add_requires_variant_selection_for_a_variant_product(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'qty' => 1])->assertInvalid(['variant_id']);

        $this->assertSame([], app(CartService::class)->items());
        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_cart_add_rejects_a_variant_belonging_to_another_product(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        $otherProduct = $this->product();

        $this->post(route('cart.add'), ['product_id' => $otherProduct->id, 'variant_id' => $variant->id, 'qty' => 1])->assertInvalid(['variant_id']);

        $this->assertSame([], app(CartService::class)->items());
        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('invalidSelectedVariants')]
    public function test_invalid_selected_variant_is_rejected_by_checkout_get_and_post_without_stock_changes(array $attributes, string $selection): void
    {
        $product = $this->product();
        $variant = $this->variant($product, $attributes);
        app(CartService::class)->add($product, 1, $variant);
        $items = app(CartService::class)->items();
        if ($selection === 'wrong product') {
            $other = $this->product();
            $items[$product->id.':'.$variant->id]['product_id'] = $other->id;
        } elseif ($selection === 'nonexistent') {
            $items[$product->id.':'.$variant->id]['variant_id'] = $variant->id + 10000;
        }
        $token = $this->issueAttempt($items);

        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart', 'items.0.variant_id']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    public static function invalidSelectedVariants(): array
    {
        return [
            'variant from another product' => [[], 'wrong product'],
            'nonexistent variant' => [[], 'nonexistent'],
            'inactive variant' => [['is_active' => false], 'selected'],
            'untyped legacy variant' => [['size' => null, 'color' => null], 'selected'],
        ];
    }

    public function test_checkout_rejects_a_variant_product_without_a_selected_variant(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        app(CartService::class)->add($product);
        $token = $this->issueAttempt(app(CartService::class)->items());

        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart' => 'পণ্যের সাইজ বা রঙ নির্বাচন করুন।']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    #[DataProvider('unavailableStock')]
    public function test_variant_stock_is_rechecked_at_placement(int $stock): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        app(CartService::class)->add($product, 3, $variant);
        $token = $this->checkoutToken();
        $variant->update(['stock_quantity' => $stock]);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart']);
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame($stock, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    public static function unavailableStock(): array
    {
        return ['out of stock' => [0], 'insufficient remaining stock' => [2]];
    }

    public function test_nonzero_parent_stock_cannot_be_used_to_purchase_a_variant(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        app(CartService::class)->add($product, 1, $variant);
        $token = $this->checkoutToken();
        Product::query()->whereKey($product->id)->update(['stock_quantity' => 5]);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart', 'items.0.variant_id']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_variant_price_change_requires_review_and_get_issues_a_new_token_with_current_price(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        app(CartService::class)->add($product, 2, $variant);
        $token = $this->checkoutToken();
        $variant->update(['price_override' => 590]);

        $this->post(route('checkout.store'), $this->customer($token))
            ->assertRedirect(route('checkout.index'))->assertInvalid(['cart' => 'পণ্যের দাম পরিবর্তিত হয়েছে']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $review = $this->get(route('checkout.index'))->assertOk()->assertSee('৳590');
        $newToken = $review->viewData('checkoutToken');
        $this->assertNotSame($token, $newToken);
        $this->assertSame(590.0, app(CartService::class)->items()[$product->id.':'.$variant->id]['price']);
        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['checkout_token']);
        $this->post(route('checkout.store'), $this->customer($newToken))->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseHas('orders', ['checkout_token' => $newToken, 'subtotal' => 1180, 'total' => 1240]);
        $this->assertDatabaseHas('order_items', ['variant_id' => $variant->id, 'unit_price' => 590, 'subtotal' => 1180]);
        $this->assertSame(8, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    public function test_forged_variant_prices_and_order_totals_are_ignored(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        app(CartService::class)->add($product, 2, $variant);
        $token = $this->checkoutToken();

        $this->post(route('checkout.store'), [
            ...$this->customer($token), 'price' => 1, 'price_override' => 1, 'selling_price' => 1,
            'subtotal' => 1, 'total' => 1, 'shipping_charge' => 0, 'discount' => 10000,
        ])->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseHas('orders', ['subtotal' => 980.5, 'shipping_charge' => 60, 'total' => 1040.5]);
        $this->assertDatabaseHas('order_items', ['variant_id' => $variant->id, 'unit_price' => 490.25, 'subtotal' => 980.5]);
        $this->assertSame(8, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_duplicate_variant_checkout_post_returns_the_same_order_and_deducts_stock_once(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        app(CartService::class)->add($product, 2, $variant);
        $token = $this->checkoutToken();
        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'))->assertSessionMissing('cart');
        $order = Order::query()->sole();

        $this->post(route('checkout.store'), $this->customer($token))
            ->assertRedirect(route('order.confirmation'))->assertSessionHas('last_order_id', $order->id);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(8, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDeduction(OrderItem::query()->sole(), $variant, 2, 10, 8);
    }

    public function test_cancelling_pending_variant_order_restores_only_its_variant_once(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        $sibling = $this->variant($product, ['size' => 'L', 'stock_quantity' => 7]);
        app(CartService::class)->add($product, 3, $variant);
        $this->post(route('checkout.store'), $this->customer($this->checkoutToken()))->assertRedirect(route('order.confirmation'));
        $order = Order::query()->sole();
        $item = OrderItem::query()->sole();
        $admin = User::factory()->create(['role' => 'admin']);
        $lifecycle = app(OrderLifecycleService::class);
        $this->assertSame(7, $variant->refresh()->stock_quantity);

        $lifecycle->transition($order, 'cancelled', 'Customer cancelled', $admin);
        $lifecycle->transition($order, 'cancelled', 'Customer cancelled', $admin);

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $this->assertSame(7, $sibling->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'stock_deducted_quantity' => 3, 'stock_restored_quantity' => 3]);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'variant_id' => $variant->id, 'type' => 'stock_in',
            'quantity_change' => 3, 'stock_before' => 7, 'stock_after' => 10,
            'operation_key' => 'order-item:'.$item->id.':cancel', 'reference_type' => 'order_item', 'reference_id' => $item->id,
        ]);
        $this->assertDatabaseCount('order_status_histories', 1);
    }

    public function test_partial_return_restocks_only_the_exact_variant_and_retry_does_not_restore_twice(): void
    {
        $product = $this->product();
        $first = $this->variant($product);
        $second = $this->variant($product, ['size' => 'L', 'stock_quantity' => 7]);
        app(CartService::class)->add($product, 3, $first);
        app(CartService::class)->add($product, 2, $second);
        $this->post(route('checkout.store'), $this->customer($this->checkoutToken()))->assertRedirect(route('order.confirmation'));
        $order = Order::query()->sole();
        $item = OrderItem::query()->where('variant_id', $first->id)->sole();
        $admin = User::factory()->create(['role' => 'admin']);
        $lifecycle = app(OrderLifecycleService::class);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $lifecycle->transition($order, $status, null, $admin);
        }
        $key = (string) Str::uuid();
        $return = $lifecycle->requestReturn($order, $item, 1, 'One item does not fit', $key, $admin);
        $retry = $lifecycle->requestReturn($order, $item, 1, 'One item does not fit', $key, $admin);
        $this->assertSame($return->id, $retry->id);
        $lifecycle->transitionReturn($return, 'approved', null, $admin);
        $lifecycle->transitionReturn($return, 'received', null, $admin);
        $this->assertSame(7, $first->refresh()->stock_quantity);
        $this->assertSame(5, $second->refresh()->stock_quantity);

        $lifecycle->transitionReturn($return, 'restocked', 'Inspected and approved as sellable', $admin);
        $lifecycle->transitionReturn($return, 'restocked', 'Inspected and approved as sellable', $admin);
        $lifecycle->transitionReturn($return, 'completed', null, $admin);
        $lifecycle->transitionReturn($return, 'completed', null, $admin);

        $this->assertSame(8, $first->refresh()->stock_quantity);
        $this->assertSame(5, $second->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertSame('delivered', $order->refresh()->status);
        $this->assertDatabaseCount('order_returns', 1);
        $this->assertDatabaseHas('order_returns', ['id' => $return->id, 'order_item_id' => $item->id, 'quantity' => 1, 'restocked_quantity' => 1, 'status' => 'completed']);
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'stock_deducted_quantity' => 3, 'stock_restored_quantity' => 1]);
        $this->assertDatabaseHas('order_items', ['variant_id' => $second->id, 'stock_deducted_quantity' => 2, 'stock_restored_quantity' => 0]);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'variant_id' => $first->id, 'type' => 'stock_in',
            'quantity_change' => 1, 'stock_before' => 7, 'stock_after' => 8,
            'operation_key' => 'return:'.$return->id.':restock', 'reference_type' => 'order_return', 'reference_id' => $return->id,
        ]);
    }

    public function test_later_variant_item_failure_rolls_back_all_lines_stock_and_ledger_and_allows_retry(): void
    {
        $product = $this->product();
        $first = $this->variant($product);
        $second = $this->variant($product, ['size' => 'L', 'stock_quantity' => 7]);
        app(CartService::class)->add($product, 2, $first);
        app(CartService::class)->add($product, 3, $second);
        $token = $this->checkoutToken();
        $cart = app(CartService::class)->items();
        $dispatcher = OrderItem::getEventDispatcher();
        OrderItem::setEventDispatcher(clone $dispatcher);
        $failedAfterDeduction = false;
        OrderItem::creating(function (OrderItem $item) use ($first, $second, &$failedAfterDeduction): void {
            if ($item->variant_id === $second->id) {
                $this->assertSame(8, $first->fresh()->stock_quantity);
                $this->assertDatabaseCount('inventory_transactions', 1);
                $failedAfterDeduction = true;
                throw new RuntimeException('Simulated later variant line failure');
            }
        });

        try {
            $this->post(route('checkout.store'), $this->customer($token))
                ->assertRedirect(route('checkout.index'))->assertSessionHas('error');
        } finally {
            OrderItem::setEventDispatcher($dispatcher);
        }

        $this->assertTrue($failedAfterDeduction, 'The failure must occur after the first variant was deducted.');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $first->refresh()->stock_quantity);
        $this->assertSame(7, $second->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertSame($cart, app(CartService::class)->items());
        $this->assertSame($token, session('checkout_attempt.token'));

        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertSame(8, $first->refresh()->stock_quantity);
        $this->assertSame(4, $second->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    private function product(): Product
    {
        return Product::factory()->create(['stock_quantity' => 0, 'selling_price' => 800, 'regular_price' => 1000, 'is_active' => true]);
    }

    private function variant(Product $product, array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Merchant medium blue',
            'sku' => 'VAR-'.Str::uuid(), 'size' => 'M', 'color' => 'Blue',
            'price_override' => 490.25, 'regular_price' => 650,
            'stock_quantity' => 10, 'is_active' => true, ...$attributes,
        ]);
    }

    private function checkoutToken(): string
    {
        return $this->get(route('checkout.index'))->assertOk()->viewData('checkoutToken');
    }

    private function issueAttempt(array $items): string
    {
        $token = (string) Str::uuid();
        $this->withSession(['cart' => $items, 'checkout_attempt' => [
            'token' => $token, 'fingerprint' => app(CheckoutService::class)->fingerprint($items, null),
        ]]);

        return $token;
    }

    private function customer(string $token): array
    {
        return [
            'customer_name' => 'Variant Checkout Test', 'customer_phone' => '01700000000',
            'customer_address' => 'Test delivery address', 'payment_method' => 'cash_on_delivery',
            'checkout_token' => $token,
        ];
    }

    private function assertDeduction(OrderItem $item, ProductVariant $variant, int $quantity, int $before, int $after): void
    {
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $variant->product_id, 'variant_id' => $variant->id,
            'type' => 'stock_out', 'quantity_change' => -$quantity,
            'stock_before' => $before, 'stock_after' => $after,
            'operation_key' => 'order-item:'.$item->id.':deduct',
            'reference_type' => 'order_item', 'reference_id' => $item->id,
        ]);
    }
}
