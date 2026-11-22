<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderLifecycleService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Explicitly isolated fixtures: no application database or migrations are used.
        config(['database.connections.order_lifecycle_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('order_lifecycle_test');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, password TEXT,
                role TEXT, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT, sku TEXT,
                selling_price NUMERIC, regular_price NUMERIC, stock_quantity INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1, main_image TEXT, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE product_variants (
                id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id),
                name TEXT, sku TEXT, size TEXT, color TEXT, options TEXT, combination_key TEXT,
                regular_price NUMERIC, price_override NUMERIC, stock_quantity INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT, order_number TEXT UNIQUE, checkout_token TEXT UNIQUE,
                user_id INTEGER REFERENCES users(id), customer_name TEXT, customer_phone TEXT,
                customer_additional_phone TEXT, customer_address TEXT, customer_district TEXT,
                subtotal NUMERIC, shipping_charge NUMERIC, total NUMERIC, status TEXT,
                payment_method TEXT, note TEXT, cancellation_reason TEXT, cancelled_at TEXT,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER REFERENCES orders(id) ON DELETE RESTRICT,
                product_id INTEGER REFERENCES products(id) ON DELETE RESTRICT,
                variant_id INTEGER REFERENCES product_variants(id) ON DELETE RESTRICT,
                product_name TEXT, product_sku TEXT, variant_name TEXT, quantity INTEGER,
                unit_price NUMERIC, subtotal NUMERIC, stock_deducted_quantity INTEGER DEFAULT 0,
                stock_restored_quantity INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE inventory_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id),
                variant_id INTEGER REFERENCES product_variants(id), type TEXT, quantity_change INTEGER,
                stock_before INTEGER, stock_after INTEGER, reason TEXT CHECK (length(reason) <= 255), reference_type TEXT,
                reference_id INTEGER, user_id INTEGER REFERENCES users(id), operation_key TEXT UNIQUE,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE order_status_histories (
                id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER REFERENCES orders(id) ON DELETE RESTRICT,
                from_status TEXT, to_status TEXT, reason TEXT, user_id INTEGER REFERENCES users(id),
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE order_returns (
                id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER REFERENCES orders(id) ON DELETE RESTRICT,
                order_item_id INTEGER REFERENCES order_items(id) ON DELETE RESTRICT, quantity INTEGER,
                status TEXT DEFAULT 'requested', reason TEXT, disposition_note TEXT,
                restocked_quantity INTEGER DEFAULT 0, requested_by INTEGER REFERENCES users(id),
                processed_by INTEGER REFERENCES users(id), operation_key TEXT UNIQUE,
                created_at TEXT, updated_at TEXT
            );
            SQL);
    }

    protected function tearDown(): void
    {
        DB::purge('order_lifecycle_test');
        parent::tearDown();
    }

    public function test_cancellation_restores_verified_stock_once_and_preserves_amounts(): void
    {
        $this->freezeTime();
        [$order, $item, $product, $admin] = $this->checkout();
        $service = app(OrderLifecycleService::class);
        $amounts = $order->only(['subtotal', 'shipping_charge', 'total', 'payment_method']);

        $cancelled = $service->transition($order, 'cancelled', 'Customer cancelled', $admin);
        $service->transition($order, 'cancelled', null, $admin);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame($amounts, $order->refresh()->only(array_keys($amounts)));
        $this->assertSame(10, (int) $product->refresh()->stock_quantity);
        $this->assertSame(3, $item->refresh()->stock_restored_quantity);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'cancellation_reason' => 'Customer cancelled', 'cancelled_at' => now()->toDateTimeString()]);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseHas('inventory_transactions', ['operation_key' => 'order-item:'.$item->id.':cancel', 'reference_type' => 'order_item', 'reference_id' => $item->id, 'quantity_change' => 3, 'user_id' => $admin->id]);
        $this->assertDatabaseCount('order_status_histories', 1);
        $this->assertDatabaseHas('order_status_histories', ['from_status' => 'pending', 'to_status' => 'cancelled', 'user_id' => $admin->id]);
    }

    public function test_order_progression_has_history_and_delivered_is_terminal(): void
    {
        [$order, , $product, $admin] = $this->checkout();
        $this->deliver($order, $admin);
        app(OrderLifecycleService::class)->transition($order, 'delivered', null, $admin);

        $this->invalid(fn () => app(OrderLifecycleService::class)->transition($order, 'cancelled', 'Too late', $admin), 'status');

        $this->assertSame('delivered', $order->refresh()->status);
        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('order_status_histories', 3);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    #[DataProvider('invalidOrderTransitions')]
    public function test_invalid_order_transition_or_missing_reason_makes_no_changes(string $status, ?string $reason, string $field): void
    {
        [$order, , $product, $admin] = $this->checkout();

        $this->invalid(fn () => app(OrderLifecycleService::class)->transition($order, $status, $reason, $admin), $field);

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public static function invalidOrderTransitions(): array
    {
        return ['skip processing' => ['shipped', null, 'status'], 'unknown' => ['invalid', null, 'status'], 'no reason' => ['cancelled', null, 'reason'], 'blank reason' => ['cancelled', '  ', 'reason']];
    }

    #[DataProvider('unprovenDeductions')]
    public function test_cancellation_blocks_unproven_or_inconsistent_deductions(string $change): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        match ($change) {
            'legacy' => $item->update(['stock_deducted_quantity' => 0]),
            'counter' => $item->update(['stock_restored_quantity' => 4]),
            'key' => InventoryTransaction::query()->update(['operation_key' => null]),
            'reference' => InventoryTransaction::query()->update(['reference_type' => null, 'reference_id' => null]),
            'quantity' => InventoryTransaction::query()->update(['quantity_change' => -2]),
        };

        $this->invalid(fn () => app(OrderLifecycleService::class)->transition($order, 'cancelled', 'Cancel', $admin), 'stock');

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public static function unprovenDeductions(): array
    {
        return ['legacy' => ['legacy'], 'invalid counter' => ['counter'], 'missing key' => ['key'], 'free text only' => ['reference'], 'wrong delta' => ['quantity']];
    }

    public function test_cancellation_skips_already_restored_stock_and_restores_only_remaining(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        app(InventoryService::class)->stockIn($product, 1, 'Prior restoration', $admin->id);
        $item->update(['stock_restored_quantity' => 1]);

        app(OrderLifecycleService::class)->transition($order, 'cancelled', 'Cancel remainder', $admin);

        $this->assertSame(10, (int) $product->refresh()->stock_quantity);
        $this->assertSame(3, $item->refresh()->stock_restored_quantity);
        $this->assertDatabaseHas('inventory_transactions', ['operation_key' => 'order-item:'.$item->id.':cancel', 'quantity_change' => 2]);
    }

    public function test_fully_restored_cancellation_does_not_create_stock_movement(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        app(InventoryService::class)->stockIn($product, 3, 'Prior restoration', $admin->id);
        $item->update(['stock_restored_quantity' => 3]);

        app(OrderLifecycleService::class)->transition($order, 'cancelled', 'Already restored', $admin);

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(10, (int) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_all_service_boundaries_forbid_non_admin_even_for_noops_and_replays(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $return = $service->requestReturn($order, $item, 1, 'Wrong size', (string) Str::uuid(), $admin);
        $customer = User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => 'password', 'role' => 'customer']);

        foreach ([
            fn () => $service->transition($order, 'delivered', null, $customer),
            fn () => $service->requestReturn($order, $item, 1, 'Wrong size', $return->operation_key, $customer),
            fn () => $service->transitionReturn($return, 'requested', null, $customer),
        ] as $action) {
            try {
                $action();
                $this->fail('Expected the service to forbid a non-admin actor.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('order_returns', 1);
        $this->assertDatabaseCount('order_status_histories', 4);
    }

    public function test_partial_returns_reserve_completed_quantities_and_rejection_releases_reservation(): void
    {
        [$order, $item, , $admin] = $this->checkout();
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $first = $service->requestReturn($order, $item, 2, 'Wrong size', (string) Str::uuid(), $admin);
        $this->invalid(fn () => $service->requestReturn($order, $item, 2, 'Another', (string) Str::uuid(), $admin), 'quantity');
        $service->transitionReturn($first, 'rejected', 'Request withdrawn', $admin);
        $second = $service->requestReturn($order, $item, 3, 'Damaged', (string) Str::uuid(), $admin);
        $service->transitionReturn($second, 'approved', null, $admin);
        $service->transitionReturn($second, 'received', null, $admin);
        $service->transitionReturn($second, 'completed', 'Disposed: unsafe to sell', $admin);

        $this->invalid(fn () => $service->requestReturn($order, $item, 1, 'Again', (string) Str::uuid(), $admin), 'quantity');

        $this->assertDatabaseCount('order_returns', 2);
        $this->assertSame(0, $item->refresh()->stock_restored_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    #[DataProvider('changedReturnPayloads')]
    public function test_return_request_retries_are_bound_to_the_original_payload(string $change): void
    {
        [$order, $item, , $admin] = $this->checkout();
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $return = $service->requestReturn($order, $item, 1, 'Wrong size', (string) Str::uuid(), $admin);
        $this->assertSame($return->id, $service->requestReturn($order, $item, 1, 'Wrong size', $return->operation_key, $admin)->id);
        $otherOrder = $order;
        $otherItem = $item;
        $otherActor = $admin;

        if ($change === 'order' || $change === 'item') {
            [$createdOrder, $createdItem] = $this->checkout();
            $otherOrder = $change === 'order' ? $createdOrder : $order;
            $otherItem = $createdItem;
        }
        if ($change === 'actor') {
            $otherActor = User::create(['name' => 'Other admin', 'email' => 'other@example.test', 'password' => 'password', 'role' => 'admin']);
        }

        $this->invalid(fn () => $service->requestReturn($otherOrder, $otherItem, $change === 'quantity' ? 2 : 1, $change === 'reason' ? 'Changed' : 'Wrong size', $return->operation_key, $otherActor), 'operation_key');

        $this->assertDatabaseCount('order_returns', 1);
        $this->assertSame(1, OrderStatusHistory::query()->where('to_status', 'return:'.$return->id.':requested')->count());
    }

    public static function changedReturnPayloads(): array
    {
        return ['quantity' => ['quantity'], 'reason' => ['reason'], 'order' => ['order'], 'item' => ['item'], 'actor' => ['actor']];
    }

    public function test_returns_require_delivered_order_valid_uuid_positive_quantity_and_own_item(): void
    {
        [$order, $item, , $admin] = $this->checkout();
        $service = app(OrderLifecycleService::class);
        $this->invalid(fn () => $service->requestReturn($order, $item, 1, 'Reason', (string) Str::uuid(), $admin), 'order');
        $this->deliver($order, $admin);
        $this->invalid(fn () => $service->requestReturn($order, $item, 0, 'Reason', (string) Str::uuid(), $admin), 'quantity');
        $this->invalid(fn () => $service->requestReturn($order, $item, 1, 'Reason', 'invalid', $admin), 'operation_key');
        $this->invalid(fn () => $service->requestReturn($order, $item, 1, '  ', (string) Str::uuid(), $admin), 'reason');
        [, $otherItem] = $this->checkout();
        $this->invalid(fn () => $service->requestReturn($order, $otherItem, 1, 'Reason', (string) Str::uuid(), $admin), 'order_item_id');

        $this->assertDatabaseCount('order_returns', 0);
    }

    public function test_return_restock_requires_receipt_and_inspection_and_is_audited_once_per_stage(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        $amounts = $order->only(['subtotal', 'shipping_charge', 'total']);
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $return = $service->requestReturn($order, $item, 2, 'Wrong size', (string) Str::uuid(), $admin);
        $this->invalid(fn () => $service->transitionReturn($return, 'restocked', 'Sellable', $admin), 'status');
        $service->transitionReturn($return, 'approved', 'Return authorized', $admin);
        $this->invalid(fn () => $service->transitionReturn($return, 'restocked', 'Sellable', $admin), 'status');
        $service->transitionReturn($return, 'received', 'Package received', $admin);
        $this->invalid(fn () => $service->transitionReturn($return, 'restocked', '  ', $admin), 'note');
        $service->transitionReturn($return, 'restocked', 'Inspection approved: sellable', $admin);
        $service->transitionReturn($return, 'restocked', null, $admin);
        $service->transitionReturn($return, 'completed', null, $admin);
        $service->transitionReturn($return, 'completed', null, $admin);
        $this->invalid(fn () => $service->transitionReturn($return, 'restocked', 'Again', $admin), 'status');

        $this->assertSame(9, (int) $product->refresh()->stock_quantity);
        $this->assertSame(2, $item->refresh()->stock_restored_quantity);
        $this->assertSame(2, $return->refresh()->restocked_quantity);
        $this->assertSame($admin->id, $return->processedBy->id);
        $this->assertSame($admin->id, $return->requestedBy->id);
        $this->assertSame($item->id, $return->orderItem->id);
        $this->assertSame('Inspection approved: sellable', $return->disposition_note);
        $this->assertSame('delivered', $order->refresh()->status);
        $this->assertSame($amounts, $order->only(array_keys($amounts)));
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseHas('inventory_transactions', ['operation_key' => 'return:'.$return->id.':restock', 'reference_type' => 'order_return', 'reference_id' => $return->id, 'quantity_change' => 2]);
        $this->assertDatabaseCount('order_status_histories', 8);
        $this->assertDatabaseHas('order_status_histories', ['from_status' => 'return:'.$return->id.':received', 'to_status' => 'return:'.$return->id.':restocked', 'reason' => 'Inspection approved: sellable', 'user_id' => $admin->id]);
    }

    public function test_non_restock_completion_requires_disposition_and_does_not_restore_stock(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $return = $service->requestReturn($order, $item, 1, 'Damaged', (string) Str::uuid(), $admin);
        $service->transitionReturn($return, 'approved', null, $admin);
        $service->transitionReturn($return, 'received', null, $admin);
        $this->invalid(fn () => $service->transitionReturn($return, 'completed', null, $admin), 'note');
        $service->transitionReturn($return, 'completed', 'Destroyed: not sellable', $admin);

        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertSame(0, $item->refresh()->stock_restored_quantity);
        $this->assertSame(0, $return->refresh()->restocked_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseHas('order_returns', ['id' => $return->id, 'status' => 'completed', 'disposition_note' => 'Destroyed: not sellable']);
    }

    public function test_restock_cannot_exceed_deducted_quantity_or_repeat_a_recorded_restock(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $return = $service->requestReturn($order, $item, 2, 'Reason', (string) Str::uuid(), $admin);
        $service->transitionReturn($return, 'approved', null, $admin);
        $service->transitionReturn($return, 'received', null, $admin);
        $item->update(['stock_restored_quantity' => 2]);
        $this->invalid(fn () => $service->transitionReturn($return, 'restocked', 'Sellable', $admin), 'quantity');
        $item->update(['stock_restored_quantity' => 0]);
        $return->update(['restocked_quantity' => 1]);
        $this->invalid(fn () => $service->transitionReturn($return, 'restocked', 'Sellable', $admin), 'quantity');

        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertSame('received', $return->refresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    #[DataProvider('restorationOperations')]
    public function test_variant_cancellation_and_return_restock_restore_only_the_original_variant(string $operation): void
    {
        [$order, $item, $product, $admin, $variant, $sibling] = $this->checkout(variant: true);
        $service = app(OrderLifecycleService::class);

        if ($operation === 'cancel') {
            $service->transition($order, 'cancelled', 'Cancel variant', $admin);
        } else {
            $this->deliver($order, $admin);
            $return = $service->requestReturn($order, $item, 3, 'Return variant', (string) Str::uuid(), $admin);
            $service->transitionReturn($return, 'approved', null, $admin);
            $service->transitionReturn($return, 'received', null, $admin);
            $service->transitionReturn($return, 'restocked', 'Inspected and sellable', $admin);
        }

        $this->assertSame(0, (int) $product->refresh()->stock_quantity);
        $this->assertSame(10, (int) $variant->refresh()->stock_quantity);
        $this->assertSame(6, (int) $sibling->refresh()->stock_quantity);
        $this->assertSame(3, $item->refresh()->stock_restored_quantity);
        $this->assertDatabaseHas('inventory_transactions', ['type' => 'stock_in', 'variant_id' => $variant->id, 'quantity_change' => 3]);
    }

    public static function restorationOperations(): array
    {
        return ['cancellation' => ['cancel'], 'return' => ['return']];
    }

    public function test_history_failure_rolls_back_cancellation_stock_and_counters(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        OrderStatusHistory::creating(function (): void {
            throw new RuntimeException('Audit write failed');
        });

        try {
            app(OrderLifecycleService::class)->transition($order, 'cancelled', 'Cancel', $admin);
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit write failed', $exception->getMessage());
        } finally {
            OrderStatusHistory::flushEventListeners();
        }

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertSame(0, $item->refresh()->stock_restored_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    #[DataProvider('unprovenReturnDeductions')]
    public function test_return_request_and_restock_require_matching_deduction_evidence(string $stage, string $change): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $return = null;

        if ($stage === 'restock') {
            $return = $service->requestReturn($order, $item, 1, 'Wrong size', (string) Str::uuid(), $admin);
            $service->transitionReturn($return, 'approved', null, $admin);
            $service->transitionReturn($return, 'received', null, $admin);
        }

        match ($change) {
            'counter' => $item->update(['stock_deducted_quantity' => 4]),
            'restored' => $item->update(['stock_restored_quantity' => 4]),
            'negative' => $item->update(['stock_restored_quantity' => -1]),
            'key' => InventoryTransaction::query()->update(['operation_key' => null]),
            'reference' => InventoryTransaction::query()->update(['reference_type' => null, 'reference_id' => null]),
            'quantity' => InventoryTransaction::query()->update(['quantity_change' => -2]),
        };
        $historyCount = OrderStatusHistory::query()->count();

        $this->invalid($return
            ? fn () => $service->transitionReturn($return, 'restocked', 'Inspected: sellable', $admin)
            : fn () => $service->requestReturn($order, $item, 1, 'Wrong size', (string) Str::uuid(), $admin), 'stock');

        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('order_status_histories', $historyCount);
        $this->assertDatabaseCount('order_returns', $stage === 'restock' ? 1 : 0);
        if ($return) {
            $this->assertSame('received', $return->refresh()->status);
            $this->assertSame(0, $return->restocked_quantity);
        }
    }

    public static function unprovenReturnDeductions(): array
    {
        $cases = [];
        foreach (['request', 'restock'] as $stage) {
            foreach (['counter', 'restored', 'negative', 'key', 'reference', 'quantity'] as $change) {
                $cases[$stage.' '.$change] = [$stage, $change];
            }
        }

        return $cases;
    }

    public function test_later_cancellation_stock_failure_rolls_back_earlier_items_and_all_counters(): void
    {
        [$order, $firstItem, $firstProduct, $admin] = $this->checkout();
        $secondProduct = Product::create(['name' => 'Second product', 'sku' => (string) Str::uuid(), 'slug' => (string) Str::uuid(), 'selling_price' => 100, 'regular_price' => 120, 'stock_quantity' => 10, 'is_active' => true]);
        $secondOrder = app(CheckoutService::class)->place([
            'customer_name' => 'Test', 'customer_phone' => '01700000000', 'customer_address' => 'Dhaka', 'payment_method' => 'cash_on_delivery',
        ], [['product_id' => $secondProduct->id, 'qty' => 3, 'price' => 100]], (string) Str::uuid(), null);
        $secondItem = $secondOrder->items()->firstOrFail();
        // Merge checkout-created lines to exercise a later stock failure in one cancellation transaction.
        $secondItem->update(['order_id' => $order->id]);
        $secondProduct->update(['stock_quantity' => 2147483647]);

        $this->invalid(fn () => app(OrderLifecycleService::class)->transition($order, 'cancelled', 'Cancel both', $admin), 'quantity');

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(null, $order->cancellation_reason);
        $this->assertSame(null, $order->cancelled_at);
        $this->assertSame(7, (int) $firstProduct->refresh()->stock_quantity);
        $this->assertSame(2147483647, (int) $secondProduct->refresh()->stock_quantity);
        $this->assertSame(0, $firstItem->refresh()->stock_restored_quantity);
        $this->assertSame(0, $secondItem->refresh()->stock_restored_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_return_stock_ledger_failure_rolls_back_stock_return_counter_and_history(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        $this->deliver($order, $admin);
        $service = app(OrderLifecycleService::class);
        $return = $service->requestReturn($order, $item, 1, 'Wrong size', (string) Str::uuid(), $admin);
        $service->transitionReturn($return, 'approved', null, $admin);
        $service->transitionReturn($return, 'received', 'Received package', $admin);
        $historyCount = OrderStatusHistory::query()->count();
        InventoryTransaction::created(function (InventoryTransaction $transaction): void {
            if ($transaction->type === 'stock_in') {
                throw new RuntimeException('Stock ledger failed after writing');
            }
        });

        try {
            $service->transitionReturn($return, 'restocked', 'Inspection approved: sellable', $admin);
            $this->fail('Expected stock ledger failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Stock ledger failed after writing', $exception->getMessage());
        } finally {
            InventoryTransaction::flushEventListeners();
        }

        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertSame(0, $item->refresh()->stock_restored_quantity);
        $this->assertSame('received', $return->refresh()->status);
        $this->assertSame(0, $return->restocked_quantity);
        $this->assertSame('Received package', $return->disposition_note);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('order_status_histories', $historyCount);

        $service->transitionReturn($return, 'restocked', 'Inspection approved: sellable', $admin);
        $this->assertSame(8, (int) $product->refresh()->stock_quantity);
        $this->assertSame(1, $item->refresh()->stock_restored_quantity);
        $this->assertSame(1, $return->refresh()->restocked_quantity);
    }

    #[DataProvider('restorationOperations')]
    public function test_long_multibyte_reasons_are_preserved_in_audit_but_truncated_in_inventory(string $operation): void
    {
        [$order, $item, , $admin] = $this->checkout();
        $text = str_repeat('ক', 1000);
        $service = app(OrderLifecycleService::class);

        if ($operation === 'cancel') {
            $service->transition($order, 'cancelled', $text, $admin);
            $this->assertSame($text, $order->refresh()->cancellation_reason);
            $historyStatus = 'cancelled';
        } else {
            $this->deliver($order, $admin);
            $return = $service->requestReturn($order, $item, 1, $text, (string) Str::uuid(), $admin);
            $service->transitionReturn($return, 'approved', null, $admin);
            $service->transitionReturn($return, 'received', null, $admin);
            $service->transitionReturn($return, 'restocked', $text, $admin);
            $this->assertSame($text, $return->refresh()->disposition_note);
            $this->assertSame($text, $return->reason);
            $historyStatus = 'return:'.$return->id.':restocked';
        }

        $this->assertSame(str_repeat('ক', 255), InventoryTransaction::query()->where('type', 'stock_in')->firstOrFail()->reason);
        $this->assertDatabaseHas('order_status_histories', ['to_status' => $historyStatus, 'reason' => $text, 'user_id' => $admin->id]);
    }

    public function test_empty_order_cannot_be_cancelled_without_inventory_reconciliation(): void
    {
        [$order, $item, $product, $admin] = $this->checkout();
        // The ledger retains the evidence, but this simulates missing legacy order lines.
        $item->delete();

        $this->invalid(fn () => app(OrderLifecycleService::class)->transition($order, 'cancelled', 'Cancel empty order', $admin), 'stock');

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(7, (int) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    /** @return array{Order, OrderItem, Product, User, ?ProductVariant, ?ProductVariant} */
    private function checkout(bool $variant = false): array
    {
        $product = Product::create(['name' => 'Lifecycle product', 'slug' => (string) Str::uuid(), 'sku' => (string) Str::uuid(), 'selling_price' => 100, 'regular_price' => 120, 'stock_quantity' => $variant ? 0 : 10, 'is_active' => true]);
        $selected = $sibling = null;
        if ($variant) {
            $selected = ProductVariant::create(['product_id' => $product->id, 'name' => 'Small', 'size' => 'S', 'sku' => (string) Str::uuid(), 'stock_quantity' => 10, 'is_active' => true]);
            $sibling = ProductVariant::create(['product_id' => $product->id, 'name' => 'Large', 'size' => 'L', 'sku' => (string) Str::uuid(), 'stock_quantity' => 6, 'is_active' => true]);
        }

        $order = app(CheckoutService::class)->place([
            'customer_name' => 'Lifecycle customer', 'customer_phone' => '01700000000',
            'customer_address' => 'Dhaka', 'payment_method' => 'cash_on_delivery',
        ], [['product_id' => $product->id, 'qty' => 3, 'price' => 100, ...($selected ? ['variant_id' => $selected->id] : [])]], (string) Str::uuid(), null);
        $admin = User::create(['name' => 'Admin', 'email' => Str::uuid().'@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($admin);

        return [$order, $order->items()->firstOrFail(), $product, $admin, $selected, $sibling];
    }

    private function deliver(Order $order, User $admin): void
    {
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            app(OrderLifecycleService::class)->transition($order, $status, null, $admin);
        }
    }

    private function invalid(Closure $action, string $field): void
    {
        try {
            $action();
            $this->fail('Expected validation failure for '.$field);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
