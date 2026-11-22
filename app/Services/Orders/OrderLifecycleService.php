<?php

namespace App\Services\Orders;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderLifecycleService
{
    private const ORDER_TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    private const RETURN_TRANSITIONS = [
        'requested' => ['approved', 'rejected'],
        'approved' => ['received'],
        'received' => ['restocked', 'completed'],
        'restocked' => ['completed'],
        'rejected' => [],
        'completed' => [],
    ];

    public function __construct(private InventoryService $inventory) {}

    public function transition(Order $order, string $status, ?string $reason, User $actor): Order
    {
        $this->authorize($actor);

        return $this->transitionOrder($order, $status, $reason, $actor);
    }

    public function cancelForPaymentFailure(Order $order, string $reason): Order
    {
        return $this->transitionOrder($order, 'cancelled', $reason, null, true);
    }

    private function transitionOrder(Order $order, string $status, ?string $reason, ?User $actor, bool $paymentFailure = false): Order
    {
        if ($paymentFailure) {
            abort_unless($status === 'cancelled' && $order->payment_method === 'sslcommerz', 403);
        }

        return DB::transaction(function () use ($order, $status, $reason, $actor): Order {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->status === $status) {
                return $order;
            }

            if ($status === 'processing' && $order->payment_method === 'sslcommerz'
                && ! $order->payments()->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages(['payment' => 'Online orders can only be processed after payment is verified.']);
            }

            $this->validateTransition(self::ORDER_TRANSITIONS, $order->status, $status);
            $fromStatus = $order->status;

            if ($status === 'cancelled') {
                $this->requireText($reason, 'reason', 'A cancellation reason is required.');
                $items = OrderItem::query()->where('order_id', $order->id)
                    ->orderBy('product_id')->orderBy('variant_id')->orderBy('id')
                    ->lockForUpdate()->get();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages(['stock' => 'Cannot cancel an order without items. Reconcile the order and its inventory records before retrying.']);
                }

                // Validate every line before restoring any stock; free-text ledger reasons are not evidence.
                foreach ($items as $item) {
                    $this->validateDeduction($item);
                }

                foreach ($items as $item) {
                    $remaining = (int) $item->stock_deducted_quantity - (int) $item->stock_restored_quantity;

                    if ($remaining === 0) {
                        continue;
                    }

                    [$product, $variant] = $this->lockStock($item);
                    $this->inventory->stockIn(
                        $product, $remaining, mb_substr($reason, 0, 255), $actor?->id, $variant,
                        'order-item:'.$item->id.':cancel', 'order_item', $item->id,
                    );
                    $item->stock_restored_quantity = (int) $item->stock_deducted_quantity;
                    $item->save();
                }

                $order->cancellation_reason = $reason;
                $order->cancelled_at = now();
            }

            $order->status = $status;
            $order->save();
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'reason' => $reason,
                'user_id' => $actor?->id,
            ]);

            return $order;
        }, attempts: 3);
    }

    public function requestReturn(Order $order, OrderItem $item, int $qty, string $reason, string $operationKey, User $actor): OrderReturn
    {
        abort_unless($actor->hasPermission('orders.manage') || ((int) $order->user_id === (int) $actor->id), 403);
        Validator::make(['quantity' => $qty, 'reason' => $reason, 'operation_key' => $operationKey], [
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string'],
            'operation_key' => ['required', 'uuid'],
        ])->validate();
        $operationKey = strtolower($operationKey);

        try {
            return DB::transaction(function () use ($order, $item, $qty, $reason, $operationKey, $actor): OrderReturn {
                $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if ($existing = OrderReturn::query()->where('operation_key', $operationKey)->first()) {
                    return $this->validateReturnRetry($existing, $order, $item, $qty, $reason, $actor);
                }

                if ($order->status !== 'delivered') {
                    throw ValidationException::withMessages(['order' => 'Returns may only be requested for delivered orders.']);
                }

                $item = OrderItem::query()->whereKey($item->getKey())->where('order_id', $order->id)
                    ->lockForUpdate()->first();

                if (! $item) {
                    throw ValidationException::withMessages(['order_item_id' => 'The item does not belong to this order.']);
                }

                $this->validateDeduction($item);
                $reserved = (int) OrderReturn::query()->where('order_item_id', $item->id)
                    ->where('status', '!=', 'rejected')->sum('quantity');

                if ($qty > (int) $item->stock_deducted_quantity - $reserved) {
                    throw ValidationException::withMessages(['quantity' => 'The return quantity exceeds the remaining deducted quantity.']);
                }

                $return = OrderReturn::query()->create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'quantity' => $qty,
                    'status' => 'requested',
                    'reason' => $reason,
                    'restocked_quantity' => 0,
                    'requested_by' => $actor->id,
                    'operation_key' => $operationKey,
                ]);
                $this->recordReturnHistory($return, 'none', 'requested', $reason, $actor);

                return $return;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            // The UUID is global, so simultaneous requests for different orders can collide.
            $existing = OrderReturn::query()->where('operation_key', $operationKey)->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->validateReturnRetry($existing, $order, $item, $qty, $reason, $actor);
        }
    }

    public function transitionReturn(OrderReturn $return, string $status, ?string $note, User $actor): OrderReturn
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($return, $status, $note, $actor): OrderReturn {
            // Resolve the persisted parent rather than trusting attributes on a caller's stale model.
            $orderId = OrderReturn::query()->whereKey($return->getKey())->firstOrFail()->order_id;
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $return = OrderReturn::query()->whereKey($return->getKey())->where('order_id', $order->id)
                ->lockForUpdate()->firstOrFail();

            if ($return->status === $status) {
                return $return;
            }

            $this->validateTransition(self::RETURN_TRANSITIONS, $return->status, $status);

            if ($order->status !== 'delivered') {
                throw ValidationException::withMessages(['order' => 'Returns may only be processed for delivered orders.']);
            }

            if ($return->status === 'received') {
                $this->requireText($note, 'note', $status === 'restocked'
                    ? 'Record the approved sellable inspection before restocking.'
                    : 'Record the non-restock disposition before completing this return.');
            }

            if ($status === 'restocked') {
                $item = OrderItem::query()->whereKey($return->order_item_id)->where('order_id', $order->id)
                    ->lockForUpdate()->firstOrFail();
                $this->validateDeduction($item);
                $remaining = (int) $item->stock_deducted_quantity - (int) $item->stock_restored_quantity;

                if ($return->restocked_quantity !== 0 || $return->quantity < 1 || $return->quantity > $remaining) {
                    throw ValidationException::withMessages(['quantity' => 'This return cannot restore more than the original remaining deduction, or be restocked twice.']);
                }

                [$product, $variant] = $this->lockStock($item);
                $this->inventory->stockIn(
                    $product, $return->quantity, mb_substr($note, 0, 255), $actor->id, $variant,
                    'return:'.$return->id.':restock', 'order_return', $return->id,
                );
                $return->restocked_quantity = $return->quantity;
                $item->stock_restored_quantity = (int) $item->stock_restored_quantity + $return->quantity;
                $item->save();
            }

            $fromStatus = $return->status;
            $return->status = $status;
            $return->processed_by = $actor->id;

            if ($note !== null && trim($note) !== '') {
                $return->disposition_note = $note;
            }

            $return->save();
            $this->recordReturnHistory($return, $fromStatus, $status, $note, $actor);

            return $return;
        }, attempts: 3);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasPermission('orders.manage'), 403);
    }

    private function recordReturnHistory(OrderReturn $return, string $from, string $to, ?string $note, User $actor): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $return->order_id,
            'from_status' => 'return:'.$return->id.':'.$from,
            'to_status' => 'return:'.$return->id.':'.$to,
            'reason' => $note,
            'user_id' => $actor->id,
        ]);
    }

    /** @param array<string, list<string>> $transitions */
    private function validateTransition(array $transitions, string $from, string $to): void
    {
        if (! in_array($to, $transitions[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'The transition from '.$from.' to '.$to.' is not allowed.']);
        }
    }

    private function requireText(?string $text, string $field, string $message): void
    {
        if ($text === null || trim($text) === '') {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function validateDeduction(OrderItem $item): void
    {
        $deducted = (int) $item->stock_deducted_quantity;
        $restored = (int) $item->stock_restored_quantity;
        $proven = $deducted > 0 && $deducted === (int) $item->quantity
            && $restored >= 0 && $restored <= $deducted
            && InventoryTransaction::query()
                ->where('product_id', $item->product_id)->where('variant_id', $item->variant_id)
                ->where('reference_type', 'order_item')->where('reference_id', $item->id)
                ->where('operation_key', 'order-item:'.$item->id.':deduct')
                ->where('type', 'stock_out')->where('quantity_change', -$deducted)->exists();

        if (! $proven) {
            throw ValidationException::withMessages([
                'stock' => 'Order item '.$item->id.' has no matching verified stock deduction or has inconsistent restoration counters. Reconcile its structured inventory ledger and counters before retrying; legacy free-text records cannot authorize restoration or returns.',
            ]);
        }
    }

    /** @return array{Product, ?ProductVariant} */
    private function lockStock(OrderItem $item): array
    {
        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
        $variant = null;

        if ($product && $item->variant_id !== null) {
            $variant = ProductVariant::query()->whereKey($item->variant_id)->where('product_id', $product->id)
                ->lockForUpdate()->first();
        }

        if (! $product || ($item->variant_id !== null && ! $variant)) {
            throw ValidationException::withMessages(['stock' => 'The original product or variant is unavailable. Reconcile the inventory record before restoring stock.']);
        }

        return [$product, $variant];
    }

    private function validateReturnRetry(OrderReturn $return, Order $order, OrderItem $item, int $qty, string $reason, User $actor): OrderReturn
    {
        if ((int) $return->order_id !== (int) $order->getKey()
            || (int) $return->order_item_id !== (int) $item->getKey()
            || $return->quantity !== $qty || $return->reason !== $reason
            || (int) $return->requested_by !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['operation_key' => 'This return operation key was already used with a different payload.']);
        }

        return $return;
    }
}
