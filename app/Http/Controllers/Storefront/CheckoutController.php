<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\Cart\CartService;
use App\Services\CheckoutService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SslCommerzGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private CheckoutService $checkout,
        private PaymentService $payments,
        private SslCommerzGateway $sslCommerz,
    ) {}

    public function applyCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
            'delivery_zone' => ['nullable', 'in:inside_dhaka,outside_dhaka'],
        ]);
        $deliveryZone = $data['delivery_zone'] ?? $request->session()->get('delivery_zone', 'inside_dhaka');
        $request->session()->put('delivery_zone', $deliveryZone);
        $coupon = Coupon::query()->available()->where('code', strtoupper(trim($data['coupon_code'])))->first();
        if (! $coupon) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'কুপনটি বৈধ নয় বা বর্তমানে ব্যবহার করা যাচ্ছে না।'], 422);
            }

            return back()->withErrors(['coupon_code' => 'কুপনটি বৈধ নয় বা বর্তমানে ব্যবহার করা যাচ্ছে না।']);
        }
        try {
            $quote = $this->checkout->quote($this->cart->items(), couponCode: $coupon->code, deliveryZone: $deliveryZone);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->validator->errors()->first()], 422);
            }

            return back()->withErrors($exception->errors());
        }
        $request->session()->put('coupon_code', $coupon->code);
        $attempt = ['token' => (string) Str::uuid(), 'fingerprint' => $this->checkout->fingerprint($this->cart->items(), $request->user()?->id, $coupon->code, $deliveryZone)];
        $request->session()->put('checkout_attempt', $attempt);
        if ($request->expectsJson()) {
            return response()->json([...$quote, 'checkoutToken' => $attempt['token'], 'message' => 'কুপন প্রয়োগ হয়েছে।']);
        }

        return back()->with('success', 'কুপন প্রয়োগ হয়েছে।');
    }

    public function removeCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $deliveryZone = $request->input('delivery_zone', $request->session()->get('delivery_zone', 'inside_dhaka'));
        abort_unless(in_array($deliveryZone, ['inside_dhaka', 'outside_dhaka'], true), 422);
        $request->session()->put('delivery_zone', $deliveryZone);
        $request->session()->forget(['coupon_code', 'checkout_attempt']);
        if ($request->expectsJson()) {
            $quote = $this->checkout->quote($this->cart->items(), deliveryZone: $deliveryZone);
            $attempt = ['token' => (string) Str::uuid(), 'fingerprint' => $this->checkout->fingerprint($this->cart->items(), $request->user()?->id, null, $deliveryZone)];
            $request->session()->put('checkout_attempt', $attempt);

            return response()->json([...$quote, 'checkoutToken' => $attempt['token'], 'message' => 'কুপন সরানো হয়েছে।']);
        }

        return back()->with('success', 'কুপন সরানো হয়েছে।');
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'আপনার কার্ট খালি।');
        }

        try {
            $couponCode = $request->session()->get('coupon_code');
            $deliveryZone = $request->query('delivery_zone', $request->session()->get('delivery_zone', 'inside_dhaka'));
            if (! in_array($deliveryZone, ['inside_dhaka', 'outside_dhaka'], true)) {
                $deliveryZone = 'inside_dhaka';
            }
            $request->session()->put('delivery_zone', $deliveryZone);
            $quote = $this->checkout->quote($this->cart->items(), couponCode: $couponCode, deliveryZone: $deliveryZone);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->with('error', $exception->validator->errors()->first());
        }

        if ($quote['pricesChanged']) {
            $this->cart->refreshPrices(array_map(fn (array $item): float => $item['price'], $quote['items']));
            if (! $request->session()->has('errors')) {
                $request->session()->flash('error', 'পণ্যের দাম পরিবর্তিত হয়েছে। নতুন মূল্য দেখে অর্ডার নিশ্চিত করুন।');
            }
        }

        $fingerprint = $this->checkout->fingerprint($this->cart->items(), $request->user()?->id, $request->session()->get('coupon_code'), $deliveryZone);
        $attempt = $request->session()->get('checkout_attempt');
        if (! is_array($attempt) || ($attempt['fingerprint'] ?? null) !== $fingerprint) {
            $attempt = ['token' => (string) Str::uuid(), 'fingerprint' => $fingerprint];
            $request->session()->put('checkout_attempt', $attempt);
        }

        return view('storefront.checkout.index', [
            ...$quote,
            'checkoutToken' => $attempt['token'],
            'deliveryZone' => $deliveryZone,
            'deliveryRates' => [
                'inside_dhaka' => $this->checkout->shippingCharge('inside_dhaka'),
                'outside_dhaka' => $this->checkout->shippingCharge('outside_dhaka'),
            ],
            'onlinePaymentEnabled' => StoreSetting::query()->where('key', 'payment_sslcommerz_enabled')->value('value') === '1'
                && $this->sslCommerz->configured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_additional_phone' => ['nullable', 'string', 'max:20'],
            'customer_address' => ['required', 'string', 'max:500'],
            'delivery_zone' => ['nullable', 'in:inside_dhaka,outside_dhaka'],
            'payment_method' => ['required', 'in:cash_on_delivery,sslcommerz'],
            'note' => ['nullable', 'string', 'max:500'],
            'checkout_token' => ['required', 'uuid'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'variant_id' => ['prohibited'],
            'items' => ['prohibited'],
        ]);
        $data['delivery_zone'] = $data['delivery_zone'] ?? $request->session()->get('delivery_zone', 'inside_dhaka');
        $request->session()->put('delivery_zone', $data['delivery_zone']);

        if ($data['payment_method'] === 'sslcommerz') {
            if (StoreSetting::query()->where('key', 'payment_sslcommerz_enabled')->value('value') !== '1' || ! $this->sslCommerz->configured()) {
                throw ValidationException::withMessages(['payment_method' => 'অনলাইন পেমেন্ট বর্তমানে চালু নেই।']);
            }
            $quote = $this->checkout->quote($this->cart->items(), couponCode: $request->session()->get('coupon_code'), deliveryZone: $data['delivery_zone']);
            if ($quote['total'] < 10 || $quote['total'] > 500000) {
                throw ValidationException::withMessages(['payment_method' => 'অনলাইন পেমেন্টের জন্য অর্ডার মোট ৳১০ থেকে ৳৫,০০,০০০ হতে হবে।']);
            }
        }

        $token = $data['checkout_token'];
        unset($data['checkout_token'], $data['variant_id'], $data['items']);
        $couponCode = $request->session()->get('coupon_code');
        unset($data['coupon_code']);
        $attempt = $request->session()->get('checkout_attempt', []);
        $isCurrentAttempt = ($attempt['token'] ?? null) === $token;

        if (! $isCurrentAttempt && $request->session()->get('last_checkout_token') !== $token) {
            throw ValidationException::withMessages(['checkout_token' => 'চেকআউট পেজটি নতুন করে খুলে আবার চেষ্টা করুন।']);
        }

        if ($existing = Order::query()->where('checkout_token', $token)->first()) {
            if ($existing->user_id !== $request->user()?->id) {
                throw ValidationException::withMessages(['checkout_token' => 'চেকআউট পেজটি নতুন করে খুলে আবার চেষ্টা করুন।']);
            }

            if ($existing->payment_method === 'sslcommerz') {
                $payment = $existing->payments()->latest()->first();
                if ($payment?->status === 'pending' && $payment->gateway_url) {
                    return redirect()->away($payment->gateway_url);
                }
                if ($payment?->status === 'paid') {
                    return $this->confirm($request, $existing, $token, true);
                }

                return redirect()->route('checkout.index')->with('error', 'পেমেন্টটি সম্পন্ন হয়নি। নতুন করে অর্ডার নিশ্চিত করুন।');
            }

            return $this->confirm($request, $existing, $token, $isCurrentAttempt);
        }

        if (! $isCurrentAttempt || ($attempt['fingerprint'] ?? null) !== $this->checkout->fingerprint($this->cart->items(), $request->user()?->id, $couponCode, $data['delivery_zone'])) {
            throw ValidationException::withMessages(['cart' => 'কার্ট পরিবর্তিত হয়েছে। নতুন মোট মূল্য দেখে আবার অর্ডার নিশ্চিত করুন।']);
        }

        try {
            $order = $this->checkout->place($data, $this->cart->items(), $token, $request->user()?->id, $couponCode, $data['delivery_zone']);
        } catch (ValidationException $exception) {
            return redirect()->route('checkout.index')->withErrors($exception->errors())->withInput();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('checkout.index')
                ->with('error', 'অর্ডারটি সংরক্ষণ করা যায়নি। অনুগ্রহ করে আবার চেষ্টা করুন।')->withInput();
        }

        if ($order->payment_method === 'sslcommerz') {
            try {
                return redirect()->away($this->payments->start($order));
            } catch (Throwable $exception) {
                report($exception);
                $request->session()->put('checkout_attempt', [
                    'token' => (string) Str::uuid(),
                    'fingerprint' => $this->checkout->fingerprint($this->cart->items(), $request->user()?->id, $couponCode, $data['delivery_zone']),
                ]);

                return redirect()->route('checkout.index')->with('error', 'পেমেন্ট গেটওয়ে চালু করা যায়নি। কার্ট অক্ষত আছে, আবার চেষ্টা করুন।');
            }
        }

        return $this->confirm($request, $order, $token, true);
    }

    private function confirm(Request $request, Order $order, string $token, bool $clearCart): RedirectResponse
    {
        if ($clearCart) {
            $this->cart->clear();
            $request->session()->forget('checkout_attempt');
            $request->session()->forget('coupon_code');
        }

        $request->session()->put([
            'last_checkout_token' => $token,
            'last_order_number' => $order->order_number,
            'last_order_id' => $order->id,
        ]);
        $request->session()->forget('delivery_zone');

        return redirect()->route('order.confirmation');
    }
}
