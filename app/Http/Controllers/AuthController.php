<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\User;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->canAccessAdmin() ? 'admin.dashboard' : 'account');
        }

        return view('auth.login');
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'string'], 'password' => ['required', 'string']]);
        $identifier = $credentials['email'];
        $loginCredentials = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? ['email' => $identifier, 'password' => $credentials['password'], 'is_active' => true]
            : ['mobile' => $identifier, 'password' => $credentials['password'], 'is_active' => true];

        if (! Auth::attempt($loginCredentials, $request->boolean('remember'))) {
            $adminAccount = User::query()
                ->whereIn('role', ['admin', 'staff'])
                ->where(fn ($query) => filter_var($identifier, FILTER_VALIDATE_EMAIL)
                    ? $query->where('email', $identifier)
                    : $query->where('mobile', $identifier))
                ->first();
            if ($adminAccount) {
                AuditLog::create([
                    'user_id' => $adminAccount->id,
                    'action' => 'auth.login_failed',
                    'route_name' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status_code' => 422,
                ]);
            }
            throw ValidationException::withMessages(['email' => 'ইমেইল বা পাসওয়ার্ড সঠিক নয়।']);
        }
        $request->session()->regenerate();
        $user = Auth::user();
        if ($user->canAccessAdmin()) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.login',
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => 302,
            ]);
        }
        $savedWishlist = array_values(array_unique(array_merge((array) $user->wishlist_product_ids, (array) $request->session()->get('wishlist', []))));
        $user->forceFill(['wishlist_product_ids' => $savedWishlist])->save();
        $request->session()->put('wishlist', $savedWishlist);

        return redirect()->intended(Auth::user()->canAccessAdmin() ? route('admin.dashboard') : route('account'));
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ]);

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'role' => 'customer',
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $savedWishlist = array_values(array_unique(array_merge((array) $user->wishlist_product_ids, (array) $request->session()->get('wishlist', []))));
        $user->forceFill(['wishlist_product_ids' => $savedWishlist])->save();
        $request->session()->put('wishlist', $savedWishlist);

        return redirect()->route('account')->with('success', 'আপনার অ্যাকাউন্ট তৈরি হয়েছে।');
    }

    public function account(Request $request): View
    {
        $orders = Order::query()
            ->with(['items', 'returns', 'cancellationRequests'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('auth.account', ['orders' => $orders, 'wishlistCount' => count($request->user()->wishlist_product_ids ?? [])]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile,'.$request->user()->id],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'আপনার তথ্য আপডেট হয়েছে।');
    }

    public function requestCancellation(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        abort_unless(in_array($order->status, ['pending', 'processing'], true), 422);
        OrderCancellationRequest::query()->firstOrCreate(['order_id' => $order->id, 'status' => 'requested'], ['user_id' => $request->user()->id, 'reason' => $data['reason']]);

        return back()->with('success', 'বাতিলের অনুরোধ পাঠানো হয়েছে।');
    }

    public function requestReturn(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['order_item_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:1000']]);
        $item = $order->items()->findOrFail($data['order_item_id']);
        app(OrderLifecycleService::class)->requestReturn($order, $item, $data['quantity'], $data['reason'], (string) Str::uuid(), $request->user());

        return back()->with('success', 'রিটার্ন/রিফান্ড অনুরোধ পাঠানো হয়েছে। প্রশাসক পর্যালোচনা করবেন।');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'string', 'min:8']]);
        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return back()->with('success', 'পাসওয়ার্ড পরিবর্তন হয়েছে।');
    }

    public function accountSecurity(): RedirectResponse
    {
        return redirect()->route('account')->withFragment('account-security');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user?->canAccessAdmin()) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.logout',
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => 302,
            ]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
