<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'sort' => ['nullable', 'in:latest,price_asc,price_desc,name'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $term = trim((string) ($validated['q'] ?? ''));
        $sort = $validated['sort'] ?? 'latest';
        $query = Product::query()->active()->with(['category', 'variants' => fn ($query) => $query->where('is_active', true)])->withCount('variants')
            ->when($term, fn ($query) => $query->where(fn ($search) => $search->where('name', 'like', "%{$term}%")->orWhere('short_description', 'like', "%{$term}%")))
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->when(isset($validated['min_price']), fn ($query) => $query->where('selling_price', '>=', $validated['min_price']))
            ->when(isset($validated['max_price']), fn ($query) => $query->where('selling_price', '<=', $validated['max_price']));
        $query = match ($sort) {
            'price_asc' => $query->orderBy('selling_price'),
            'price_desc' => $query->orderByDesc('selling_price'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };
        $products = $query->paginate(15)->withQueryString();
        $categories = Category::query()->active()->orderBy('name')->get();
        $isShop = $request->routeIs('shop');

        return view('storefront.search', compact('products', 'term', 'isShop', 'categories', 'sort'));
    }

    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $products = Product::query()->active()->where('name', 'like', '%'.trim($validated['q']).'%')
            ->orderBy('name')->limit(6)->get(['id', 'name', 'slug', 'selling_price', 'main_image']);

        return response()->json($products->map(fn (Product $product): array => [
            'name' => $product->name,
            'url' => route('products.show', $product),
            'price' => $product->formatted_selling_price,
            'image' => $product->display_image,
        ]));
    }
}
