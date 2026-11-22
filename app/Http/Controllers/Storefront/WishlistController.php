<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $ids = array_map('intval', $request->user()?->wishlist_product_ids ?? $request->session()->get('wishlist', []));
        $products = Product::query()->active()->with(['category', 'variants' => fn ($query) => $query->where('is_active', true)])->withCount('variants')->whereIn('id', $ids)->get();

        return view('storefront.wishlist.index', compact('products'));
    }

    public function toggle(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'exists:products,id']]);
        $ids = array_map('intval', $request->user()?->wishlist_product_ids ?? $request->session()->get('wishlist', []));
        $productId = (int) $data['product_id'];

        if (in_array($productId, $ids, true)) {
            $ids = array_values(array_diff($ids, [$productId]));
        } else {
            $ids[] = $productId;
        }

        $request->session()->put('wishlist', $ids);
        if ($request->user()) {
            $request->user()->forceFill(['wishlist_product_ids' => $ids])->save();
        }
        $isSaved = in_array($productId, $ids, true);
        $message = $isSaved ? 'পছন্দের তালিকায় যোগ হয়েছে।' : 'পছন্দের তালিকা থেকে সরানো হয়েছে।';

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => $isSaved,
                'count' => count($ids),
                'message' => $message,
            ]);
        }

        if ($request->string('return_to')->toString() === 'wishlist') {
            return redirect()->to(route('wishlist').'#wishlist-item-'.$productId)
                ->with('success', $message);
        }

        return back()->with('success', $message);
    }
}
