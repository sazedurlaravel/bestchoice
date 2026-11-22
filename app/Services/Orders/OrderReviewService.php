<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderRefusal;
use App\Models\StoreSetting;
use Illuminate\Support\Str;

/**
 * Evaluates COD order review rules and returns a structured outcome.
 *
 * Outcomes:
 *   ready              — no rules fired, safe to book immediately
 *   needs_confirmation — customer should be contacted, but address/data looks OK
 *   needs_correction   — data is invalid/incomplete; must be fixed before booking
 *
 * @phpstan-type ReviewResult array{status:string,reasons:list<string>}
 */
class OrderReviewService
{
    /** @var array<string,mixed> */
    private array $settings = [];

    public function __construct()
    {
        $this->settings = StoreSetting::query()
            ->whereIn('key', [
                'cod_review_amount_threshold',
                'cod_review_duplicate_minutes',
                'cod_review_refusal_count',
                'cod_review_refusal_days',
                'cod_review_active_orders_max',
            ])
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * @return ReviewResult
     */
    public function evaluate(Order $order): array
    {
        // Only COD orders go through review
        if ($order->payment_method !== 'cash_on_delivery') {
            return ['status' => 'ready', 'reasons' => []];
        }

        $phone = $this->normalizePhone($order->customer_phone);

        $correctionReasons = [];
        $confirmationReasons = [];

        // Rule 1 — Invalid Bangladesh mobile number
        if (! $this->isValidBangladeshPhone($phone)) {
            $correctionReasons[] = 'invalid_phone';
        }

        // Rule 2 — Incomplete address
        if ($this->isAddressIncomplete($order)) {
            $correctionReasons[] = 'incomplete_address';
        }

        // Rule 4 — Possible duplicate order (same phone + items within window)
        if ($correctionReasons === [] && $this->isPossibleDuplicate($order, $phone)) {
            $correctionReasons[] = 'possible_duplicate';
        }

        // Rule 5 — Large first COD order (new customer above threshold)
        if ($this->isLargeFirstOrder($order, $phone)) {
            $confirmationReasons[] = 'large_first_order';
        }

        // Rule 6 — Repeat delivery refusals
        if ($this->hasRepeatRefusals($phone)) {
            $confirmationReasons[] = 'repeat_refusals';
        }

        // Rule 7 — Multiple active COD orders
        if ($this->hasTooManyActiveOrders($order, $phone)) {
            $confirmationReasons[] = 'too_many_active_orders';
        }

        if ($correctionReasons !== []) {
            return ['status' => 'needs_correction', 'reasons' => array_merge($correctionReasons, $confirmationReasons)];
        }

        if ($confirmationReasons !== []) {
            return ['status' => 'needs_confirmation', 'reasons' => $confirmationReasons];
        }

        return ['status' => 'ready', 'reasons' => []];
    }

    /**
     * Apply outcome to the order and persist it.
     */
    public function applyToOrder(Order $order): Order
    {
        $result = $this->evaluate($order);

        $order->update([
            'review_status' => $result['status'],
            'review_reasons' => $result['reasons'] ?: null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'confirmation_note' => null,
        ]);

        return $order->refresh();
    }

    /**
     * Re-run rules after staff edits phone/address/items/total.
     * Clears any prior confirmation so a fresh review is required.
     */
    public function resetAndReapply(Order $order): Order
    {
        return $this->applyToOrder($order);
    }

    /**
     * Normalize a Bangladesh mobile number to 01XXXXXXXXX format for comparison.
     * Accepts: 01XXXXXXXXX, 8801XXXXXXXXX, +8801XXXXXXXXX
     */
    public function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^\d]/', '', $phone) ?? '';

        if (str_starts_with($cleaned, '880') && strlen($cleaned) === 13) {
            return '0'.substr($cleaned, 3);
        }

        return $cleaned;
    }

    private function isValidBangladeshPhone(string $normalizedPhone): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', $normalizedPhone);
    }

    private function isAddressIncomplete(Order $order): bool
    {
        $address = trim($order->customer_address ?? '');
        $district = trim($order->customer_district ?? '');

        return $district === '' || strlen($address) < 15;
    }

    private function isPossibleDuplicate(Order $order, string $normalizedPhone): bool
    {
        $windowMinutes = (int) ($this->settings['cod_review_duplicate_minutes'] ?? 15);
        $since = now()->subMinutes($windowMinutes);

        $recentOrders = Order::query()
            ->where('id', '!=', $order->id)
            ->where('payment_method', 'cash_on_delivery')
            ->where('created_at', '>=', $since)
            ->whereRaw("REPLACE(REPLACE(REPLACE(customer_phone, '+880', '0'), '880', '0'), ' ', '') = ?", [$normalizedPhone])
            ->with('items')
            ->get();

        if ($recentOrders->isEmpty()) {
            return false;
        }

        $orderItemKey = $this->itemFingerprint($order);

        foreach ($recentOrders as $recent) {
            if ($this->itemFingerprint($recent) === $orderItemKey) {
                return true;
            }
        }

        return false;
    }

    private function itemFingerprint(Order $order): string
    {
        $items = $order->items->map(fn ($i) => [
            $i->product_id,
            $i->variant_id,
            $i->qty,
        ])->sortBy(0)->values()->all();

        return md5(json_encode($items, JSON_THROW_ON_ERROR));
    }

    private function isLargeFirstOrder(Order $order, string $normalizedPhone): bool
    {
        $threshold = (float) ($this->settings['cod_review_amount_threshold'] ?? 3000);

        if ((float) $order->total < $threshold) {
            return false;
        }

        // Check if customer has any prior delivered order
        $hasDelivered = Order::query()
            ->where('id', '!=', $order->id)
            ->where('status', 'delivered')
            ->whereRaw("REPLACE(REPLACE(REPLACE(customer_phone, '+880', '0'), '880', '0'), ' ', '') = ?", [$normalizedPhone])
            ->exists();

        return ! $hasDelivered;
    }

    private function hasRepeatRefusals(string $normalizedPhone): bool
    {
        $count = (int) ($this->settings['cod_review_refusal_count'] ?? 2);
        $days = (int) ($this->settings['cod_review_refusal_days'] ?? 90);
        $since = now()->subDays($days);

        $refusalCount = OrderRefusal::query()
            ->where('created_at', '>=', $since)
            ->whereRaw("REPLACE(REPLACE(REPLACE(customer_phone, '+880', '0'), '880', '0'), ' ', '') = ?", [$normalizedPhone])
            ->count();

        return $refusalCount >= $count;
    }

    private function hasTooManyActiveOrders(Order $order, string $normalizedPhone): bool
    {
        $max = (int) ($this->settings['cod_review_active_orders_max'] ?? 2);

        $activeCount = Order::query()
            ->where('id', '!=', $order->id)
            ->where('payment_method', 'cash_on_delivery')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->whereRaw("REPLACE(REPLACE(REPLACE(customer_phone, '+880', '0'), '880', '0'), ' ', '') = ?", [$normalizedPhone])
            ->count();

        return $activeCount >= $max;
    }

    /**
     * Human-readable label for a review reason key.
     */
    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'invalid_phone' => 'Invalid phone number',
            'incomplete_address' => 'Incomplete address (missing district or too short)',
            'possible_duplicate' => 'Possible duplicate order (same phone + items within window)',
            'large_first_order' => 'Large first COD order (new customer above threshold)',
            'repeat_refusals' => 'Customer has repeated delivery refusals',
            'too_many_active_orders' => 'Customer has too many active COD orders',
            default => Str::title(str_replace('_', ' ', $reason)),
        };
    }
}
