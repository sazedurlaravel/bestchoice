<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Courier\SteadfastService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CourierController extends Controller
{
    public function edit(SteadfastService $steadfast): View
    {
        return view('admin.settings.courier', ['setting' => $steadfast->setting()]);
    }

    public function update(Request $request, SteadfastService $steadfast): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'api_key' => ['nullable', 'string', 'max:500'], 'secret_key' => ['nullable', 'string', 'max:500'], 'clear_api_key' => ['nullable', 'boolean'], 'clear_secret_key' => ['nullable', 'boolean']]);
        $setting = $steadfast->setting();
        $apiKey = $data['clear_api_key'] ?? false ? null : (($data['api_key'] ?? '') !== '' ? $data['api_key'] : $setting->api_key);
        $secretKey = $data['clear_secret_key'] ?? false ? null : (($data['secret_key'] ?? '') !== '' ? $data['secret_key'] : $setting->secret_key);
        if ($data['enabled'] && (! $apiKey || ! $secretKey)) {
            throw ValidationException::withMessages(['enabled' => 'Both credentials are required before enabling Steadfast.']);
        }
        $changed = $apiKey !== $setting->api_key || $secretKey !== $setting->secret_key;
        $setting->update(['enabled' => (bool) $data['enabled'], 'api_key' => $apiKey, 'secret_key' => $secretKey, 'config_revision' => $changed ? $setting->config_revision + 1 : $setting->config_revision, 'last_connection_status' => $changed ? null : $setting->last_connection_status, 'last_connection_message' => $changed ? null : $setting->last_connection_message, 'last_connection_tested_at' => $changed ? null : $setting->last_connection_tested_at]);

        return back()->with('success', 'Steadfast settings saved.');
    }

    public function test(SteadfastService $steadfast): RedirectResponse
    {
        $steadfast->testConnection();

        return back()->with('success', 'Steadfast connection test succeeded.');
    }

    public function book(Request $request, Order $order, SteadfastService $steadfast): RedirectResponse
    {
        if (in_array($order->review_status, ['needs_confirmation', 'needs_correction'], true)) {
            return back()->with('error', 'এই অর্ডারটি COD রিভিউতে আটকে আছে ('.($order->review_status === 'needs_correction' ? 'সংশোধন প্রয়োজন' : 'নিশ্চিতকরণ প্রয়োজন').')। বুক করার আগে রিভিউ সম্পন্ন করুন।');
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        try {
            $shipment = $steadfast->book($order, $data['note'] ?? null);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Steadfast booking failed. Please check the courier settings and try again.');
        }

        return back()->with($shipment->booking_state === 'booked' ? 'success' : 'error', $shipment->booking_state === 'booked' ? 'Shipment booked with Steadfast.' : ($shipment->failure_reason ?? 'Shipment booking needs reconciliation.'));
    }

    public function refresh(Shipment $shipment, SteadfastService $steadfast): RedirectResponse
    {
        abort_unless($shipment->courier === 'steadfast', 404);
        $shipment = $steadfast->refresh($shipment, $shipment->booking_state === 'outcome_unknown');

        return back()->with($shipment->provider_status ? 'success' : 'error', $shipment->provider_status ? 'Delivery status refreshed.' : ($shipment->failure_reason ?? 'Delivery status could not be confirmed.'));
    }
}
