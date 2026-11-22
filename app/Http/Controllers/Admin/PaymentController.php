<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\StoreSetting;
use App\Services\Payments\SslCommerzGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(SslCommerzGateway $gateway): View
    {
        $payments = Payment::query()->with('order')->latest()->paginate(30);
        $counts = Payment::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $enabled = StoreSetting::query()->where('key', 'payment_sslcommerz_enabled')->value('value') === '1';
        $configured = $gateway->configured();

        return view('admin.payments.index', compact('payments', 'counts', 'enabled', 'configured'));
    }

    public function updateSettings(Request $request, SslCommerzGateway $gateway): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        if ($data['enabled'] && ! $gateway->configured()) {
            throw ValidationException::withMessages([
                'enabled' => 'প্রথমে .env ফাইলে SSLCOMMERZ_STORE_ID এবং SSLCOMMERZ_STORE_PASSWORD সেট করুন।',
            ]);
        }

        StoreSetting::query()->updateOrCreate(
            ['key' => 'payment_sslcommerz_enabled'],
            ['value' => $data['enabled'] ? '1' : '0'],
        );

        return back()->with('success', 'পেমেন্ট সেটিংস আপডেট হয়েছে।');
    }
}
