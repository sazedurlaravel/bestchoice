<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        private SslCommerzGateway $gateway,
        private OrderLifecycleService $orders,
    ) {}

    public function start(Order $order): string
    {
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'sslcommerz',
            'transaction_id' => 'BCPAY'.str_replace('-', '', (string) Str::uuid()),
            'amount' => $order->total,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        try {
            return $this->gateway->initiate($order->loadMissing(['items', 'user']), $payment);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($payment, $order): void {
                $payment->update(['status' => 'failed', 'gateway_response' => ['status' => 'SESSION_CREATION_FAILED']]);
                $this->orders->cancelForPaymentFailure($order, 'Online payment session could not be created.');
            });

            throw $exception;
        }
    }

    /** @param array<string, mixed> $callback @return array{payment:Payment, outcome:string} */
    public function processCallback(array $callback): array
    {
        $transactionId = $callback['tran_id'] ?? null;
        $callbackSessionKey = $callback['sessionkey'] ?? null;

        if ((! is_string($transactionId) || $transactionId === '')
            && (! is_string($callbackSessionKey) || $callbackSessionKey === '')) {
            throw ValidationException::withMessages(['payment' => 'The payment notification is incomplete.']);
        }

        $paymentQuery = Payment::query()->with('order');
        $payment = is_string($transactionId) && $transactionId !== ''
            ? $paymentQuery->where('transaction_id', $transactionId)->firstOrFail()
            : $paymentQuery->where('session_key', $callbackSessionKey)->firstOrFail();
        $validationId = $callback['val_id'] ?? null;
        $validationId = is_string($validationId) && $validationId !== '' ? $validationId : null;
        $verified = $validationId
            ? $this->gateway->validate($validationId)
            : $this->gateway->query((string) $payment->session_key);
        $this->assertMatchesPayment($payment, $verified, $validationId);
        $providerStatus = strtoupper((string) ($verified['status'] ?? ''));

        if (in_array($providerStatus, ['VALID', 'VALIDATED'], true)) {
            $outcome = (string) ($verified['risk_level'] ?? '0') === '1' ? 'review' : 'paid';
        } elseif (in_array($providerStatus, ['FAILED', 'CANCELLED', 'EXPIRED', 'UNATTEMPTED'], true)) {
            $outcome = $providerStatus === 'CANCELLED' ? 'cancelled' : 'failed';
        } else {
            throw ValidationException::withMessages(['payment' => 'The payment provider has not confirmed a final transaction status.']);
        }

        $payment = DB::transaction(function () use ($payment, $verified, $outcome, $validationId): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['paid', 'failed', 'cancelled', 'review'], true)) {
                return $locked;
            }

            $locked->update([
                'status' => $outcome,
                'validation_id' => $validationId ?: ($verified['val_id'] ?? null),
                'bank_transaction_id' => $verified['bank_tran_id'] ?? null,
                'payment_channel' => $verified['card_type'] ?? null,
                'risk_level' => isset($verified['risk_level']) ? (string) $verified['risk_level'] : null,
                'gateway_response' => $this->safeResponse($verified),
                'paid_at' => $outcome === 'paid' ? now() : null,
            ]);

            if ($outcome === 'paid' && $order->status !== 'cancelled') {
                return $locked;
            }

            if (in_array($outcome, ['failed', 'cancelled'], true) && $order->status !== 'cancelled') {
                $this->orders->cancelForPaymentFailure($order, 'Online payment '.$outcome.'.');
            }

            if ($outcome === 'paid' && $order->status === 'cancelled') {
                $locked->update(['status' => 'review']);
            }

            return $locked->refresh();
        }, attempts: 3);

        return ['payment' => $payment->load('order'), 'outcome' => $payment->status];
    }

    /** @param array<string, mixed> $verified */
    private function assertMatchesPayment(Payment $payment, array $verified, ?string $validationId): void
    {
        $expected = (int) round((float) $payment->amount * 100);
        $actual = isset($verified['amount']) ? (int) round((float) $verified['amount'] * 100) : -1;

        if (($verified['APIConnect'] ?? 'DONE') !== 'DONE'
            || ! in_array(strtoupper((string) ($verified['status'] ?? '')), ['VALID', 'VALIDATED', 'FAILED', 'CANCELLED', 'EXPIRED', 'UNATTEMPTED'], true)
            || ($verified['tran_id'] ?? null) !== $payment->transaction_id
            || $actual !== $expected
            || strtoupper((string) ($verified['currency'] ?? '')) !== 'BDT'
            || (string) ($verified['value_a'] ?? '') !== (string) $payment->order_id
            || ($validationId !== null && (string) ($verified['val_id'] ?? '') !== $validationId)
            || ($validationId === null && (string) ($verified['sessionkey'] ?? '') !== (string) $payment->session_key)) {
            throw new RuntimeException('SSLCOMMERZ transaction details did not match this order.');
        }
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function safeResponse(array $response): array
    {
        return array_intersect_key($response, array_flip([
            'status', 'val_id', 'tran_id', 'amount', 'currency', 'bank_tran_id',
            'card_type', 'risk_level', 'risk_title', 'APIConnect',
        ]));
    }
}
