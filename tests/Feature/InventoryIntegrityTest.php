<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InventoryIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Own the schema on a separate connection; never run application migrations.
        config([
            'database.default' => 'inventory_integrity',
            'database.connections.inventory_integrity' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('inventory_integrity');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->decimal('selling_price')->default(100);
            $table->decimal('regular_price')->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->string('operation_key')->nullable()->unique();
            $table->string('type');
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        try {
            DB::purge('inventory_integrity');
        } finally {
            parent::tearDown();
        }
    }

    public function test_original_positional_arguments_and_reference_metadata_are_preserved(): void
    {
        $product = $this->product(10);
        $service = app(InventoryService::class);

        $service->stockIn($product, 3, 'Receipt', 7, null, 'receipt-1', 'purchase', 42);
        $service->stockOut($product, 2, 'Sale', 8, null, 'sale-1', 'order', 43);
        $entry = $service->adjust($product, -1, 'adjustment', 'Count', 9);

        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertSame(-1, $entry->quantity_change);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertDatabaseHas('inventory_transactions', ['operation_key' => 'receipt-1', 'quantity_change' => 3, 'stock_before' => 10, 'stock_after' => 13, 'reason' => 'Receipt', 'user_id' => 7, 'reference_type' => 'purchase', 'reference_id' => 42]);
        $this->assertDatabaseHas('inventory_transactions', ['operation_key' => 'sale-1', 'quantity_change' => -2, 'stock_before' => 13, 'stock_after' => 11, 'reason' => 'Sale', 'user_id' => 8, 'reference_type' => 'order', 'reference_id' => 43]);
        $this->assertDatabaseHas('inventory_transactions', ['id' => $entry->id, 'reason' => 'Count', 'user_id' => 9]);
    }

    public function test_matching_operation_key_returns_original_ledger_after_further_stock_changes(): void
    {
        $product = $this->product(3);
        $service = app(InventoryService::class);
        $original = $service->stockOut($product, 3, 'Original', 7, null, 'order-1', 'order', 42);
        $service->stockIn($product, 1);

        $replay = $service->stockOut($product, 3, 'Changed', 8, null, 'order-1', 'order', 99);

        $this->assertSame($original->id, $replay->id);
        $this->assertSame('Original', $replay->reason);
        $this->assertSame(42, $replay->reference_id);
        $this->assertSame(1, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    #[DataProvider('conflictingOperations')]
    public function test_operation_key_cannot_be_reused_for_a_different_operation(string $difference): void
    {
        $product = $this->product(10);
        $other = $this->product(10);
        $service = app(InventoryService::class);
        $service->adjust($product, 2, 'stock_in', operationKey: 'unique-key');

        $this->assertRejected(fn () => $service->adjust(
            $difference === 'product' ? $other : $product,
            $difference === 'delta' ? 3 : 2,
            $difference === 'type' ? 'adjustment' : 'stock_in',
            operationKey: 'unique-key',
        ), 'operation_key');

        $this->assertSame(12, $product->refresh()->stock_quantity);
        $this->assertSame(10, $other->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public static function conflictingOperations(): array
    {
        return ['different product' => ['product'], 'different delta' => ['delta'], 'different type' => ['type']];
    }

    public function test_variant_stock_and_operation_identity_are_independent_from_siblings(): void
    {
        $product = $this->product(0);
        $first = $this->variant($product, 5);
        $second = $this->variant($product, 9);
        $service = app(InventoryService::class);
        $entry = $service->stockOut($product, 2, variant: $first, operationKey: 'variant-sale', referenceType: 'order', referenceId: 42);
        $replay = $service->stockOut($product, 2, variant: $first, operationKey: 'variant-sale');

        $this->assertRejected(fn () => $service->stockOut($product, 2, variant: $second, operationKey: 'variant-sale'), 'operation_key');

        $this->assertSame($entry->id, $replay->id);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertSame(3, $first->refresh()->stock_quantity);
        $this->assertSame(9, $second->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseHas('inventory_transactions', ['variant_id' => $first->id, 'stock_before' => 5, 'stock_after' => 3, 'reference_type' => 'order', 'reference_id' => 42]);
    }

    public function test_base_and_variant_operations_cannot_share_a_key(): void
    {
        $product = $this->product(1);
        $service = app(InventoryService::class);
        $service->stockOut($product, 1, operationKey: 'base-key');
        $variant = $this->variant($product, 1);

        $this->assertRejected(fn () => $service->stockOut($product, 1, variant: $variant, operationKey: 'base-key'), 'operation_key');

        $this->assertSame(1, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    #[DataProvider('mutationMethods')]
    public function test_variant_must_belong_to_the_locked_product(string $method): void
    {
        $product = $this->product(0);
        $other = $this->product(0);
        $variant = $this->variant($other, 5);
        // A forged in-memory parent must not override the persisted identity.
        $variant->product_id = $product->id;

        $this->assertRejected(fn () => app(InventoryService::class)->$method($product, 2, variant: $variant), 'variant_id');

        $this->assertSame(5, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('mutationMethods')]
    public function test_base_mutations_are_forbidden_when_variants_exist(string $method): void
    {
        $product = $this->product(0);
        $this->variant($product, 5);
        $product->setRelation('variants', collect());

        $this->assertRejected(fn () => app(InventoryService::class)->$method($product, 2), 'variant_id');

        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('mutationMethods')]
    public function test_variant_mutations_require_zero_base_stock(string $method): void
    {
        $product = $this->product(3);
        $variant = $this->variant($product, 5);

        $this->assertRejected(fn () => app(InventoryService::class)->$method($product, 2, variant: $variant), 'stock_quantity');

        $this->assertSame(3, $product->refresh()->stock_quantity);
        $this->assertSame(5, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public static function mutationMethods(): array
    {
        return ['stock in' => ['stockIn'], 'stock out' => ['stockOut'], 'absolute stock' => ['setStock']];
    }

    public function test_variant_product_allows_only_an_unchanged_zero_base_target(): void
    {
        $product = $this->product(0);
        $variant = $this->variant($product, 5);

        $service = app(InventoryService::class);

        $this->assertRejected(fn () => $service->setStock($product, 0, expectedStock: 9), 'stock_quantity');
        $this->assertNull($service->setStock($product, 0, expectedStock: 0));

        $this->assertSame(5, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_direct_adjustment_uses_variant_stock_and_rejects_base_adjustment(): void
    {
        $product = $this->product(0);
        $variant = $this->variant($product, 5);
        $service = app(InventoryService::class);

        $entry = $service->adjust($product, -2, 'adjustment', variant: $variant, referenceType: 'count', referenceId: 42);
        $this->assertRejected(fn () => $service->adjust($product, 1, 'adjustment'), 'variant_id');

        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertSame(3, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseHas('inventory_transactions', ['id' => $entry->id, 'variant_id' => $variant->id, 'quantity_change' => -2, 'reference_type' => 'count', 'reference_id' => 42]);
    }

    public function test_deleted_variant_cannot_be_replaced_by_a_sibling(): void
    {
        $product = $this->product(0);
        $variant = $this->variant($product, 5);
        $sibling = $this->variant($product, 9);
        DB::table('product_variants')->where('id', $variant->id)->delete();

        $this->assertRejected(fn () => app(InventoryService::class)->stockOut($product, 2, variant: $variant), 'variant_id');

        $this->assertSame(9, $sibling->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_variant_bounds_reject_overselling_and_overflow_without_changing_base_stock(): void
    {
        $product = $this->product(0);
        $variant = $this->variant($product, 1);
        $service = app(InventoryService::class);

        $this->assertRejected(fn () => $service->stockOut($product, 2, variant: $variant), 'quantity');
        $service->setStock($product, 2147483647, variant: $variant, expectedStock: 1);
        $this->assertRejected(fn () => $service->stockIn($product, 1, variant: $variant), 'quantity');

        $this->assertSame(2147483647, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_absolute_stock_uses_current_locked_stock_instead_of_a_stale_model(): void
    {
        $product = $this->product(10);
        $service = app(InventoryService::class);
        $service->stockOut($product->fresh(), 3);

        $entry = $service->setStock($product, 9, 'Count', 7, operationKey: 'count-1', expectedStock: 7);

        $this->assertSame(9, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_transactions', ['id' => $entry->id, 'type' => 'adjustment', 'quantity_change' => 2, 'stock_before' => 7, 'stock_after' => 9, 'reason' => 'Count', 'user_id' => 7, 'operation_key' => 'count-1']);
    }

    public function test_stale_form_cannot_overwrite_an_intervening_stock_change(): void
    {
        $product = $this->product(10);
        $staleForm = $product->fresh();
        $service = app(InventoryService::class);
        $service->stockOut($product, 3);

        $this->assertRejected(fn () => $service->setStock($staleForm, 12, expectedStock: 10), 'stock_quantity');

        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertRejected(fn () => $service->setStock($staleForm, 7, expectedStock: 10), 'stock_quantity');
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    #[DataProvider('stockOwners')]
    public function test_stale_noop_is_rejected_and_valid_noop_retry_cannot_deduct_replenished_stock(bool $useVariant): void
    {
        $product = $this->product($useVariant ? 0 : 10);
        $variant = $useVariant ? $this->variant($product, 10) : null;
        $service = app(InventoryService::class);
        $service->stockOut($product, 2, variant: $variant);

        $this->assertRejected(fn () => $service->setStock($product, 8, variant: $variant, operationKey: 'noop-count', expectedStock: 10), 'stock_quantity');
        $this->assertSame(8, ($variant ?? $product)->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseMissing('inventory_transactions', ['operation_key' => 'noop-count']);

        $this->assertNull($service->setStock($product, 8, variant: $variant, operationKey: 'noop-count', expectedStock: 8));
        $this->assertDatabaseCount('inventory_transactions', 1);
        $service->stockIn($product, 2, variant: $variant);

        $this->assertRejected(fn () => $service->setStock($product, 8, variant: $variant, operationKey: 'noop-count', expectedStock: 8), 'stock_quantity');

        $this->assertSame(10, ($variant ?? $product)->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseMissing('inventory_transactions', ['operation_key' => 'noop-count']);
    }

    public static function stockOwners(): array
    {
        return ['base stock' => [false], 'variant stock' => [true]];
    }

    #[DataProvider('keyedRequestsWithoutBaseline')]
    public function test_keyed_stock_target_requires_a_baseline_before_starting_a_transaction(bool $useVariant, int $target): void
    {
        $product = $this->product($useVariant ? 0 : 8);
        $variant = $useVariant ? $this->variant($product, 8) : null;
        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $this->assertRejected(fn () => app(InventoryService::class)->setStock($product, $target, variant: $variant, operationKey: 'missing-baseline'), 'expected_stock');

        $this->assertSame([], $connection->getQueryLog());
        $this->assertSame(8, ($variant ?? $product)->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public static function keyedRequestsWithoutBaseline(): array
    {
        return [
            'base noop' => [false, 8], 'base mutation' => [false, 10],
            'variant noop' => [true, 8], 'variant mutation' => [true, 10],
        ];
    }

    public function test_unkeyed_stock_target_keeps_the_baseline_optional(): void
    {
        $product = $this->product(5);

        $entry = app(InventoryService::class)->setStock($product, 8);

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_transactions', ['id' => $entry->id, 'quantity_change' => 3, 'operation_key' => null]);
    }

    public function test_variant_absolute_stock_honors_baseline_and_leaves_other_stock_untouched(): void
    {
        $product = $this->product(0);
        $variant = $this->variant($product, 5);
        $sibling = $this->variant($product, 9);
        $service = app(InventoryService::class);
        $entry = $service->setStock($product, 8, variant: $variant, expectedStock: 5);

        $this->assertRejected(fn () => $service->setStock($product, 10, variant: $variant, expectedStock: 5), 'stock_quantity');

        $this->assertSame(8, $variant->refresh()->stock_quantity);
        $this->assertSame(9, $sibling->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseHas('inventory_transactions', ['id' => $entry->id, 'variant_id' => $variant->id, 'quantity_change' => 3, 'stock_before' => 5, 'stock_after' => 8]);
    }

    public function test_absolute_stock_replay_does_not_restore_stock_after_a_later_sale(): void
    {
        $product = $this->product(10);
        $service = app(InventoryService::class);
        $original = $service->setStock($product, 15, operationKey: 'count-1', expectedStock: 10);
        $service->stockOut($product, 2);

        $replay = $service->setStock($product, 15, operationKey: 'count-1', expectedStock: 10);
        $this->assertRejected(fn () => $service->setStock($product, 15, operationKey: 'count-1'), 'expected_stock');
        $this->assertRejected(fn () => $service->setStock($product, 16, operationKey: 'count-1', expectedStock: 10), 'operation_key');
        $this->assertRejected(fn () => $service->setStock($product, 15, operationKey: 'count-1', expectedStock: 11), 'operation_key');

        $this->assertSame($original->id, $replay->id);
        $this->assertSame(13, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_noop_target_still_rejects_a_key_for_a_different_operation_type(): void
    {
        $product = $this->product(5);
        $service = app(InventoryService::class);
        $service->stockIn($product, 2, operationKey: 'receipt-key');

        $this->assertRejected(fn () => $service->setStock($product, 7, operationKey: 'receipt-key', expectedStock: 7), 'operation_key');

        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    #[DataProvider('ledgerFailureOperations')]
    public function test_transient_ledger_contention_retries_the_whole_write_up_to_three_attempts(string $method, bool $useVariant): void
    {
        $product = $this->product($useVariant ? 0 : 5);
        $variant = $useVariant ? $this->variant($product, 5) : null;
        $attempts = 0;
        $arguments = ['variant' => $variant, 'operationKey' => 'contended-write'];
        if ($method === 'setStock') {
            $arguments['expectedStock'] = 5;
        }
        DB::connection()->beforeExecuting(function (string $sql) use (&$attempts): void {
            if (str_starts_with($sql, 'insert into "inventory_transactions"') && ++$attempts < 3) {
                throw new RuntimeException('database is locked');
            }
        });

        app(InventoryService::class)->$method($product, 2, ...$arguments);

        $this->assertSame(3, $attempts);
        $this->assertSame($method === 'setStock' ? 2 : 3, ($variant ?? $product)->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    #[DataProvider('invalidAdjustmentTypesAndSigns')]
    public function test_invalid_adjustment_types_and_signs_leave_stock_and_ledger_unchanged(string $type, int $delta, string $field): void
    {
        $product = $this->product(5);

        $this->assertRejected(fn () => app(InventoryService::class)->adjust($product, $delta, $type), $field);

        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public static function invalidAdjustmentTypesAndSigns(): array
    {
        return [
            'unknown type' => ['sale', -1, 'type'],
            'empty type' => ['', 1, 'type'],
            'incorrect type case' => ['STOCK_IN', 1, 'type'],
            'negative stock in' => ['stock_in', -1, 'quantity'],
            'positive stock out' => ['stock_out', 1, 'quantity'],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_never_change_stock_or_ledger(string $method, int $quantity): void
    {
        $product = $this->product(5);
        $service = app(InventoryService::class);

        $this->assertRejected(fn () => $method === 'adjust'
            ? $service->adjust($product, $quantity, 'adjustment')
            : $service->$method($product, $quantity), 'quantity');

        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public static function invalidQuantities(): array
    {
        return [
            'negative receipt' => ['stockIn', -1], 'zero receipt' => ['stockIn', 0],
            'negative issue' => ['stockOut', -1], 'zero issue' => ['stockOut', 0],
            'minimum integer issue' => ['stockOut', PHP_INT_MIN],
            'overselling' => ['stockOut', 6], 'zero delta' => ['adjust', 0],
            'signed ledger overflow' => ['adjust', 2147483648],
            'signed ledger underflow' => ['adjust', -2147483649],
            'negative target' => ['setStock', -1], 'target overflow' => ['setStock', 2147483648],
            'receipt overflow' => ['stockIn', PHP_INT_MAX], 'issue overflow' => ['stockOut', PHP_INT_MAX],
        ];
    }

    public function test_stock_bounds_allow_maximum_and_zero_but_reject_overflow(): void
    {
        $product = $this->product(0);
        $service = app(InventoryService::class);
        $service->stockIn($product, 2147483647);

        $this->assertRejected(fn () => $service->stockIn($product, 1), 'quantity');
        $this->assertSame(2147483647, $product->refresh()->stock_quantity);
        $service->stockOut($product, 2147483647);

        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseHas('inventory_transactions', ['quantity_change' => -2147483647, 'stock_after' => 0]);
    }

    #[DataProvider('ledgerFailureOperations')]
    public function test_ledger_insert_failure_rolls_back_stock_and_does_not_consume_key(string $method, bool $useVariant): void
    {
        $product = $this->product($useVariant ? 0 : 5);
        $variant = $useVariant ? $this->variant($product, 5) : null;
        $service = app(InventoryService::class);
        $arguments = ['variant' => $variant, 'operationKey' => 'retry-after-failure'];
        if ($method === 'setStock') {
            $arguments['expectedStock'] = 5;
        }
        DB::unprepared("CREATE TRIGGER reject_inventory BEFORE INSERT ON inventory_transactions BEGIN SELECT RAISE(ABORT, 'ledger unavailable'); END");

        try {
            $service->$method($product, 2, ...$arguments);
            $this->fail('The ledger write must fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('ledger unavailable', $exception->getMessage());
        }

        $this->assertSame($useVariant ? 0 : 5, $product->refresh()->stock_quantity);
        $this->assertSame(5, ($variant ?? $product)->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);

        DB::unprepared('DROP TRIGGER reject_inventory');
        $service->$method($product, 2, ...$arguments);

        $this->assertSame($method === 'setStock' ? 2 : 3, ($variant ?? $product)->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public static function ledgerFailureOperations(): array
    {
        return ['base delta' => ['stockOut', false], 'variant delta' => ['stockOut', true], 'base target' => ['setStock', false], 'variant target' => ['setStock', true]];
    }

    public function test_low_stock_products_excludes_variant_parents_and_inactive_or_above_threshold_products(): void
    {
        $low = $this->product(1, threshold: 2);
        $atThreshold = $this->product(2, threshold: 2);
        $this->product(3, threshold: 2);
        $this->product(0, active: false);
        $variantParent = $this->product(0);
        $this->variant($variantParent, 0);
        $inactiveVariantParent = $this->product(0);
        $this->variant($inactiveVariantParent, 0, active: false);

        $products = app(InventoryService::class)->lowStockProducts();

        $this->assertSame([$low->id, $atThreshold->id], $products->modelKeys());
    }

    #[DataProvider('lowStockThresholds')]
    public function test_low_stock_variants_uses_each_parent_threshold_inclusively(int $threshold): void
    {
        $product = $this->product(0, threshold: $threshold);
        $atThreshold = $this->variant($product, $threshold);
        $this->variant($product, $threshold + 1);
        $otherParent = $this->product(0, threshold: 20);
        $belowOtherThreshold = $this->variant($otherParent, 10);

        $variants = app(InventoryService::class)->lowStockVariants();

        $this->assertSame([$atThreshold->id, $belowOtherThreshold->id], $variants->modelKeys());
        $this->assertCount(0, app(InventoryService::class)->lowStockProducts());
    }

    public static function lowStockThresholds(): array
    {
        return ['zero threshold' => [0], 'low threshold' => [2], 'higher threshold' => [8]];
    }

    public function test_low_stock_variants_requires_both_variant_and_parent_to_be_active(): void
    {
        $product = $this->product(0);
        $activeVariant = $this->variant($product, 0);
        $this->variant($product, 0, active: false);
        $inactiveParent = $this->product(0, active: false);
        $this->variant($inactiveParent, 0);
        $this->variant($inactiveParent, 0, active: false);

        $variants = app(InventoryService::class)->lowStockVariants();

        $this->assertSame([$activeVariant->id], $variants->modelKeys());
        $this->assertTrue($variants->first()->relationLoaded('product'));
    }

    private function product(int $stock, int $threshold = 5, bool $active = true): Product
    {
        return Product::query()->findOrFail(DB::table('products')->insertGetId(['stock_quantity' => $stock, 'low_stock_threshold' => $threshold, 'is_active' => $active]));
    }

    private function variant(Product $product, int $stock, bool $active = true): ProductVariant
    {
        return ProductVariant::query()->findOrFail(DB::table('product_variants')->insertGetId(['product_id' => $product->id, 'stock_quantity' => $stock, 'is_active' => $active]));
    }

    private function assertRejected(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('The inventory operation must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
