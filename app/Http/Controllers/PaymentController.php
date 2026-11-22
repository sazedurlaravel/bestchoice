<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartService;
use App\Services\CheckoutService;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments, private CartService $cart, private CheckoutService $checkout) {}

    public function success(Request $request): View|RedirectResponse
    {
        return $this->handleBrowserCallback($request);
    }

    public function fail(Request $request): View|RedirectResponse
    {
        return $this->handleBrowserCallback($request);
    }

    public function cancel(Request $request): View|RedirectResponse
    {
        return $this->handleBrowserCallback($request);
    }

    public function ipn(Request $request): Response
    {
        try {
            $this->payments->processCallback($request->all());

            return response('OK', 200);
        } catch (Throwable $exception) {
            report($exception);

            return response('Payment notification rejected', 422);
        }
    }

    private function handleBrowserCallback(Request $request): View|RedirectResponse
    {
        try {
            ['payment' => $payment, 'outcome' => $outcome] = $this->payments->processCallback($request->all());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('checkout.index')->with('error', 'পেমেন্ট যাচাই করা যায়নি। অর্ডার স্ট্যাটাস নিশ্চিত করতে আমাদের সাথে যোগাযোগ করুন।');
        }

        $order = $payment->order;
        if ($outcome === 'paid') {
            $this->cart->clear();
            $request->session()->forget(['checkout_attempt', 'coupon_code']);
            $request->session()->put(['last_order_number' => $order->order_number, 'last_order_id' => $order->id]);
        } elseif (in_array($outcome, ['failed', 'cancelled'], true) && ! $this->cart->isEmpty()) {
            $request->session()->put('checkout_attempt', [
                'token' => (string) Str::uuid(),
                'fingerprint' => $this->checkout->fingerprint($this->cart->items(), $request->user()?->id, $request->session()->get('coupon_code'), $request->session()->get('delivery_zone', 'inside_dhaka')),
            ]);
        }

        return view('storefront.checkout.payment-result', compact('payment', 'order', 'outcome'));
    }
}
