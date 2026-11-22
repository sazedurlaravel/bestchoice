<?php

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || ! preg_match('/^best_choice_checkout_test_[a-f0-9]{16}$/', (string) config('database.connections.mysql.database')) || config('database.default') !== 'mysql') {
    fwrite(STDERR, 'Worker refused a non-isolated database.');
    exit(1);
}

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
touch($input['barrier'].'.'.$input['index']);
$deadline = microtime(true) + 10;
while (! is_file($input['barrier'])) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}

// Hold the transaction open so the other connection competes for the same locks.
Order::created(static function (): void {
    usleep(250000);
});
InventoryTransaction::creating(static function (): void {
    usleep(250000);
});

try {
    // All model reads happen after the barrier releases both connections.
    $result = match ($input['action'] ?? 'checkout') {
        'checkout' => ['order_id' => $app->make(CheckoutService::class)->place(
            $input['customer'], $input['items'], $input['token'], null,
        )->id],
        'inventory' => ['transaction_id' => $app->make(InventoryService::class)->stockOut(
            Product::findOrFail($input['product_id']), $input['quantity'],
            'Concurrency inventory', $input['user_id'] ?? null,
            isset($input['variant_id']) ? ProductVariant::findOrFail($input['variant_id']) : null,
            $input['operation_key'],
        )->id],
        'cancel' => ['order_id' => $app->make(OrderLifecycleService::class)->transition(
            Order::findOrFail($input['order_id']), 'cancelled', 'Concurrency cancellation',
            User::findOrFail($input['user_id']),
        )->id],
        'restock' => ['return_id' => $app->make(OrderLifecycleService::class)->transitionReturn(
            OrderReturn::findOrFail($input['return_id']), 'restocked', 'Inspected and sellable',
            User::findOrFail($input['user_id']),
        )->id],
        default => throw new InvalidArgumentException('Unknown worker action.'),
    };
    echo json_encode(['result' => 'accepted', ...$result], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['result' => 'rejected'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class);
    exit(1);
}
