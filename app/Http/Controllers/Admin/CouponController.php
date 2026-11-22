<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons.index', ['coupons' => Coupon::query()->latest()->paginate(30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'minimum_subtotal' => ['required', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            return back()->withErrors(['value' => 'শতকরা ছাড় ১০০-এর বেশি হতে পারবে না।'])->withInput();
        }
        $data['code'] = strtoupper($data['code']);
        Coupon::create($data);

        return back()->with('success', 'কুপন তৈরি হয়েছে।');
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', 'কুপনের অবস্থা আপডেট হয়েছে।');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if (Order::query()->where('coupon_code', $coupon->code)->exists()) {
            $coupon->update(['is_active' => false]);

            return back()->with('success', 'অর্ডারে ব্যবহৃত কুপন ইতিহাসের জন্য রেখে নিষ্ক্রিয় করা হয়েছে।');
        }
        $coupon->delete();

        return back()->with('success', 'কুপন মুছে ফেলা হয়েছে।');
    }
}
