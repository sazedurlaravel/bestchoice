<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Computes a simple rule-based fraud risk score (0–100) for an order.
 *
 * Rules:
 *  +30  Cash on delivery (highest single signal)
 *  +25  Customer has ≥1 cancelled order on the same phone
 *  +20  Brand-new customer (no prior completed/delivered order on this phone)
 *  +10  High value order (total ≥ 2000 ৳)
 *  +15  Burst orders: ≥2 other orders today from the same phone
 *  +5   Address is very short / district missing
 */
class FraudScoreService
{
    /**
     * Scores a single order. Pass a pre-fetched history collection to avoid N+1 queries.
     *
     * @param  Collection<int,Order>|null  $historyByPhone  All previous orders for this phone, keyed by id.
     */
    public function score(Order $order, ?Collection $historyByPhone = null): int
    {
        if ($historyByPhone === null) {
            $historyByPhone = Order::query()
                ->where('customer_phone', $order->customer_phone)
                ->where('id', '!=', $order->id)
                ->get();
        }

        $score = 0;

        // COD
        if ($order->payment_method === 'cash_on_delivery') {
            $score += 30;
        }

        // Cancelled history
        $hasCancelled = $historyByPhone->contains(fn (Order $o) => $o->status === 'cancelled');
        if ($hasCancelled) {
            $score += 25;
        }

        // New customer — no prior delivered/completed order
        $hasDelivered = $historyByPhone->contains(fn (Order $o) => $o->status === 'delivered');
        if (! $hasDelivered) {
            $score += 20;
        }

        // High value
        if ((float) $order->total >= 2000) {
            $score += 10;
        }

        // Multiple orders today from same phone
        $todayCount = $historyByPhone->filter(
            fn (Order $o) => $o->created_at->isSameDay($order->created_at)
        )->count();
        if ($todayCount >= 1) {
            $score += 10;
        }

        // Incomplete address
        if (empty($order->customer_district) || mb_strlen($order->customer_address) < 15) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * Scores a whole paginated/collection of orders efficiently (one extra query per unique phone).
     *
     * @param  Collection<int,Order>  $orders
     * @return array<int,int> Map of order id → score
     */
    public function scoreMany(Collection $orders): array
    {
        $phones = $orders->pluck('customer_phone')->unique()->values()->all();

        // Load all history for these phones in one query
        $orderIds = $orders->pluck('id')->all();
        $history = Order::query()
            ->whereIn('customer_phone', $phones)
            ->whereNotIn('id', $orderIds)
            ->get()
            ->groupBy('customer_phone');

        $scores = [];
        foreach ($orders as $order) {
            $phoneHistory = $history->get($order->customer_phone, collect());
            $scores[$order->id] = $this->score($order, $phoneHistory);
        }

        return $scores;
    }
}
