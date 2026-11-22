<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CourierController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderReturnController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\InformationController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\TrackOrderController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Support\Facades\Route;

// Laravel Boost posts browser logs here; keep direct browser visits from becoming
// an unhelpful 405 while preserving the POST-only log ingestion route.
Route::get('/_boost/browser-logs', fn () => response()->json([
    'status' => 'ready',
    'message' => 'Browser logs are accepted via POST.',
]));

// ---- Storefront ----
Route::get('/', HomeController::class)->name('home');
Route::get('/shop', SearchController::class)->name('shop');
Route::get('/search', SearchController::class)->name('search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
Route::get('/track', TrackOrderController::class)->name('track');
Route::get('/about-us', [InformationController::class, 'about'])->name('about');
Route::get('/contact', [InformationController::class, 'contact'])->name('contact');
Route::get('/faq', [InformationController::class, 'faq'])->name('faq');
Route::get('/policy', [InformationController::class, 'policy'])->name('policy');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');

// Checkout & Order
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon.apply');
Route::delete('/checkout/coupon', [CheckoutController::class, 'removeCoupon'])->name('checkout.coupon.remove');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->block(30, 30);
Route::post('/payments/sslcommerz/success', [PaymentController::class, 'success'])->name('payments.sslcommerz.success');
Route::post('/payments/sslcommerz/fail', [PaymentController::class, 'fail'])->name('payments.sslcommerz.fail');
Route::post('/payments/sslcommerz/cancel', [PaymentController::class, 'cancel'])->name('payments.sslcommerz.cancel');
Route::post('/payments/sslcommerz/ipn', [PaymentController::class, 'ipn'])->name('payments.sslcommerz.ipn');
Route::get('/order/confirmation', fn () => session('last_order_number')
    ? view('storefront.checkout.confirmation')
    : redirect()->route('home')
)->name('order.confirmation');

// ---- Auth ----
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:login')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:login')->name('password.update');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'storeRegistration'])->middleware('throttle:login')->name('register.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function (): void {
    Route::get('/account', [AuthController::class, 'account'])->name('account');
    Route::patch('/account/profile', [AuthController::class, 'updateProfile'])->name('account.profile.update');
    Route::patch('/account/password', [AuthController::class, 'updatePassword'])->name('account.password.update');
    Route::post('/account/orders/{order}/cancel-request', [AuthController::class, 'requestCancellation'])->name('account.orders.cancel-request');
    Route::post('/account/orders/{order}/return-request', [AuthController::class, 'requestReturn'])->name('account.orders.return-request');
});

// ---- Admin ----
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::middleware('permission:homepage.manage')->group(function (): void {
        Route::get('/homepage', [HomepageController::class, 'index'])->name('homepage');
        Route::post('/homepage/banners', [HomepageController::class, 'store'])->name('homepage.banners.store');
        Route::put('/homepage/banners/{banner}', [HomepageController::class, 'update'])->name('homepage.banners.update');
        Route::delete('/homepage/banners/{banner}', [HomepageController::class, 'destroy'])->name('homepage.banners.destroy');
    });

    // Products
    Route::middleware('permission:products.manage')->group(function (): void {
        Route::get('/products', [AdminProductController::class, 'index'])->name('products');
        Route::get('/products/export', [AdminProductController::class, 'export'])->name('products.export');
        Route::post('/products/import', [AdminProductController::class, 'import'])->name('products.import');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
        Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
    });

    // Categories
    Route::middleware('permission:categories.manage')->group(function (): void {
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Inventory
    Route::middleware('permission:inventory.manage')->group(function (): void {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    });
    Route::middleware('permission:purchases.manage')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::patch('/suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    });

    // Orders
    Route::middleware('permission:orders.manage')->group(function (): void {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::patch('/orders/{order}/cancellation-requests/{cancellationRequest}', [OrderController::class, 'updateCancellationRequest'])->name('orders.cancellation-requests.update');
        Route::post('/orders/{order}/returns', [OrderReturnController::class, 'store'])->name('orders.returns.store');
        Route::patch('/orders/{order}/returns/{orderReturn}', [OrderReturnController::class, 'update'])->name('orders.returns.update');
        Route::post('/orders/{order}/steadfast', [CourierController::class, 'book'])->name('orders.steadfast.book');
        Route::post('/orders/bulk-update', [OrderController::class, 'bulkUpdate'])->name('orders.bulk-update');
        Route::patch('/orders/{order}/note', [OrderController::class, 'updateNote'])->name('orders.note');
        Route::patch('/orders/{order}/assign', [OrderController::class, 'updateAssignment'])->name('orders.assign');
        Route::get('/orders/{order}/print', [OrderController::class, 'printInvoice'])->name('orders.print');
        Route::post('/orders/{order}/review/confirm', [OrderController::class, 'confirmReview'])->name('orders.review.confirm');
        Route::post('/orders/{order}/review/rerun', [OrderController::class, 'rerunReview'])->name('orders.review.rerun');
        Route::post('/orders/{order}/review/mark-refusal', [OrderController::class, 'markRefusal'])->name('orders.review.refusal');
    });
    Route::post('/shipments/{shipment}/refresh', [CourierController::class, 'refresh'])->middleware('permission:orders.manage')->name('shipments.refresh');
    Route::middleware('permission:payments.manage')->group(function (): void {
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments');
        Route::patch('/payments/settings', [AdminPaymentController::class, 'updateSettings'])->name('payments.settings');
    });

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers');
    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme.update');
        Route::delete('/settings/theme', [SettingsController::class, 'resetTheme'])->name('settings.theme.reset');
        Route::get('/settings/courier/steadfast', [CourierController::class, 'edit'])->name('settings.courier.steadfast');
        Route::put('/settings/courier/steadfast', [CourierController::class, 'update'])->name('settings.courier.steadfast.update');
        Route::post('/settings/courier/steadfast/test', [CourierController::class, 'test'])->middleware('throttle:6,1')->name('settings.courier.steadfast.test');
        Route::put('/settings/cod-review', [SettingsController::class, 'updateCodReview'])->name('settings.cod-review.update');
        Route::put('/homepage/layout', [SettingsController::class, 'updateHomepageLayout'])->name('homepage.layout.update');
    });
    Route::middleware('permission:coupons.manage')->group(function (): void {
        Route::get('/coupons', [CouponController::class, 'index'])->name('coupons');
        Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::patch('/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');
        Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    });
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit-logs');
    Route::middleware('admin-only')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });
});
