@extends('layouts.storefront')
@section('title', 'শপিং কার্ট')
@section('content')
<div class="page cart-page">
    <div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / কার্ট</div>
    <h1 class="page-title">শপিং কার্ট</h1>

    @if(session('success'))
        <div class="flash success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash error">{{ session('error') }}</div>
    @endif

    @if(empty($items))
        <div class="panel empty cart-empty-state">
            <i class="fa-solid fa-cart-shopping cart-empty-icon" aria-hidden="true"></i>
            <p>আপনার কার্ট এখন খালি।</p>
            <a href="{{ route('home') }}" class="bc-buy-now cart-empty-action">কেনাকাটা শুরু করুন</a>
        </div>
    @else
        <div class="cart-layout">
            <div class="cart-items panel">
                @foreach($items as $item)
                    <div class="cart-item">
                        <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="cart-item-img">
                        <div class="cart-item-info">
                            <div class="cart-item-name">{{ $item['name'] }}</div>
                            @if(!empty($item['variant_name']))
                                <div class="muted">{{ $item['variant_name'] }}</div>
                            @endif
                            <div class="cart-item-price">৳{{ number_format($item['price'], 0) }}</div>
                            <form method="POST" action="{{ route('cart.update') }}" class="cart-qty-form">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                @if(isset($item['variant_id']))
                                    <input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">
                                @endif
                                <button type="submit" name="qty" value="{{ max(0, $item['qty'] - 1) }}" class="qty-btn" aria-label="{{ $item['name'] }} এর পরিমাণ কমান">−</button>
                                <span class="qty-val">{{ $item['qty'] }}</span>
                                <button type="submit" name="qty" value="{{ $item['qty'] + 1 }}" class="qty-btn" aria-label="{{ $item['name'] }} এর পরিমাণ বাড়ান">+</button>
                            </form>
                            <span class="cart-line-total">পণ্যের মোট: ৳{{ number_format($item['price'] * $item['qty'], 0) }}</span>
                        </div>
                        <form method="POST" action="{{ route('cart.remove') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                            @if(isset($item['variant_id']))
                                <input type="hidden" name="variant_id" value="{{ $item['variant_id'] }}">
                            @endif
                            <button type="submit" class="cart-remove" title="সরান" aria-label="{{ $item['name'] }} কার্ট থেকে সরান"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="cart-summary panel">
                <h2>অর্ডার সারাংশ</h2>
                <div class="summary-row"><span>সাবটোটাল</span><b>৳{{ number_format($subtotal, 0) }}</b></div>
                <div class="summary-row"><span>ডেলিভারি</span><b>৳{{ number_format($shippingCharge, 0) }}</b></div>
                <div class="summary-row total"><b>সর্বমোট</b><b>৳{{ number_format($total, 0) }}</b></div>
                <a href="{{ route('checkout.index') }}" class="primary cart-checkout-action">চেকআউট করুন <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>

    @endif
</div>
@endsection
