<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 10): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory(),
            'stock_quantity' => $stock,
        ]);
    }

    // ---- Stock In ----

    public function test_stock_in_increases_quantity_and_records_transaction(): void
    {
        $product = $this->makeProduct(5);

        app(InventoryService::class)->stockIn($product, 3, 'নতুন মাল');

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'stock_in',
            'quantity_change' => 3,
            'stock_before' => 5,
            'stock_after' => 8,
        ]);
    }

    // ---- Stock Out ----

    public function test_stock_out_decreases_quantity_and_records_transaction(): void
    {
        $product = $this->makeProduct(10);

        app(InventoryService::class)->stockOut($product, 4, 'বিক্রয়');

        $this->assertSame(6, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'stock_out',
            'quantity_change' => -4,
            'stock_before' => 10,
            'stock_after' => 6,
        ]);
    }

    // ---- Multiple adjustments ----

    public function test_multiple_adjustments_all_recorded(): void
    {
        $product = $this->makeProduct(5);
        $service = app(InventoryService::class);

        $service->stockIn($product, 3, 'প্রাথমিক স্টক');
        $service->stockOut($product, 2, 'ডেমো বিক্রয়');

        $this->assertSame(6, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    // ---- Negative stock guard ----

    public function test_negative_stock_is_rejected(): void
    {
        $product = $this->makeProduct(1);

        $this->expectException(ValidationException::class);
        app(InventoryService::class)->stockOut($product, 2);
    }

    public function test_exact_stock_depletion_is_allowed(): void
    {
        $product = $this->makeProduct(3);

        app(InventoryService::class)->stockOut($product, 3, 'পুরো স্টক বিক্রয়');

        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    // ---- Reason / user attribution ----

    public function test_adjustment_records_reason(): void
    {
        $product = $this->makeProduct(10);

        app(InventoryService::class)->stockIn($product, 1, 'টেস্ট কারণ');

        $this->assertDatabaseHas('inventory_transactions', ['reason' => 'টেস্ট কারণ']);
    }

    public function test_adjustment_records_user_id_when_provided(): void
    {
        $product = $this->makeProduct(10);
        $user = User::factory()->create(['role' => 'admin']);

        app(InventoryService::class)->stockIn($product, 1, 'ব্যবহারকারী সহ', $user->id);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'user_id' => $user->id,
        ]);
    }

    // ---- Low stock query ----

    public function test_low_stock_products_returns_products_at_or_below_threshold(): void
    {
        $category = Category::factory()->create();

        $low = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 3,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
        $ok = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $result = app(InventoryService::class)->lowStockProducts();

        $this->assertTrue($result->contains($low));
        $this->assertFalse($result->contains($ok));
    }
}
