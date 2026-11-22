<?php

namespace App\Services\Courier;

use App\Models\CourierSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SteadfastClient
{
    public function __construct(private readonly CourierSetting $setting) {}

    public function balance(): Response
    {
        return $this->request()->get('/get_balance');
    }

    /** @param array<string, mixed> $payload */
    public function createOrder(array $payload): Response
    {
        return $this->request(false)->post('/create_order', $payload);
    }

    public function statusByConsignment(string $consignmentId): Response
    {
        return $this->request()->get('/status_by_cid/'.rawurlencode($consignmentId));
    }

    public function statusByInvoice(string $invoice): Response
    {
        return $this->request()->get('/status_by_invoice/'.rawurlencode($invoice));
    }

    private function request(bool $retryRead = true)
    {
        $request = Http::baseUrl(rtrim((string) config('services.steadfast.base_url'), '/'))
            ->withHeaders([
                'Api-Key' => (string) $this->setting->api_key,
                'Secret-Key' => (string) $this->setting->secret_key,
                'Accept' => 'application/json',
            ])
            ->connectTimeout((int) config('services.steadfast.connect_timeout', 10))
            ->timeout((int) config('services.steadfast.timeout', 30));

        return $retryRead ? $request->retry(2, 200, fn ($exception) => $exception instanceof ConnectionException) : $request;
    }
}
