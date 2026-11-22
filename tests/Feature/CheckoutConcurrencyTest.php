<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CheckoutConcurrencyTest extends TestCase
{
    private ?PDO $server = null;

    private ?string $ownedDatabase = null;

    private array $workerEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('CHECKOUT_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with CHECKOUT_MYSQL_TESTS=1 to create an isolated, temporary MySQL database.');
        }

        $this->assertTrue(app()->environment('testing'));
        $mysql = config('database.connections.mysql');
        $this->assertContains($mysql['host'], ['127.0.0.1', 'localhost', '::1'], 'Concurrency tests only use local MySQL.');
        $dsn = $mysql['unix_socket']
            ? 'mysql:unix_socket='.$mysql['unix_socket']
            : 'mysql:host='.$mysql['host'].';port='.$mysql['port'];
        $this->server = new PDO($dsn, $mysql['username'], $mysql['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database = 'best_choice_checkout_test_'.bin2hex(random_bytes(8));
        $this->server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->ownedDatabase = $database;

        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]));
        $this->workerEnvironment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
            'DB_URL' => '', 'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'],
            'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => $mysql['password'],
            'DB_SOCKET' => $mysql['unix_socket'], 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null',
        ];
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ownedDatabase !== null && preg_match('/^best_choice_checkout_test_[a-f0-9]{16}$/', $this->ownedDatabase)) {
                DB::purge('mysql');
                $this->server->exec('DROP DATABASE `'.$this->ownedDatabase.'`');
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_simultaneous_retries_create_one_order_and_one_stock_deduction(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2, 'selling_price' => 490, 'regular_price' => 650]);
        $token = (string) Str::uuid();
        $results = $this->race($product, [$token, $token]);

        $this->assertSame(['accepted', 'accepted'], array_column($results, 'result'));
        $this->assertSame($results[0]['order_id'], $results[1]['order_id']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame('550.00', Order::firstOrFail()->total);
    }

    public function test_simultaneous_distinct_attempts_cannot_oversell_the_last_unit(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 1, 'selling_price' => 490, 'regular_price' => 650]);
        $results = $this->race($product, [(string) Str::uuid(), (string) Str::uuid()]);
        $outcomes = array_column($results, 'result');
        sort($outcomes);

        $this->assertSame(['accepted', 'rejected'], $outcomes);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_distinct_variant_checkouts_cannot_oversell_the_last_unit(): void
    {
        [$product, $variant] = $this->stockFixture(true, 1);

        $results = $this->race($product, [(string) Str::uuid(), (string) Str::uuid()], $variant);
        $outcomes = array_column($results, 'result');
        sort($outcomes);

        $this->assertSame(['accepted', 'rejected'], $outcomes);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertCheckoutDeduction($product, $variant);
    }

    public function test_duplicate_variant_attempts_return_one_order_and_deduct_once(): void
    {
        [$product, $variant] = $this->stockFixture(true, 1);
        $token = (string) Str::uuid();

        $results = $this->race($product, [$token, $token], $variant);

        $this->assertSame(['accepted', 'accepted'], array_column($results, 'result'));
        $this->assertSame($results[0]['order_id'], $results[1]['order_id']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertSame('550.00', Order::firstOrFail()->total);
        $this->assertCheckoutDeduction($product, $variant);
    }

    #[DataProvider('stockOwners')]
    public function test_concurrent_manual_deltas_preserve_both_updates_and_a_continuous_ledger(bool $withVariant): void
    {
        [$product, $variant] = $this->stockFixture($withVariant, 5);
        $admin = User::factory()->create(['role' => 'admin']);
        $actions = [
            ['action' => 'inventory', 'quantity' => 2, 'operation_key' => (string) Str::uuid(), 'user_id' => $admin->id],
            ['action' => 'inventory', 'quantity' => 3, 'operation_key' => (string) Str::uuid(), 'user_id' => $admin->id],
        ];

        $results = $this->race($product, [null, null], $variant, $actions);

        $this->assertSame(['accepted', 'accepted'], array_column($results, 'result'));
        $this->assertSame(0, ($variant ?? $product)->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseCount('orders', 0);
        $entries = InventoryTransaction::orderBy('id')->get();
        $this->assertSame(5, (int) $entries[0]->stock_before);
        $this->assertSame((int) $entries[0]->stock_after, (int) $entries[1]->stock_before);
        $this->assertSame(0, (int) $entries[1]->stock_after);
        foreach ($actions as $action) {
            $this->assertDatabaseHas('inventory_transactions', [
                'product_id' => $product->id, 'variant_id' => $variant?->id,
                'operation_key' => $action['operation_key'], 'type' => 'stock_out',
                'quantity_change' => -$action['quantity'], 'user_id' => $admin->id,
                'reason' => 'Concurrency inventory',
            ]);
        }
        if ($variant) {
            $this->assertSame(0, $product->fresh()->stock_quantity);
        }
    }

    #[DataProvider('stockOwners')]
    public function test_concurrent_manual_operation_retries_deduct_the_last_unit_once(bool $withVariant): void
    {
        [$product, $variant] = $this->stockFixture($withVariant, 1);
        $action = ['action' => 'inventory', 'quantity' => 1, 'operation_key' => (string) Str::uuid()];

        $results = $this->race($product, [null, null], $variant, [$action, $action]);

        $this->assertSame(['accepted', 'accepted'], array_column($results, 'result'));
        $this->assertSame($results[0]['transaction_id'], $results[1]['transaction_id']);
        $this->assertSame(0, ($variant ?? $product)->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $results[0]['transaction_id'], 'product_id' => $product->id,
            'variant_id' => $variant?->id, 'operation_key' => $action['operation_key'],
            'type' => 'stock_out', 'quantity_change' => -1, 'stock_before' => 1, 'stock_after' => 0,
        ]);
        if ($variant) {
            $this->assertSame(0, $product->fresh()->stock_quantity);
        }
    }

    #[DataProvider('stockOwners')]
    public function test_concurrent_cancellations_restore_verified_stock_exactly_once(bool $withVariant): void
    {
        [$product, $variant, $order, $admin] = $this->lifecycleFixture($withVariant);
        $item = $order->items()->firstOrFail();
        $amounts = $order->only(['subtotal', 'shipping_charge', 'total']);
        $action = ['action' => 'cancel', 'order_id' => $order->id, 'user_id' => $admin->id];

        $results = $this->race($product, [null, null], $variant, [$action, $action]);

        $this->assertSame(['accepted', 'accepted'], array_column($results, 'result'));
        $this->assertSame([$order->id, $order->id], array_column($results, 'order_id'));
        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame($amounts, $order->only(array_keys($amounts)));
        $this->assertSame(1, ($variant ?? $product)->fresh()->stock_quantity);
        $this->assertSame(1, (int) $item->refresh()->stock_deducted_quantity);
        $this->assertSame(1, (int) $item->stock_restored_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseCount('order_status_histories', 1);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'variant_id' => $variant?->id,
            'operation_key' => 'order-item:'.$item->id.':cancel', 'reference_type' => 'order_item',
            'reference_id' => $item->id, 'quantity_change' => 1, 'stock_before' => 0,
            'stock_after' => 1, 'type' => 'stock_in', 'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'from_status' => 'pending', 'to_status' => 'cancelled', 'user_id' => $admin->id,
        ]);
        if ($variant) {
            $this->assertSame(0, $product->fresh()->stock_quantity);
        }
    }

    #[DataProvider('stockOwners')]
    public function test_concurrent_return_restock_restores_received_stock_exactly_once(bool $withVariant): void
    {
        [$product, $variant, $order, $admin] = $this->lifecycleFixture($withVariant);
        $service = app(OrderLifecycleService::class);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $service->transition($order, $status, null, $admin);
        }
        $item = $order->items()->firstOrFail();
        $return = $service->requestReturn($order, $item, 1, 'Wrong size', (string) Str::uuid(), $admin);
        $service->transitionReturn($return, 'approved', null, $admin);
        $service->transitionReturn($return, 'received', null, $admin);
        $action = ['action' => 'restock', 'return_id' => $return->id, 'user_id' => $admin->id];

        $results = $this->race($product, [null, null], $variant, [$action, $action]);

        $this->assertSame(['accepted', 'accepted'], array_column($results, 'result'));
        $this->assertSame([$return->id, $return->id], array_column($results, 'return_id'));
        $this->assertSame('restocked', $return->refresh()->status);
        $this->assertSame(1, $return->restocked_quantity);
        $this->assertSame(1, (int) $item->refresh()->stock_restored_quantity);
        $this->assertSame(1, ($variant ?? $product)->fresh()->stock_quantity);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseCount('order_returns', 1);
        $this->assertDatabaseCount('order_status_histories', 7);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'variant_id' => $variant?->id,
            'operation_key' => 'return:'.$return->id.':restock', 'reference_type' => 'order_return',
            'reference_id' => $return->id, 'quantity_change' => 1, 'stock_before' => 0,
            'stock_after' => 1, 'type' => 'stock_in', 'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'from_status' => 'return:'.$return->id.':received',
            'to_status' => 'return:'.$return->id.':restocked', 'user_id' => $admin->id,
        ]);
        if ($variant) {
            $this->assertSame(0, $product->fresh()->stock_quantity);
        }
    }

    public static function stockOwners(): array
    {
        return ['product' => [false], 'variant' => [true]];
    }

    private function stockFixture(bool $withVariant, int $stock): array
    {
        $product = Product::factory()->create([
            'stock_quantity' => $withVariant ? 0 : $stock, 'selling_price' => 490, 'regular_price' => 650,
        ]);
        $variant = $withVariant ? ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Medium green', 'size' => 'M', 'color' => 'Green',
            'sku' => (string) Str::uuid(), 'stock_quantity' => $stock, 'is_active' => true,
        ]) : null;

        return [$product, $variant];
    }

    private function lifecycleFixture(bool $withVariant): array
    {
        [$product, $variant] = $this->stockFixture($withVariant, 1);
        $order = app(CheckoutService::class)->place([
            'customer_name' => 'Concurrency Test', 'customer_phone' => '01700000000',
            'customer_address' => 'Test address', 'payment_method' => 'cash_on_delivery',
        ], [['product_id' => $product->id, 'price' => 490, 'qty' => 1,
            ...($variant ? ['variant_id' => $variant->id] : []),
        ]], (string) Str::uuid(), null);
        $this->assertCheckoutDeduction($product, $variant);

        return [$product, $variant, $order, User::factory()->create(['role' => 'admin'])];
    }

    private function assertCheckoutDeduction(Product $product, ?ProductVariant $variant): void
    {
        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertSame(1, (int) $item->stock_deducted_quantity);
        $this->assertSame(0, (int) $item->stock_restored_quantity);
        $this->assertSame($variant?->id, $item->variant_id);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id, 'variant_id' => $variant?->id,
            'operation_key' => 'order-item:'.$item->id.':deduct', 'reference_type' => 'order_item',
            'reference_id' => $item->id, 'type' => 'stock_out', 'quantity_change' => -1,
            'stock_before' => 1, 'stock_after' => 0,
        ]);
    }

    private function race(Product $product, array $tokens, ?ProductVariant $variant = null, array $actions = []): array
    {
        $barrier = sys_get_temp_dir().'/checkout-race-'.Str::uuid();
        $processes = [];

        try {
            foreach ($tokens as $index => $token) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/checkout-worker.php')], base_path(), $this->workerEnvironment);
                $process->setTimeout(20);
                $process->setInput(json_encode([
                    'token' => $token, 'barrier' => $barrier, 'index' => $index,
                    'product_id' => $product->id, 'variant_id' => $variant?->id,
                    'items' => [$product->id => ['product_id' => $product->id, 'price' => 490, 'qty' => 1,
                        ...($variant ? ['variant_id' => $variant->id] : []),
                    ]],
                    'customer' => ['customer_name' => 'Concurrency Test', 'customer_phone' => '01700000000', 'customer_address' => 'Test address', 'payment_method' => 'cash_on_delivery'],
                    ...($actions[$index] ?? []),
                ], JSON_THROW_ON_ERROR));
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 10;
            while ((! is_file($barrier.'.0') || ! is_file($barrier.'.1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($barrier.'.0');
            $this->assertFileExists($barrier.'.1');
            touch($barrier);

            return array_map(function (Process $process): array {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

                return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }, $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach ([$barrier, $barrier.'.0', $barrier.'.1'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
