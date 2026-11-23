<?php

namespace App\Providers;

use App\Models\StoreSetting;
use App\Services\Cart\CartService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();

        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        View::composer('layouts.storefront', function ($view): void {
            $wishlistIds = array_map('intval', session('wishlist', []));
            $storeSettings = StoreSetting::query()->pluck('value', 'key')->all();
            $cart = app(CartService::class);
            $cartItems = $cart->items();
            $cartSubtotal = array_reduce($cartItems, function (float $subtotal, array $item): float {
                return $subtotal + ((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0));
            }, 0.0);

            $view->with([
                'cartCount' => $cart->count(),
                'cartItems' => $cartItems,
                'cartSubtotal' => $cartSubtotal,
                'wishlistCount' => count(array_unique($wishlistIds)),
                'storeSettings' => $storeSettings,
                'storeName' => $storeSettings['store_name'] ?? 'Best Choice Online Shopping',
            ]);
        });

        View::composer('layouts.admin', function ($view): void {
            $view->with('faviconUrl', StoreSetting::query()->where('key', 'favicon_url')->value('value'));
        });

        View::composer('layouts.storefront', function ($view): void {
            $view->with('themeSettings', StoreSetting::query()
                ->whereIn('key', [
                    'theme_primary_color',
                    'theme_accent_color',
                    'theme_background_color',
                    'header_background_color',
                    'footer_background_color',
                ])
                ->pluck('value', 'key')
                ->all());
        });
    }
}
