<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomepageBanner;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $settings = StoreSetting::query()->pluck('value', 'key');
        $collectionCategoryLimit = min(24, max(1, (int) ($settings['home_collection_category_limit'] ?? 8)));
        $productColumnsDesktop = min(8, max(1, (int) ($settings['home_product_columns_desktop'] ?? 4)));
        $productColumnsMobile = min(4, max(1, (int) ($settings['home_product_columns_mobile'] ?? 2)));
        $categories = Category::query()->active()->topLevel()->with(['children' => fn ($query) => $query->active()->withCount(['products' => fn ($products) => $products->active()])])->orderBy('sort_order')->get();
        $categoryIndexes = $categories->pluck('id')->flip();
        $frontendCategories = $categories->map(fn (Category $category): array => [$category->name, $category->image_path])->values()->all();
        $frontendCollections = $categories->map(fn (Category $category): array => [
            'title' => $category->name,
                'items' => $category->children->take($collectionCategoryLimit)->map(fn (Category $child): array => [
                'name' => $child->name,
                'image' => $child->image_path ?: $category->image_path,
                'url' => route('categories.show', $child),
                'count' => (int) $child->products_count,
            ])->values()->all(),
        ])->values()->all();

        $frontendProducts = Product::query()->active()->with(['category.parent'])->withCount('variants')->latest()->get()->values()->map(function (Product $product) use ($categoryIndexes): array {
            $topLevelCategoryId = $product->category?->parent_id ?? $product->category_id;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'url' => route('products.show', $product),
                'unit' => '১ পিস',
                'price' => (float) $product->selling_price,
                'regularPrice' => $product->regular_price === null ? null : (float) $product->regular_price,
                'description' => $product->short_description ?: $product->full_description,
                'stock' => (int) $product->stock_quantity,
                'hasVariants' => $product->variants_count > 0,
                'cat' => $categoryIndexes[$topLevelCategoryId] ?? 0,
                'img' => $product->display_image,
                'tag' => $product->is_featured ? 'জনপ্রিয়' : '',
            ];
        })->all();

        $deliveryCharge = (float) ($settings['delivery_charge'] ?? 60);
        $homepageContent = [
            'collectionSubtitle' => $settings['home_collection_subtitle'] ?? 'পছন্দের কালেকশন থেকে ক্যাটাগরি বেছে নিন',
            'collectionCategoryLimit' => $collectionCategoryLimit,
            'featuredKicker' => $settings['home_featured_kicker'] ?? 'CUSTOMER FAVORITES',
            'featuredTitle' => $settings['home_featured_title'] ?? 'সেরা পছন্দের পণ্য',
            'productColumnsDesktop' => $productColumnsDesktop,
            'productColumnsMobile' => $productColumnsMobile,
        ];
        $homepageBanners = HomepageBanner::query()->active()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (HomepageBanner $banner): array => [
                'kicker' => $banner->kicker,
                'headline' => $banner->headline,
                'highlight' => $banner->highlight,
                'description' => $banner->description,
                'buttonText' => $banner->button_text,
                'buttonUrl' => $banner->button_url,
                'imageUrl' => $banner->image_url,
                'backgroundColor' => $banner->background_color,
            ])->all();
        $wishlistIds = array_map('intval', request()->session()->get('wishlist', []));

        return view('storefront.home', compact('frontendCategories', 'frontendCollections', 'frontendProducts', 'deliveryCharge', 'wishlistIds', 'homepageBanners', 'homepageContent'));
    }
}
