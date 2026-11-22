@extends('layouts.storefront')
@section('title', 'চেকআউট')
@section('content')
<div class="page">
    <div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / অর্ডার নিশ্চিত করুন</div>
    <div class="checkout-heading checkout-quick-heading">
        <div>
            <h1 class="page-title">অর্ডার নিশ্চিত করুন</h1>
            <p class="checkout-subtitle">নাম, ঠিকানা ও মোবাইল নম্বর দিয়ে অর্ডার নিশ্চিত করুন।</p>
        </div>
        <div class="checkout-heading-actions">
            <a class="checkout-back checkout-add-more" href="{{ route('shop') }}"><i class="fa-solid fa-plus"></i> আরও পণ্য যোগ করুন</a>
            <a aria-label="কার্ট খুলুন" class="checkout-back" data-cart-drawer-open href="{{ route('cart.index') }}"><i class="fa-solid fa-arrow-left"></i> কার্টে ফিরুন</a>
        </div>
    </div>

    @if($errors->any())
        <div class="flash error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('checkout.store') }}" class="checkout-layout checkout-quick">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
        <input type="hidden" name="coupon_code" value="{{ session('coupon_code') }}" id="checkoutCouponCode">
        <div class="checkout-form panel">
            <div class="checkout-simple-intro">অর্ডার কনফার্ম করতে আপনার নাম, ঠিকানা ও মোবাইল নম্বর দিয়ে “অর্ডার কনফার্ম করুন” বাটনে ক্লিক করুন।</div>
            <div class="checkout-fields">
                <label class="checkout-field checkout-field-wide">আপনার নাম <span class="req">*</span><input type="text" name="customer_name" value="{{ old('customer_name') }}" required placeholder="পূর্ণ নাম"></label>
                <label class="checkout-field">মোবাইল নম্বর <span class="req">*</span><input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" required placeholder="01XXXXXXXXX"></label>
                <label class="checkout-field checkout-field-wide">সম্পূর্ণ ঠিকানা <span class="req">*</span><textarea name="customer_address" rows="4" required placeholder="বাসা/রোড/এলাকার বিস্তারিত ঠিকানা">{{ old('customer_address') }}</textarea></label>
            </div>

            <div class="checkout-delivery-choice">
                <span class="checkout-choice-label">ডেলিভারি চার্জ <span class="req">*</span></span>
                <div class="checkout-zone-pills">
                    <button type="button" class="checkout-zone-pill @if($deliveryZone === 'inside_dhaka') is-selected @endif" data-zone-choice="inside_dhaka">ঢাকার ভিতরে — ৳{{ number_format($deliveryRates['inside_dhaka'], 0) }}</button>
                    <button type="button" class="checkout-zone-pill @if($deliveryZone === 'outside_dhaka') is-selected @endif" data-zone-choice="outside_dhaka">ঢাকার বাইরে — ৳{{ number_format($deliveryRates['outside_dhaka'], 0) }}</button>
                </div>
                <select name="delivery_zone" id="deliveryZone" class="checkout-zone-select" required data-rates='@json($deliveryRates)' aria-label="ডেলিভারি জোন">
                    <option value="inside_dhaka" @selected($deliveryZone === 'inside_dhaka')>ঢাকার ভিতরে</option>
                    <option value="outside_dhaka" @selected($deliveryZone === 'outside_dhaka')>ঢাকার বাইরে</option>
                </select>
            </div>

            <label class="payment-option checkout-payment-simple"><input type="radio" name="payment_method" value="cash_on_delivery" @checked(old('payment_method', 'cash_on_delivery') === 'cash_on_delivery')><span><i class="fa-solid fa-money-bill-wave"></i><b>ক্যাশ অন ডেলিভারি</b><small>পণ্য হাতে পেয়ে পেমেন্ট করুন</small></span><i class="fa-solid fa-circle-check payment-selected"></i></label>
            @if($onlinePaymentEnabled)
                <label class="payment-option checkout-payment-simple"><input type="radio" name="payment_method" value="sslcommerz" @checked(old('payment_method') === 'sslcommerz')><span><i class="fa-solid fa-credit-card"></i><b>অনলাইন পেমেন্ট</b><small>বিকাশ, নগদ, রকেট, কার্ড ও অন্যান্য পদ্ধতি</small></span><i class="fa-solid fa-circle-check payment-selected"></i></label>
            @endif

            <button type="submit" class="checkout-submit"><i class="fa-solid fa-cart-shopping"></i> অর্ডার কনফার্ম করুন</button>
            <p class="checkout-safe">অর্ডার পাঠানোর পর আমাদের টিম আপনার সাথে যোগাযোগ করবে।</p>
        </div>

        <aside class="cart-summary checkout-summary panel">
            <div class="checkout-summary-heading"><h2>আপনার অর্ডার</h2><span>{{ count($items) }}টি পণ্য</span></div>
            <div class="checkout-items">
                @foreach($items as $item)
                    <div class="checkout-item"><img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"><div><b>{{ $item['name'] }}</b>@if(!empty($item['variant_name']))<small class="muted">{{ $item['variant_name'] }}</small>@endif<small>{{ $item['qty'] }} × ৳{{ number_format($item['price'], 0) }}</small></div><strong>৳{{ number_format($item['price'] * $item['qty'], 0) }}</strong></div>
                @endforeach
            </div>
            <div class="coupon-box">
                @if(session('coupon_code'))<p id="appliedCoupon">কুপন <b>{{ session('coupon_code') }}</b> প্রয়োগ করা হয়েছে</p>@endif
                <label class="checkout-field" id="couponEntry" @if(session('coupon_code')) hidden @endif>কুপন কোড<input id="couponInput" value="{{ old('coupon_code') }}" maxlength="50" placeholder="কুপন কোড লিখুন"></label>
                <button type="button" class="secondary" id="applyCouponButton" data-url="{{ route('checkout.coupon.apply') }}" @if(session('coupon_code')) hidden @endif>প্রয়োগ করুন</button>
                <button type="button" class="secondary" id="removeCouponButton" data-url="{{ route('checkout.coupon.remove') }}" @if(!session('coupon_code')) hidden @endif>কুপন সরান</button>
                <p id="couponFeedback" role="status" aria-live="polite"></p>
            </div>
            <div class="checkout-totals"><div><span>সাবটোটাল</span><b id="checkoutSubtotal" data-amount="{{ $subtotal }}">৳{{ number_format($subtotal, 0) }}</b></div><div id="checkoutDiscountRow" @if(($discountAmount ?? 0) <= 0) hidden @endif><span>ছাড়</span><b id="checkoutDiscount" data-amount="{{ $discountAmount ?? 0 }}">− ৳{{ number_format($discountAmount ?? 0, 0) }}</b></div><div><span>ডেলিভারি চার্জ</span><b id="checkoutShipping">৳{{ number_format($shippingCharge, 0) }}</b></div><div class="checkout-total"><span>সর্বমোট</span><strong id="checkoutTotal">৳{{ number_format($total, 0) }}</strong></div></div>
        </aside>
    </form>
</div>
@endsection
