<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SslCommerzGateway
{
    public function configured(): bool
    {
        return filled(config('services.sslcommerz.store_id'))
            && filled(config('services.sslcommerz.store_password'));
    }

    public function initiate(Order $order, Payment $payment): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('SSLCOMMERZ merchant credentials are not configured.');
        }

        $baseUrl = config('services.sslcommerz.base_url');
        $response = Http::asForm()->connectTimeout(5)->timeout(20)->post($baseUrl.'/gwprocess/v4/api.php', [
            'store_id' => config('services.sslcommerz.store_id'),
            'store_passwd' => config('services.sslcommerz.store_password'),
            'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => $payment->transaction_id,
            'success_url' => route('payments.sslcommerz.success'),
            'fail_url' => route('payments.sslcommerz.fail'),
            'cancel_url' => route('payments.sslcommerz.cancel'),
            'ipn_url' => route('payments.sslcommerz.ipn'),
            'cus_name' => mb_substr($order->customer_name, 0, 50),
            'cus_email' => mb_substr($order->user?->email ?: 'customer@example.com', 0, 50),
            'cus_add1' => mb_substr($order->customer_address, 0, 50),
            'cus_city' => mb_substr($order->customer_district ?: 'Dhaka', 0, 50),
            'cus_postcode' => '1000',
            'cus_country' => 'Bangladesh',
            'cus_phone' => mb_substr($order->customer_phone, 0, 20),
            'shipping_method' => 'YES',
            'ship_name' => mb_substr($order->customer_name, 0, 50),
            'ship_add1' => mb_substr($order->customer_address, 0, 50),
            'ship_city' => mb_substr($order->customer_district ?: 'Dhaka', 0, 50),
            'ship_postcode' => '1000',
            'ship_country' => 'Bangladesh',
            'num_of_item' => max(1, (int) $order->items()->sum('quantity')),
            'product_name' => mb_substr($order->items()->pluck('product_name')->unique()->implode(', '), 0, 255),
            'product_category' => 'clothing',
            'product_profile' => 'physical-goods',
            'value_a' => (string) $order->id,
        ]);

        $payload = $response->json();
        $gatewayUrl = is_array($payload) ? ($payload['GatewayPageURL'] ?? null) : null;
        $host = is_string($gatewayUrl) ? parse_url($gatewayUrl, PHP_URL_HOST) : null;
        $expectedHost = parse_url($baseUrl, PHP_URL_HOST);

        if (! $response->successful() || ($payload['status'] ?? null) !== 'SUCCESS'
            || ! is_string($gatewayUrl) || parse_url($gatewayUrl, PHP_URL_SCHEME) !== 'https'
            || $host !== $expectedHost || empty($payload['sessionkey'])) {
            throw new RuntimeException('SSLCOMMERZ could not create a valid payment session.');
        }

        $payment->update([
            'session_key' => (string) $payload['sessionkey'],
            'gateway_url' => $gatewayUrl,
        ]);

        return $gatewayUrl;
    }

    /** @return array<string, mixed> */
    public function validate(string $validationId): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('SSLCOMMERZ merchant credentials are not configured.');
        }

        $response = Http::connectTimeout(5)->timeout(15)->get(
            config('services.sslcommerz.base_url').'/validator/api/validationserverAPI.php',
            [
                'val_id' => $validationId,
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'format' => 'json',
            ],
        );

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('SSLCOMMERZ transaction validation failed.');
        }

        return $response->json();
    }

    /** @return array<string, mixed> */
    public function query(string $sessionKey): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('SSLCOMMERZ merchant credentials are not configured.');
        }

        $response = Http::connectTimeout(5)->timeout(15)->get(
            config('services.sslcommerz.base_url').'/validator/api/merchantTransIDvalidationAPI.php',
            [
                'sessionkey' => $sessionKey,
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'format' => 'json',
            ],
        );

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('SSLCOMMERZ transaction lookup failed.');
        }

        return $response->json();
    }
}
