<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefusal;
use App\Models\StoreSetting;
use App\Services\Orders\OrderReviewService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CodOrderReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'cod_review',
            'database.connections.cod_review' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('cod_review');
        Artisan::call('migrate', ['--database' => 'cod_review', '--force' => true]);

        // Seed default COD review thresholds
        $defaults = [
            'cod_review_amount_threshold' => '3000',
            'cod_review_duplicate_minutes' => '15',
            'cod_review_refusal_count' => '2',
            'cod_review_refusal_days' => '90',
            'cod_review_active_orders_max' => '2',
        ];
        foreach ($defaults as $key => $value) {
            StoreSetting::create(['key' => $key, 'value' => $value]);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('cod_review');
        parent::tearDown();
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'BC-'.rand(10000, 99999),
            'customer_name' => 'Test Customer',
            'customer_phone' => '01712345678',
            'customer_address' => 'House 10, Road 5, Dhanmondi',
            'customer_district' => 'Dhaka',
            'delivery_zone' => 'inside_dhaka',
            'subtotal' => 1000,
            'shipping_charge' => 60,
            'discount_amount' => 0,
            'total' => 1060,
            'status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'checkout_token' => Str::uuid(),
        ], $overrides));
    }

    /** @test */
    public function online_payment_orders_are_always_ready(): void
    {
        $order = $this->makeOrder(['payment_method' => 'sslcommerz']);
        $service = new OrderReviewService;

        $result = $service->evaluate($order);

        $this->assertSame('ready', $result['status']);
        $this->assertEmpty($result['reasons']);
    }

    /** @test */
    public function valid_simple_cod_order_is_ready(): void
    {
        $order = $this->makeOrder();
        $service = new OrderReviewService;

        $result = $service->evaluate($order);

        $this->assertSame('ready', $result['status']);
    }

    /** @test */
    public function invalid_phone_triggers_needs_correction(): void
    {
        $order = $this->makeOrder(['customer_phone' => '12345']);
        $service = new OrderReviewService;

        $result = $service->evaluate($order);

        $this->assertSame('needs_correction', $result['status']);
        $this->assertContains('invalid_phone', $result['reasons']);
    }

    /** @test */
    public function missing_district_triggers_needs_correction(): void
    {
        $order = $this->makeOrder(['customer_district' => '']);
        $service = new OrderReviewService;

        $result = $service->evaluate($order);

        $this->assertSame('needs_correction', $result['status']);
        $this->assertContains('incomplete_address', $result['reasons']);
    }

    /** @test */
    public function short_address_triggers_needs_correction(): void
    {
        $order = $this->makeOrder(['customer_address' => 'House 1']);
        $service = new OrderReviewService;

        $result = $service->evaluate($order);

        $this->assertSame('needs_correction', $result['status']);
        $this->assertContains('incomplete_address', $result['reasons']);
    }

    /** @test */
    public function large_first_order_above_threshold_triggers_needs_confirmation(): void
    {
        $order = $this->makeOrder(['total' => 5000]);
        $service = new OrderReviewService;

        $result = $service->evaluate($order);

        $this->assertSame('needs_confirmation', $result['status']);
        $this->assertContains('large_first_order', $result['reasons']);
    }

    /** @test */
    public function large_order_from_returning_customer_is_ready(): void
    {
        // Create a prior delivered order for same phone
        $this->makeOrder(['status' => 'delivered', 'checkout_token' => Str::uuid()]);

        $order = $this->makeOrder(['total' => 5000]);
        $service = new OrderReviewService;

        $result = $service->evaluate($order);

        $this->assertSame('ready', $result['status']);
    }

    /** @test */
    public function repeat_refusals_trigger_needs_confirmation(): void
    {
        $phone = '01712345678';
        $order = $this->makeOrder(['customer_phone' => $phone]);

        OrderRefusal::create(['customer_phone' => $phone, 'order_id' => $order->id, 'reason' => 'First refusal']);
        OrderRefusal::create(['customer_phone' => $phone, 'order_id' => $order->id, 'reason' => 'Second refusal']);

        $newOrder = $this->makeOrder(['customer_phone' => $phone, 'checkout_token' => Str::uuid()]);
        $service = new OrderReviewService;

        $result = $service->evaluate($newOrder);

        $this->assertContains('repeat_refusals', $result['reasons']);
    }

    /** @test */
    public function too_many_active_orders_triggers_needs_confirmation(): void
    {
        $phone = '01712345678';

        // Two pre-existing active COD orders
        $this->makeOrder(['customer_phone' => $phone, 'checkout_token' => Str::uuid()]);
        $this->makeOrder(['customer_phone' => $phone, 'checkout_token' => Str::uuid()]);

        $newOrder = $this->makeOrder(['customer_phone' => $phone, 'checkout_token' => Str::uuid()]);
        $service = new OrderReviewService;

        $result = $service->evaluate($newOrder);

        $this->assertContains('too_many_active_orders', $result['reasons']);
    }

    /** @test */
    public function phone_normalization_handles_plus880_prefix(): void
    {
        $service = new OrderReviewService;

        $this->assertSame('01712345678', $service->normalizePhone('+8801712345678'));
        $this->assertSame('01712345678', $service->normalizePhone('8801712345678'));
        $this->assertSame('01712345678', $service->normalizePhone('01712345678'));
    }

    /** @test */
    public function apply_to_order_persists_review_status(): void
    {
        $order = $this->makeOrder(['customer_phone' => 'invalid', 'customer_district' => '']);
        $service = new OrderReviewService;

        $service->applyToOrder($order);

        $order->refresh();
        $this->assertSame('needs_correction', $order->review_status);
        $this->assertNotNull($order->review_reasons);
    }
}
