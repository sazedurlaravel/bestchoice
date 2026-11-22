<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_configured_outside_dhaka_rate_and_stores_the_zone(): void
    {
        StoreSetting::updateOrCreate(['key' => 'delivery_charge_inside_dhaka'], ['value' => '70']);
        StoreSetting::updateOrCreate(['key' => 'delivery_charge_outside_dhaka'], ['value' => '150']);
        $product = Product::factory()->create(['stock_quantity' => 5, 'selling_price' => 500]);
        $items = [[
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => 500,
            'qty' => 1,
            'image' => $product->display_image,
        ]];

        $quote = app(CheckoutService::class)->quote($items, deliveryZone: 'outside_dhaka');
        $order = app(CheckoutService::class)->place([
            'customer_name' => 'Zone customer',
            'customer_phone' => '01700000000',
            'customer_address' => 'Chattogram',
            'delivery_zone' => 'outside_dhaka',
            'payment_method' => 'cash_on_delivery',
        ], $items, (string) Str::uuid(), null, null, 'outside_dhaka');

        $this->assertSame(150, $quote['shippingCharge']);
        $this->assertSame('150.00', $order->refresh()->shipping_charge);
        $this->assertSame('outside_dhaka', $order->delivery_zone);
    }
}
