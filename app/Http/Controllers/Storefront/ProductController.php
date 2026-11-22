<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Product $product, Request $request): View
    {
        abort_unless($product->is_active, 404);
        $product->load(['category.parent', 'images', 'variants']);
        $relatedProducts = Product::query()
            ->active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(['category', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->withCount('variants')
            ->latest()
            ->take(4)
            ->get();
        $recentIds = array_values(array_unique(array_merge([$product->id], (array) $request->session()->get('recent_products', []))));
        $request->session()->put('recent_products', array_slice($recentIds, 0, 8));

        return view('storefront.products.show', compact('product', 'relatedProducts'));
    }
}
