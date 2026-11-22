<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderAdministrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Migrations run only on this explicitly owned in-memory connection.
        config([
            'database.default' => 'order_administration',
            'database.connections.order_administration' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('order_administration');
        Artisan::call('migrate', ['--database' => 'order_administration', '--force' => true]);
    }

    protected function tearDown(): void
    {
        try {
            DB::purge('order_administration');
        } finally {
            parent::tearDown();
        }
    }

    public static function protectedEndpoints(): array
    {
        return [
            'inventory page' => ['GET', '/admin/inventory'],
            'inventory adjustment' => ['POST', '/admin/inventory/adjust'],
            'order detail' => ['GET', '/admin/orders/1'],
            'order status' => ['PATCH', '/admin/orders/1/status'],
            'return request' => ['POST', '/admin/orders/1/returns'],
            'return processing' => ['PATCH', '/admin/orders/1/returns/1'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect(route('login'));

        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('order_returns', 0);
    }

    #[DataProvider('protectedEndpoints')]
    public function test_customers_are_forbidden_from_admin_endpoints(string $method, string $uri): void
    {
        $admin = $this->admin();
        [$order] = $this->order($admin, delivered: true);
        $this->requestedReturn($order, $admin);
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->call($method, $uri)->assertForbidden();

        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('order_returns', 1);
    }

    public function test_admin_can_cancel_and_restore_variant_stock_exactly_once(): void
    {
        $admin = $this->admin();
        [$order, $product, $variant] = $this->order($admin, variant: true);
        $url = route('admin.orders.status', $order);
        $payload = ['status' => 'cancelled', 'reason' => 'Customer requested cancellation'];

        $this->actingAs($admin)->from(route('admin.orders.show', $order))
            ->patch($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->patch($url, $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(10, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'cancellation_reason' => $payload['reason']]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'stock_restored_quantity' => 2]);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseHas('inventory_transactions', ['reference_type' => 'order_item', 'reference_id' => $order->items->first()->id, 'quantity_change' => 2, 'user_id' => $admin->id]);
        $this->assertDatabaseCount('order_status_histories', 1);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee($payload['reason'])->assertSee($admin->name)->assertDontSee('name="status"', false);
    }

    public function test_cancellation_requires_a_reason_without_restoring_stock(): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin);

        $this->actingAs($admin)->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertInvalid(['reason' => 'A cancellation reason is required.']);

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_invalid_status_transition_is_rejected_and_not_offered_in_the_form(): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin);
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('value="processing"', false)->assertSee('value="cancelled"', false)
            ->assertDontSee('value="delivered"', false)->assertDontSee('value="shipped"', false)
            ->assertDontSee('name="order_item_id"', false);

        $this->patch(route('admin.orders.status', $order), ['status' => 'delivered'])
            ->assertInvalid(['status' => 'The transition from pending to delivered is not allowed.']);

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_order_details_displays_the_product_main_image(): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee(e($product->main_image), false)
            ->assertSee('alt="'.$order->items->first()->product_name.'"', false);
    }

    public function test_delivered_return_request_and_restock_processing_are_idempotent(): void
    {
        $admin = $this->admin();
        [$order, $product, $variant] = $this->order($admin, delivered: true, variant: true);
        $this->actingAs($admin);
        $payload = $this->returnPayload($order);
        $url = route('admin.orders.returns.store', $order);

        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $return = OrderReturn::query()->sole();
        $this->assertSame('requested', $return->status);
        $this->assertSame(8, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $update = route('admin.orders.returns.update', [$order, $return]);
        $this->patch($update, ['status' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch($update, ['status' => 'received'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('value="restocked"', false)->assertSee('value="completed"', false)
            ->assertDontSee('value="approved"', false)->assertSee('name="note"', false);
        $restock = ['status' => 'restocked', 'note' => 'Inspected and sellable'];
        $this->patch($update, $restock)->assertRedirect()->assertSessionHasNoErrors();
        $this->patch($update, $restock)->assertRedirect()->assertSessionHasNoErrors();
        $this->patch($update, ['status' => 'completed'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('order_returns', 1);
        $this->assertDatabaseHas('order_returns', ['id' => $return->id, 'status' => 'completed', 'restocked_quantity' => 1, 'disposition_note' => $restock['note']]);
        $this->assertSame(9, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'stock_restored_quantity' => 1]);
        $this->assertDatabaseHas('inventory_transactions', ['reference_type' => 'order_return', 'reference_id' => $return->id, 'quantity_change' => 1, 'user_id' => $admin->id]);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseCount('order_status_histories', 8);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Completed')->assertSee('Return #'.$return->id)->assertSee($restock['note']);
    }

    #[DataProvider('dispositions')]
    public function test_received_return_requires_a_disposition_note(string $status): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin, delivered: true);
        $return = $this->requestedReturn($order, $admin);
        $service = app(OrderLifecycleService::class);
        $service->transitionReturn($return, 'approved', null, $admin);
        $service->transitionReturn($return, 'received', null, $admin);

        $this->actingAs($admin)->patch(route('admin.orders.returns.update', [$order, $return]), ['status' => $status])->assertInvalid(['note']);

        $this->assertSame('received', $return->refresh()->status);
        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public static function dispositions(): array
    {
        return ['sellable' => ['restocked'], 'non-restock' => ['completed']];
    }

    public function test_non_restock_completion_records_note_without_restoring_inventory(): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin, delivered: true);
        $return = $this->requestedReturn($order, $admin);
        app(OrderLifecycleService::class)->transitionReturn($return, 'approved', null, $admin);
        app(OrderLifecycleService::class)->transitionReturn($return, 'received', null, $admin);

        $this->actingAs($admin)->patch(route('admin.orders.returns.update', [$order, $return]), ['status' => 'completed', 'note' => 'Damaged; not sellable'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_returns', ['id' => $return->id, 'status' => 'completed', 'restocked_quantity' => 0, 'disposition_note' => 'Damaged; not sellable']);
        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_invalid_return_transition_does_not_change_stock_or_history(): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin, delivered: true);
        $return = $this->requestedReturn($order, $admin);

        $this->actingAs($admin)->patch(route('admin.orders.returns.update', [$order, $return]), ['status' => 'restocked', 'note' => 'Too early'])
            ->assertInvalid(['status' => 'The transition from requested to restocked is not allowed.']);

        $this->assertSame('requested', $return->refresh()->status);
        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('order_status_histories', 4);
    }

    public function test_cross_order_return_and_item_requests_return_404(): void
    {
        $admin = $this->admin();
        [$order] = $this->order($admin, delivered: true);
        [$other] = $this->order($admin, delivered: true);
        $return = $this->requestedReturn($other, $admin);

        $this->actingAs($admin)->patch(route('admin.orders.returns.update', [$order, $return]), ['status' => 'approved'])->assertNotFound();
        $this->post(route('admin.orders.returns.store', $order), $this->returnPayload($other))->assertNotFound();

        $this->assertSame('requested', $return->refresh()->status);
        $this->assertDatabaseCount('order_returns', 1);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_return_request_requires_delivery_and_cannot_exceed_ordered_quantity(): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin);
        $this->actingAs($admin)->post(route('admin.orders.returns.store', $order), $this->returnPayload($order))
            ->assertInvalid(['order' => 'Returns may only be requested for delivered orders.']);
        $this->deliver($order, $admin);

        $this->post(route('admin.orders.returns.store', $order), [...$this->returnPayload($order), 'quantity' => 3])->assertInvalid(['quantity']);

        $this->assertDatabaseCount('order_returns', 0);
        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_admin_inventory_shows_variant_actor_references_and_uuid_and_preserves_errors(): void
    {
        $admin = $this->admin();
        [$order, $product, $variant] = $this->order($admin, variant: true);
        $variant->update(['stock_quantity' => 2]);
        $this->actingAs($admin)->get(route('admin.inventory'))->assertOk()
            ->assertSee($variant->sku)->assertSee($admin->name)->assertSee('order_item #'.$order->items->first()->id)
            ->assertSee('name="variant_id"', false)->assertSee('name="expected_stock"', false)
            ->assertSee('value="adjustment"', false)->assertSee('name="operation_key"', false);
        $key = (string) Str::uuid();

        $this->from(route('admin.inventory'))->post(route('admin.inventory.adjust'), [
            'product_id' => $product->id, 'variant_id' => $variant->id, 'type' => 'adjustment', 'quantity' => 0, 'operation_key' => $key,
        ])->assertInvalid(['expected_stock']);

        $this->get(route('admin.inventory'))->assertOk()->assertSee($key);
        $this->assertSame(2, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_admin_order_list_displays_product_images(): void
    {
        $admin = $this->admin();
        [$order, $product] = $this->order($admin);

        $this->actingAs($admin)->get(route('admin.orders'))->assertOk()
            ->assertSee(e($product->main_image), false)
            ->assertSee('alt="'.$order->items->first()->product_name.'"', false);

        $product->update(['main_image' => null]);
        $this->get(route('admin.orders'))->assertOk()->assertDontSee('<img', false);
    }

    public function test_admin_order_list_displays_only_one_main_image_per_order(): void
    {
        $admin = $this->admin();
        [$order, $firstProduct] = $this->order($admin);
        $secondProduct = Product::factory()->create(['main_image' => 'https://example.com/second-product.jpg']);
        $order->items()->create([
            'product_id' => $secondProduct->id,
            'product_name' => $secondProduct->name,
            'product_sku' => $secondProduct->sku,
            'quantity' => 1,
            'unit_price' => 800,
            'subtotal' => 800,
        ]);

        $page = $this->actingAs($admin)->get(route('admin.orders'))->assertOk();

        $this->assertSame(1, substr_count($page->getContent(), '<img src="'));
        $page->assertSee(e($firstProduct->main_image), false)
            ->assertDontSee($secondProduct->main_image, false);
    }

    public function test_order_detail_uses_variant_snapshot_and_escapes_return_text(): void
    {
        $admin = $this->admin();
        [$order, , $variant] = $this->order($admin, delivered: true, variant: true);
        $snapshot = $variant->name;
        $variant->update(['name' => 'Renamed current variant']);
        $reason = '<script>alert("return")</script>';
        $payload = [...$this->returnPayload($order), 'reason' => $reason];
        $this->actingAs($admin)->post(route('admin.orders.returns.store', $order), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $page = $this->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee($snapshot)->assertDontSee('Renamed current variant')->assertSee($reason)->assertDontSee($reason, false);
        preg_match('/name="operation_key" value="([^"]+)"/', $page->getContent(), $matches);
        $this->assertTrue(Str::isUuid($matches[1] ?? ''));
    }

    #[DataProvider('validationPages')]
    public function test_admin_page_renders_validation_messages(string $routeName): void
    {
        $admin = $this->admin();
        [$order] = $this->order($admin);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['reason' => 'Please supply a reason.']));
        $this->actingAs($admin)->withSession(['errors' => $errors]);
        // Persist the bag in the configured JSON format before the HTTP session loads it.
        session()->save();

        $this->get(route($routeName, $routeName === 'admin.orders.show' ? [$order] : []))
            ->assertOk()->assertSee('Please supply a reason.');
    }

    public static function validationPages(): array
    {
        return ['inventory' => ['admin.inventory'], 'order detail' => ['admin.orders.show']];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @return array{Order, Product, ?ProductVariant} */
    private function order(User $admin, bool $delivered = false, bool $variant = false): array
    {
        $product = Product::factory()->create(['stock_quantity' => $variant ? 0 : 10]);
        $selected = $variant ? $product->variants()->create([
            'name' => 'Large / Green', 'size' => 'L', 'color' => 'Green', 'sku' => 'VAR-'.Str::uuid(), 'stock_quantity' => 10, 'is_active' => true,
        ]) : null;
        $line = ['product_id' => $product->id, 'qty' => 2, 'price' => 800];
        if ($selected) {
            $line['variant_id'] = $selected->id;
        }
        $order = app(CheckoutService::class)->place([
            'customer_name' => 'Test Customer', 'customer_phone' => '01700000000', 'customer_address' => 'Dhaka', 'payment_method' => 'cash_on_delivery',
        ], [$line], (string) Str::uuid(), $admin->id);
        if ($delivered) {
            $this->deliver($order, $admin);
        }

        return [$order, $product, $selected];
    }

    private function deliver(Order $order, User $admin): void
    {
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            app(OrderLifecycleService::class)->transition($order, $status, null, $admin);
        }
    }

    private function returnPayload(Order $order): array
    {
        return ['order_item_id' => $order->items->first()->id, 'quantity' => 1, 'reason' => 'Size does not fit', 'operation_key' => (string) Str::uuid()];
    }

    private function requestedReturn(Order $order, User $admin): OrderReturn
    {
        $payload = $this->returnPayload($order);

        return app(OrderLifecycleService::class)->requestReturn($order, $order->items->first(), $payload['quantity'], $payload['reason'], $payload['operation_key'], $admin);
    }
}
