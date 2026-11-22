@extends('layouts.storefront')
@section('title', 'অর্ডার নিশ্চিত হয়েছে')
@section('content')
<div class="page">
    <div class="confirmation-box panel" style="max-width:520px;margin:40px auto;text-align:center;padding:40px 32px">
        <div style="width:72px;height:72px;background:var(--green);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
            <i class="fa-solid fa-check" style="font-size:32px;color:white"></i>
        </div>
        <h1 style="font-size:24px;margin-bottom:8px">ধন্যবাদ আপনার অর্ডারের জন্য!</h1>
        <p class="muted" style="margin-bottom:20px">আপনার অর্ডার সফলভাবে গ্রহণ করা হয়েছে।</p>
        @if(session('last_order_number'))
            <div style="background:#f5f9f7;border-radius:12px;padding:16px 20px;margin-bottom:24px">
                <div class="muted small">অর্ডার নম্বর</div>
                <div style="font-size:22px;font-weight:800;color:var(--green);font-family:monospace">{{ session('last_order_number') }}</div>
            </div>
        @endif
        <p style="color:#4a5e53;line-height:1.75;margin-bottom:28px">
            আমাদের টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে এবং ডেলিভারির ব্যবস্থা করবে।
        </p>
        <a href="{{ route('home') }}" class="bc-buy-now" style="display:inline-block">
            <i class="fa-solid fa-arrow-left"></i> হোমে ফিরুন
        </a>
    </div>
</div>
@endsection
