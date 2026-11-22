<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CourierSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteadfastCourierTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_preserved_and_never_rendered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.settings.courier.steadfast.update'), ['enabled' => 1, 'api_key' => 'api-secret', 'secret_key' => 'secret-secret'])->assertRedirect();
        $setting = CourierSetting::query()->firstOrFail();
        $this->assertSame('api-secret', $setting->api_key);
        $this->assertNotSame('api-secret', $setting->getRawOriginal('api_key'));
        $this->actingAs($admin)->put(route('admin.settings.courier.steadfast.update'), ['enabled' => 1])->assertRedirect();
        $this->assertSame('api-secret', $setting->refresh()->api_key);
        $log = AuditLog::query()->where('action', 'admin.settings.courier.steadfast.update')->latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('api_key', $log->metadata ?? []);
        $this->assertArrayNotHasKey('secret_key', $log->metadata ?? []);
        $this->actingAs($admin)->get(route('admin.settings.courier.steadfast'))->assertOk()->assertDontSee('api-secret')->assertDontSee('secret-secret');
    }

    public function test_credential_validation_does_not_flash_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->put(route('admin.settings.courier.steadfast.update'), ['enabled' => 1, 'api_key' => 'api-secret', 'secret_key' => 'secret-secret', 'clear_api_key' => 1]);
        $response->assertSessionHasErrors('enabled');
        $this->assertArrayNotHasKey('api_key', session('_old_input', []));
        $this->assertArrayNotHasKey('secret_key', session('_old_input', []));
    }

    public function test_connection_test_uses_read_only_balance_endpoint(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.packzy.com/api/v1/get_balance' => Http::response(['status' => 200, 'current_balance' => 1000])]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.settings.courier.steadfast.update'), ['enabled' => 1, 'api_key' => 'api-secret', 'secret_key' => 'secret-secret']);
        $this->actingAs($admin)->post(route('admin.settings.courier.steadfast.test'))->assertRedirect()->assertSessionHas('success');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && str_ends_with($request->url(), '/get_balance'));
        $this->assertDatabaseHas('courier_settings', ['last_connection_status' => 'success']);
    }

    public function test_booking_calculates_cod_and_blocks_duplicate_submission(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.packzy.com/api/v1/create_order' => Http::response(['status' => 200, 'consignment' => ['consignment_id' => 123, 'tracking_code' => 'TRK123', 'status' => 'in_review']])]);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::create(['order_number' => 'BC-TEST-1', 'customer_name' => 'Customer', 'customer_phone' => '+8801712345678', 'customer_address' => 'House 1', 'customer_district' => 'Dhaka', 'subtotal' => 1000, 'shipping_charge' => 60, 'total' => 1060, 'status' => 'processing', 'payment_method' => 'cash_on_delivery']);
        Payment::create(['order_id' => $order->id, 'provider' => 'sslcommerz', 'transaction_id' => 'TX-1', 'amount' => 60, 'status' => 'paid']);
        $this->actingAs($admin)->put(route('admin.settings.courier.steadfast.update'), ['enabled' => 1, 'api_key' => 'api-secret', 'secret_key' => 'secret-secret']);
        $this->actingAs($admin)->post(route('admin.orders.steadfast.book', $order), ['note' => 'Call first'])->assertRedirect();
        $this->assertDatabaseHas('shipments', ['invoice' => 'BC-TEST-1', 'booking_state' => 'booked', 'cod_amount' => 1000]);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && (float) $request->data()['cod_amount'] === 1000.0 && $request->data()['recipient_phone'] === '01712345678');
        $this->actingAs($admin)->post(route('admin.orders.steadfast.book', $order), [])->assertSessionHasErrors('shipment');
        Http::assertSentCount(1);
    }

    public function test_timeout_marks_unknown_and_does_not_retry_create_order(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.packzy.com/api/v1/create_order' => Http::failedConnection()]);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::create(['order_number' => 'BC-TEST-2', 'customer_name' => 'Customer', 'customer_phone' => '01712345678', 'customer_address' => 'House 1', 'total' => 500, 'status' => 'processing', 'payment_method' => 'cash_on_delivery']);
        $this->actingAs($admin)->put(route('admin.settings.courier.steadfast.update'), ['enabled' => 1, 'api_key' => 'api-secret', 'secret_key' => 'secret-secret']);
        $this->actingAs($admin)->post(route('admin.orders.steadfast.book', $order), [])->assertRedirect();
        $this->assertDatabaseHas('shipments', ['invoice' => 'BC-TEST-2', 'booking_state' => 'outcome_unknown']);
        $this->actingAs($admin)->post(route('admin.orders.steadfast.book', $order), [])->assertSessionHasErrors('shipment');
        Http::assertSentCount(1);
    }

    public function test_disabled_courier_returns_to_order_with_a_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::create(['order_number' => 'BC-TEST-DISABLED', 'customer_name' => 'Customer', 'customer_phone' => '01712345678', 'customer_address' => 'House 1', 'total' => 500, 'status' => 'processing', 'payment_method' => 'cash_on_delivery']);
        $this->actingAs($admin)->post(route('admin.orders.steadfast.book', $order), [])->assertRedirect()->assertSessionHasErrors('steadfast');
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_tracking_maps_documented_and_unknown_statuses(): void
    {
        Http::preventStrayRequests();
        Http::fake(['portal.packzy.com/api/v1/status_by_cid/123' => Http::sequence()->push(['status' => 200, 'delivery_status' => 'delivered'])->push(['status' => 200, 'delivery_status' => 'future_status'])]);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::create(['order_number' => 'BC-TEST-3', 'customer_name' => 'Customer', 'customer_phone' => '01712345678', 'customer_address' => 'House 1', 'total' => 500, 'status' => 'processing', 'payment_method' => 'cash_on_delivery']);
        $shipment = Shipment::create(['order_id' => $order->id, 'courier' => 'steadfast', 'config_revision' => 1, 'invoice' => $order->order_number, 'consignment_id' => '123', 'booking_state' => 'booked']);
        $this->actingAs($admin)->put(route('admin.settings.courier.steadfast.update'), ['enabled' => 1, 'api_key' => 'api-secret', 'secret_key' => 'secret-secret']);
        $this->actingAs($admin)->post(route('admin.shipments.refresh', $shipment))->assertRedirect();
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'local_status' => 'delivered']);
        $this->actingAs($admin)->post(route('admin.shipments.refresh', $shipment))->assertRedirect();
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'provider_status' => 'future_status', 'local_status' => 'unknown']);
    }
}
