<?php

namespace App\Services\Courier;

use App\Models\CourierSetting;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SteadfastService
{
    public function setting(): CourierSetting
    {
        return CourierSetting::query()->firstOrCreate(['courier' => 'steadfast'], ['config_revision' => 1]);
    }

    public function client(): SteadfastClient
    {
        $setting = $this->setting();
        if (! $setting->configured()) {
            throw ValidationException::withMessages(['steadfast' => 'Steadfast credentials are not configured.']);
        }

        return new SteadfastClient($setting);
    }

    public function testConnection(): string
    {
        $setting = $this->setting();
        if (! $setting->configured()) {
            throw ValidationException::withMessages(['connection' => 'Enter both Steadfast credentials before testing the connection.']);
        }

        try {
            $response = $this->client()->balance();
            $json = $response->json();
            $ok = $response->successful() && (int) ($json['status'] ?? 0) === 200;
            $message = $ok ? 'Connection successful.' : $this->providerMessage($json, 'Steadfast rejected the connection.');
            $setting->update(['last_connection_status' => $ok ? 'success' : 'failed', 'last_connection_message' => $message, 'last_connection_tested_at' => now()]);
            if (! $ok) {
                throw ValidationException::withMessages(['connection' => $message]);
            }

            return $message;
        } catch (ConnectionException) {
            $message = 'Steadfast could not be reached within the configured timeout.';
            $setting->update(['last_connection_status' => 'failed', 'last_connection_message' => $message, 'last_connection_tested_at' => now()]);
            throw ValidationException::withMessages(['connection' => $message]);
        }
    }

    public function book(Order $order, ?string $note = null): Shipment
    {
        $setting = $this->setting();
        if (! $setting->enabled || ! $setting->configured()) {
            throw ValidationException::withMessages(['steadfast' => 'Steadfast is disabled or not configured. Open Settings → Courier → Steadfast first.']);
        }
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages(['order' => 'Cancelled orders cannot be sent to Steadfast.']);
        }

        $shipment = DB::transaction(function () use ($order, $setting): Shipment {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'Cancelled orders cannot be sent to Steadfast.']);
            }
            $existing = $lockedOrder->shipments()->whereIn('booking_state', ['pending', 'submitting', 'booked', 'outcome_unknown'])->first();
            if ($existing) {
                throw ValidationException::withMessages(['shipment' => 'This order already has an active Steadfast shipment.']);
            }

            return Shipment::create([
                'order_id' => $lockedOrder->id, 'courier' => 'steadfast', 'config_revision' => $setting->config_revision,
                'invoice' => $lockedOrder->order_number, 'booking_state' => 'submitting', 'cod_amount' => $this->codAmount($lockedOrder),
            ]);
        }, 3);

        $payload = [
            'invoice' => $shipment->invoice,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $this->phone($order->customer_phone),
            'recipient_address' => $order->customer_address,
            'cod_amount' => (float) $shipment->cod_amount,
        ];
        if (filled($order->customer_additional_phone)) {
            $payload['alternative_phone'] = $this->phone($order->customer_additional_phone);
        }
        if (filled($note)) {
            $payload['note'] = Str::limit(trim($note), 500, '');
        }

        try {
            // Use the credentials captured with this booking intent. A concurrent credential rotation
            // must not silently submit an existing order to a different merchant account.
            $response = (new SteadfastClient($setting))->createOrder($payload);
            $json = $response->json();
            $consignment = $json['consignment'] ?? [];
            if (! $response->successful() || (int) ($json['status'] ?? 0) !== 200) {
                $shipment->update(['booking_state' => $response->serverError() ? 'outcome_unknown' : 'failed', 'failure_reason' => $this->providerMessage($json, 'Steadfast rejected the shipment.')]);

                return $shipment->refresh();
            }
            if (blank($consignment['consignment_id'] ?? null) || blank($consignment['tracking_code'] ?? null)) {
                $shipment->update(['booking_state' => 'outcome_unknown', 'failure_reason' => 'Steadfast returned an incomplete booking response.']);

                return $shipment->refresh();
            }

            return $this->markBooked($shipment, (string) $consignment['consignment_id'], (string) $consignment['tracking_code'], (string) ($consignment['status'] ?? 'in_review'));
        } catch (ConnectionException) {
            $shipment->update(['booking_state' => 'outcome_unknown', 'failure_reason' => 'The booking request timed out or could not be completed. Reconcile before trying again.']);

            return $shipment->refresh();
        } catch (Throwable $e) {
            report($e);
            $shipment->update(['booking_state' => 'outcome_unknown', 'failure_reason' => 'The booking outcome could not be confirmed. Reconcile before trying again.']);

            return $shipment->refresh();
        }
    }

    public function refresh(Shipment $shipment, bool $reconcile = false): Shipment
    {
        abort_unless($shipment->booking_state === 'booked' || ($reconcile && $shipment->booking_state === 'outcome_unknown'), 422, 'This shipment cannot be refreshed yet.');
        $response = $shipment->consignment_id ? $this->client()->statusByConsignment($shipment->consignment_id) : $this->client()->statusByInvoice($shipment->invoice);
        $json = $response->json();
        $status = $json['delivery_status'] ?? data_get($json, 'consignment.status');
        if (! $response->successful() || blank($status)) {
            if ($reconcile) {
                $shipment->update(['failure_reason' => $this->providerMessage($json, 'Steadfast could not confirm this shipment.')]);
            }

            return $shipment->refresh();
        }

        return $this->recordStatus($shipment, (string) $status, 'manual');
    }

    private function markBooked(Shipment $shipment, string $consignmentId, string $trackingCode, string $status): Shipment
    {
        $shipment->update(['consignment_id' => $consignmentId, 'tracking_code' => $trackingCode, 'booking_state' => 'booked', 'booked_at' => now(), 'failure_reason' => null]);

        return $this->recordStatus($shipment, $status, 'booking');
    }

    private function recordStatus(Shipment $shipment, string $providerStatus, string $source): Shipment
    {
        $local = match ($providerStatus) {
            'in_review', 'pending', 'hold' => 'in_progress',
            'delivered_approval_pending', 'partial_delivered_approval_pending', 'cancelled_approval_pending', 'unknown_approval_pending' => 'awaiting_approval',
            'delivered' => 'delivered', 'partial_delivered' => 'partial_delivered', 'cancelled' => 'cancelled',
            default => 'unknown',
        };
        $shipment->update(['provider_status' => $providerStatus, 'local_status' => $local, 'last_synced_at' => now()]);
        $shipment->statusHistories()->create(['provider_status' => $providerStatus, 'local_status' => $local, 'source' => $source, 'observed_at' => now()]);

        return $shipment->refresh();
    }

    private function codAmount(Order $order): float
    {
        $paid = (float) $order->payments()->where('status', 'paid')->sum('amount');

        return max(0, round((((float) $order->total - $paid) * 100)) / 100);
    }

    private function phone(?string $phone): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', (string) $phone);

        return Str::startsWith($normalized, '+880') ? '0'.substr($normalized, 4) : (Str::startsWith($normalized, '880') ? '0'.substr($normalized, 3) : $normalized);
    }

    private function providerMessage(array $json, string $fallback): string
    {
        return Str::limit(trim((string) ($json['message'] ?? $json['error'] ?? $fallback)), 500, '');
    }
}
