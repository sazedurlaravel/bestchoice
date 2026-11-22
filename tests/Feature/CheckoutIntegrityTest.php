<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CheckoutIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cod_order_uses_current_database_prices_and_correct_line_totals(): void
    {
        $product = Product::factory()->create(['selling_price' => 490.25, 'regular_price' => 650, 'stock_quantity' => 10]);
        app(CartService::class)->add($product, 3);
        $token = $this->get(route('checkout.index'))->assertOk()->viewData('checkoutToken');

        $this->post(route('checkout.store'), $this->customer($token))
            ->assertRedirect(route('order.confirmation'))->assertSessionMissing('cart');

        $this->assertDatabaseHas('orders', ['checkout_token' => $token, 'user_id' => null, 'subtotal' => 1470.75, 'shipping_charge' => 60, 'total' => 1530.75, 'payment_method' => 'cash_on_delivery', 'status' => 'pending']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 490.25, 'subtotal' => 1470.75]);
        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->get(route('order.confirmation'))->assertOk()->assertSee(Order::firstOrFail()->order_number);
    }

    public function test_authenticated_cod_order_preserves_customer_ownership_and_optional_fields(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);
        $token = $this->openCheckout(Product::factory()->create());

        $this->post(route('checkout.store'), [...$this->customer($token), 'customer_additional_phone' => '01800000000', 'note' => 'Call before delivery'])
            ->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseHas('orders', ['user_id' => $customer->id, 'customer_additional_phone' => '01800000000', 'note' => 'Call before delivery']);
    }

    public function test_price_change_at_placement_requires_review_and_a_new_attempt(): void
    {
        $product = Product::factory()->create(['selling_price' => 490, 'regular_price' => 650]);
        $token = $this->openCheckout($product);
        $product->update(['selling_price' => 590]);

        $this->post(route('checkout.store'), $this->customer($token))
            ->assertRedirect(route('checkout.index'))
            ->assertInvalid(['cart' => 'পণ্যের দাম পরিবর্তিত হয়েছে']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $product->refresh()->stock_quantity);
        $review = $this->get(route('checkout.index'))->assertOk()->assertSee('৳590');
        $newToken = $review->viewData('checkoutToken');
        $this->assertNotSame($token, $newToken);
        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['checkout_token']);
        $this->post(route('checkout.store'), $this->customer($newToken))->assertRedirect(route('order.confirmation'));
        $this->assertDatabaseHas('orders', ['subtotal' => 590, 'total' => 650]);
    }

    public function test_price_change_before_checkout_is_shown_before_customer_confirmation(): void
    {
        $product = Product::factory()->create(['selling_price' => 490]);
        app(CartService::class)->add($product);
        $product->update(['selling_price' => 450]);

        $page = $this->get(route('checkout.index'))->assertOk()->assertSee('দাম পরিবর্তিত হয়েছে')->assertSee('৳450');
        $this->post(route('checkout.store'), $this->customer($page->viewData('checkoutToken')))->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseHas('orders', ['subtotal' => 450, 'total' => 510]);
    }

    public function test_inactive_product_is_rejected_without_creating_order_or_deducting_stock(): void
    {
        $product = Product::factory()->create();
        $token = $this->openCheckout($product);
        $product->update(['is_active' => false]);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $product->refresh()->stock_quantity);
    }

    public function test_deleted_product_is_rejected_with_validation_feedback(): void
    {
        $product = Product::factory()->create();
        $token = $this->openCheckout($product);
        $product->delete();

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('unavailableStock')]
    public function test_insufficient_or_unavailable_stock_rejects_the_order(int $stock): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        app(CartService::class)->add($product, 3);
        $token = $this->get(route('checkout.index'))->assertOk()->viewData('checkoutToken');
        $product->update(['stock_quantity' => $stock]);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame($stock, $product->refresh()->stock_quantity);
    }

    public static function unavailableStock(): array
    {
        return ['out of stock' => [0], 'remaining stock below cart quantity' => [2]];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_session_quantities_are_rejected(mixed $quantity): void
    {
        $product = Product::factory()->create();
        app(CartService::class)->add($product);
        $items = app(CartService::class)->items();
        $items[$product->id]['qty'] = $quantity;
        $token = $this->issueAttempt($items);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['items.0.qty']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $product->refresh()->stock_quantity);
    }

    public static function invalidQuantities(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'fraction' => [1.5], 'above maximum' => [100], 'not numeric' => ['invalid'], 'missing' => [null]];
    }

    public function test_variant_id_in_checkout_request_is_not_silently_purchased_as_base_product(): void
    {
        $token = $this->openCheckout(Product::factory()->create());

        $this->post(route('checkout.store'), [...$this->customer($token), 'variant_id' => 99999])->assertInvalid(['variant_id']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_variant_id_in_session_cart_is_rejected(): void
    {
        $product = Product::factory()->create();
        app(CartService::class)->add($product);
        $items = app(CartService::class)->items();
        $items[$product->id]['variant_id'] = 99999;
        $token = $this->issueAttempt($items);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['items.0.variant_id']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_variant_based_product_cannot_bypass_variant_inventory(): void
    {
        $product = Product::factory()->create();
        $token = $this->openCheckout($product);
        ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Unavailable size', 'sku' => 'VARIANT-UNAVAILABLE', 'stock_quantity' => 0, 'is_active' => false]);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_variant_id_is_rejected_by_add_to_cart(): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.add'), ['product_id' => $product->id, 'qty' => 1, 'variant_id' => 99999])->assertInvalid(['variant_id']);

        $this->assertSame([], app(CartService::class)->items());
    }

    public function test_forged_client_prices_totals_and_delivery_are_ignored(): void
    {
        $token = $this->openCheckout(Product::factory()->create(['selling_price' => 490]));

        $this->post(route('checkout.store'), [...$this->customer($token), 'price' => 1, 'selling_price' => 1, 'subtotal' => 1, 'total' => 1, 'shipping_charge' => 0, 'discount' => 10000])
            ->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseHas('orders', ['subtotal' => 490, 'shipping_charge' => 60, 'total' => 550]);
        $this->assertDatabaseHas('order_items', ['unit_price' => 490, 'subtotal' => 490]);
    }

    public function test_invalid_database_discount_cannot_be_purchased(): void
    {
        $product = Product::factory()->create(['selling_price' => 490, 'regular_price' => 650]);
        $token = $this->openCheckout($product);
        Product::query()->whereKey($product->id)->update(['regular_price' => 400]);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_duplicate_submission_returns_same_order_after_cart_has_been_cleared(): void
    {
        $product = Product::factory()->create();
        $token = $this->openCheckout($product);

        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'));
        $orderId = session('last_order_id');
        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'))->assertSessionHas('last_order_id', $orderId);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(9, $product->refresh()->stock_quantity);
    }

    public function test_distinct_legitimate_attempts_create_distinct_orders(): void
    {
        $product = Product::factory()->create();
        $first = $this->openCheckout($product);
        $this->post(route('checkout.store'), $this->customer($first))->assertRedirect(route('order.confirmation'));
        $second = $this->openCheckout($product->refresh());

        $this->assertNotSame($first, $second);
        $this->post(route('checkout.store'), $this->customer($second))->assertRedirect(route('order.confirmation'));

        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertSame(8, $product->refresh()->stock_quantity);
    }

    public function test_replaying_completed_attempt_does_not_clear_a_new_cart(): void
    {
        $product = Product::factory()->create();
        $token = $this->openCheckout($product);
        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'));
        $newToken = $this->openCheckout($product->refresh());

        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'));

        $this->assertSame(1, app(CartService::class)->count());
        $this->assertSame($newToken, session('checkout_attempt.token'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_attempt_token_is_bound_to_the_customer_session(): void
    {
        $token = $this->openCheckout(Product::factory()->create());
        $this->flushSession();

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['checkout_token']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cart_changes_require_customer_to_review_a_new_quote(): void
    {
        $product = Product::factory()->create();
        $token = $this->openCheckout($product);
        app(CartService::class)->updateQty($product->id, 2);

        $this->post(route('checkout.store'), $this->customer($token))->assertInvalid(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_order_creation_failure_can_be_retried_with_same_attempt(): void
    {
        $product = Product::factory()->create();
        $token = $this->openCheckout($product);
        Order::creating(function (): void {
            throw new RuntimeException('Simulated order failure');
        });

        try {
            $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('checkout.index'))->assertSessionHas('error');
        } finally {
            Order::flushEventListeners();
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame($token, session('checkout_attempt.token'));
        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->refresh()->stock_quantity);
    }

    public function test_later_item_failure_rolls_back_order_items_and_all_stock_and_can_retry(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $token = $this->openCheckout($first, $second);
        Mail::fake();
        Notification::fake();
        Queue::fake();
        OrderItem::creating(function (OrderItem $item) use ($second): void {
            if ($item->product_id === $second->id) {
                throw new RuntimeException('Simulated later line failure');
            }
        });

        try {
            $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('checkout.index'))->assertSessionHas('error');
        } finally {
            OrderItem::flushEventListeners();
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(10, $first->refresh()->stock_quantity);
        $this->assertSame(10, $second->refresh()->stock_quantity);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
        $this->post(route('checkout.store'), $this->customer($token))->assertRedirect(route('order.confirmation'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_empty_cart_redirects_to_cart(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
    }

    public function test_checkout_shows_add_more_products_link(): void
    {
        app(CartService::class)->add(Product::factory()->create());

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('আরও পণ্য যোগ করুন')
            ->assertSee(route('shop'), false);
    }

    public function test_cart_add_update_and_remove_still_work(): void
    {
        $product = Product::factory()->create();
        $this->post(route('cart.add'), ['product_id' => $product->id, 'qty' => 1])->assertRedirect(route('cart.index'));
        $this->post(route('cart.update'), ['product_id' => $product->id, 'qty' => 2])->assertRedirect();
        $this->get(route('cart.index'))->assertOk()->assertSee('৳1,600');
        $this->post(route('cart.remove'), ['product_id' => $product->id])->assertRedirect();
        $this->get(route('cart.index'))->assertOk()->assertSee('আপনার কার্ট এখন খালি');
    }

    public function test_buy_now_adds_the_product_and_opens_checkout(): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.add'), [
            'product_id' => $product->id,
            'qty' => 1,
            'redirect_to' => 'checkout',
        ])->assertRedirect(route('checkout.index'));

        $this->assertSame(1, app(CartService::class)->items()[$product->id]['qty']);
    }

    public function test_repeated_cart_add_request_id_does_not_add_the_item_twice(): void
    {
        $product = Product::factory()->create();
        $payload = ['product_id' => $product->id, 'qty' => 1, 'cart_request_id' => (string) Str::uuid()];

        $this->post(route('cart.add'), $payload)->assertRedirect(route('cart.index'));
        $this->post(route('cart.add'), $payload)->assertRedirect(route('cart.index'));

        $this->assertSame(1, app(CartService::class)->items()[$product->id]['qty']);
    }

    private function openCheckout(Product ...$products): string
    {
        foreach ($products as $product) {
            app(CartService::class)->add($product);
        }

        return $this->get(route('checkout.index'))->assertOk()->viewData('checkoutToken');
    }

    /** @param array<int, array<string, mixed>> $items */
    private function issueAttempt(array $items): string
    {
        $token = (string) Str::uuid();
        $this->withSession(['cart' => $items, 'checkout_attempt' => ['token' => $token, 'fingerprint' => app(CheckoutService::class)->fingerprint($items, null)]]);

        return $token;
    }

    /** @return array{customer_name:string,customer_phone:string,customer_address:string,payment_method:string,checkout_token:string} */
    private function customer(string $token): array
    {
        return ['customer_name' => 'Checkout Test', 'customer_phone' => '01700000000', 'customer_address' => 'Test delivery address', 'payment_method' => 'cash_on_delivery', 'checkout_token' => $token];
    }
}
