@extends('layouts.storefront')
@section('title', 'পেমেন্ট স্ট্যাটাস')
@section('content')
@php($messages = ['paid'=>['পেমেন্ট সফল হয়েছে','আপনার পেমেন্ট যাচাই করা হয়েছে। অর্ডারটি গ্রহণ করা হয়েছে।','fa-circle-check'], 'failed'=>['পেমেন্ট সম্পন্ন হয়নি','পেমেন্ট ব্যর্থ হয়েছে। কার্ট অক্ষত আছে; চেকআউট থেকে আবার চেষ্টা করুন।','fa-circle-xmark'], 'cancelled'=>['পেমেন্ট বাতিল হয়েছে','আপনি পেমেন্ট বাতিল করেছেন। কার্ট অক্ষত আছে; চাইলে আবার চেষ্টা করুন।','fa-circle-xmark'], 'review'=>['পেমেন্ট যাচাই চলছে','পেমেন্টটি অতিরিক্ত যাচাইয়ের জন্য রাখা হয়েছে। যাচাই শেষ হলে অর্ডার আপডেট হবে।','fa-clock'])
@php([$heading, $message, $icon] = $messages[$outcome] ?? $messages['review'])
<div class="page"><section class="confirmation-box panel" style="max-width:560px;margin:40px auto;text-align:center;padding:40px 32px">
    <div style="width:72px;height:72px;background:var(--green);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px"><i class="fa-solid {{ $icon }}" style="font-size:32px;color:white"></i></div>
    <h1 style="font-size:24px;margin-bottom:8px">{{ $heading }}</h1><p class="muted">{{ $message }}</p>
    <div style="background:#f5f9f7;border-radius:12px;padding:16px 20px;margin:20px 0"><div class="muted small">অর্ডার নম্বর</div><b>{{ $order->order_number }}</b><div class="muted small" style="margin-top:8px">লেনদেন: {{ $payment->transaction_id }}</div><div>৳{{ number_format((float)$payment->amount, 2) }}</div></div>
    @if(in_array($outcome, ['failed','cancelled'], true))<a class="bc-buy-now" href="{{ route('checkout.index') }}">আবার পেমেন্ট করুন</a>@elseif(auth()->check())<a class="bc-buy-now" href="{{ route('account') }}">আমার অর্ডার দেখুন</a>@else<a class="bc-buy-now" href="{{ route('home') }}">হোমে ফিরুন</a>@endif
</section></div>
@endsection
