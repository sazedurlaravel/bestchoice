<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private CartService $cart, private CheckoutService $checkout) {}

    public function index(): View
    {
        $items = $this->cart->items();

        $subtotal = $this->cart->subtotal();
        $shippingCharge = $this->checkout->shippingCharge('inside_dhaka');

        return view('storefront.cart.index', [
            'items' => $items,
            'subtotal' => $subtotal,
            'shippingCharge' => $shippingCharge,
            'total' => $subtotal + $shippingCharge,
        ]);
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
            'cart_request_id' => ['nullable', 'string', 'max:64'],
            'redirect_to' => ['nullable', 'string', 'in:checkout,cart'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = isset($data['variant_id']) ? $product->variants()->whereKey($data['variant_id'])->first() : null;
        if (isset($data['variant_id']) && ! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'নির্বাচিত সাইজ বা রঙ সঠিক নয়।']);
        }
        if (! $variant && $product->variants()->exists()) {
            throw ValidationException::withMessages(['variant_id' => 'পণ্যের সাইজ বা রঙ নির্বাচন করুন।']);
        }

        $available = $variant?->stock_quantity ?? $product->stock_quantity;
        if (! $product->is_active || $available < 1) {
            return back()->with('error', 'এই পণ্যটি এখন পাওয়া যাচ্ছে না।');
        }

        $key = CartService::lineKey($product->id, $variant?->id);
        $alreadyInCart = $this->cart->items()[$key]['qty'] ?? 0;
        if ($alreadyInCart + $data['qty'] > $available) {
            return back()->with('error', 'পর্যাপ্ত স্টক নেই। সর্বোচ্চ '.($available - $alreadyInCart).'টি যোগ করতে পারবেন।');
        }

        $requestId = $data['cart_request_id'] ?? null;
        $processedRequests = $request->session()->get('cart_add_requests', []);
        if ($requestId && isset($processedRequests[$requestId])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Item already added.',
                    'count' => $this->cart->count(),
                    'subtotal' => $this->cart->subtotal(),
                ]);
            }

            return redirect()->route('cart.index');
        }

        $inCart = $alreadyInCart;
        $goToCheckout = ($data['redirect_to'] ?? 'cart') === 'checkout';

        if ($goToCheckout && $inCart > 0) {
            return redirect()->route('checkout.index');
        }

        $this->checkout->quote([[
            'product_id' => $product->id, 'variant_id' => $variant?->id,
            'qty' => $inCart + $data['qty'],
            'price' => $variant ? $variant->effectiveSellingPrice() : (float) $product->selling_price,
        ]]);

        $this->cart->add($product, $data['qty'], $variant);

        if ($requestId) {
            $processedRequests[$requestId] = true;
            $request->session()->put('cart_add_requests', array_slice($processedRequests, -20, 20, true));
        }

        if ($request->expectsJson()) {
            $request->session()->flash('open_cart', true);

            return response()->json([
                'message' => '"'.$product->name.'" কার্টে যোগ করা হয়েছে।',
                'count' => $this->cart->count(),
                'subtotal' => $this->cart->subtotal(),
            ]);
        }

        if ($goToCheckout) {
            return redirect()->route('checkout.index');
        }

        return redirect()->route('cart.index')->with('success', '"'.$product->name.'" কার্টে যোগ করা হয়েছে।');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : null;
        $item = $this->cart->items()[CartService::lineKey((int) $data['product_id'], $variantId)] ?? null;

        if ($item && $data['qty'] > 0) {
            $product = Product::find((int) $data['product_id']);
            if ($product) {
                $variant = $variantId ? $product->variants()->whereKey($variantId)->first() : null;
                $available = $variant?->stock_quantity ?? $product->stock_quantity;
                if ($data['qty'] > $available) {
                    return back()->with('error', 'পর্যাপ্ত স্টক নেই। সর্বোচ্চ '.$available.'টি অর্ডার করা যাবে।');
                }
            }
            $this->checkout->quote([array_replace($item, ['qty' => $data['qty']])]);
        }
        $this->cart->updateQty((int) $data['product_id'], (int) $data['qty'], $variantId);

        $redirect = back()->with('success', 'কার্ট আপডেট হয়েছে।');
        if ($request->boolean('drawer')) {
            $redirect->with('open_cart', true);
        }

        return $redirect;
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'variant_id' => ['nullable', 'integer', 'min:1']]);
        $this->cart->remove((int) $data['product_id'], isset($data['variant_id']) ? (int) $data['variant_id'] : null);

        $redirect = back()->with('success', 'পণ্য কার্ট থেকে সরানো হয়েছে।');
        if ($request->boolean('drawer')) {
            $redirect->with('open_cart', true);
        }

        return $redirect;
    }
}
