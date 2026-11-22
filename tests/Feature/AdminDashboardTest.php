<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reports_profit_for_today_week_and_month_and_excludes_cancelled_orders(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['selling_price' => 500, 'buying_price' => 300]);

        $this->orderWithItem($product, 2, 500, 300, 'pending', Carbon::parse('2026-11-13 10:00:00'));
        $this->orderWithItem($product, 1, 500, 200, 'delivered', Carbon::parse('2026-11-10 10:00:00'));
        $this->orderWithItem($product, 1, 400, 100, 'delivered', Carbon::parse('2026-11-01 10:00:00'));
        $this->orderWithItem($product, 1, 500, 100, 'cancelled', Carbon::parse('2026-11-13 09:00:00'));
        $this->orderWithItem($product, 1, 1000, 1, 'delivered', Carbon::parse('2025-11-01 10:00:00'));

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('todayProfit', 400.0);
        $response->assertViewHas('weekProfit', 700.0);
        $response->assertViewHas('monthProfit', 1000.0);
        $response->assertViewHas('profitPeriod', 'daily');
        $response->assertViewHas('filteredProfit', 400.0);
        $response->assertSee("Today's Profit", false);
        $response->assertSee("This Week's Profit", false);
        $response->assertSee("This Month's Profit", false);
        $response->assertSee(e($product->main_image), false);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', ['profit_period' => 'yearly']));

        $response->assertOk();
        $response->assertViewHas('profitPeriod', 'yearly');
        $response->assertViewHas('filteredProfit', 1000.0);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', ['profit_period' => 'weekly']));

        $response->assertOk();
        $response->assertViewHas('profitPeriod', 'weekly');
        $response->assertViewHas('filteredProfit', 700.0);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', ['profit_period' => 'monthly']));

        $response->assertOk();
        $response->assertViewHas('profitPeriod', 'monthly');
        $response->assertViewHas('filteredProfit', 1000.0);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', ['profit_period' => 'lifetime']));

        $response->assertOk();
        $response->assertViewHas('profitPeriod', 'lifetime');
        $response->assertViewHas('filteredProfit', 1999.0);
    }

    private function orderWithItem(Product $product, int $quantity, int $unitPrice, int $buyingPrice, string $status, Carbon $createdAt): void
    {
        $order = Order::create([
            'order_number' => 'TEST-'.uniqid(),
            'customer_name' => 'Dashboard customer',
            'customer_phone' => '01700000000',
            'customer_address' => 'Dhaka',
            'subtotal' => $quantity * $unitPrice,
            'shipping_charge' => 60,
            'total' => $quantity * $unitPrice + 60,
            'status' => $status,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'buying_price' => $buyingPrice,
            'subtotal' => $quantity * $unitPrice,
        ]);

        Order::withoutTimestamps(function () use ($order, $createdAt): void {
            $order->created_at = $createdAt;
            $order->save();
        });
    }
}
