@extends('layouts.storefront')
@section('title', 'অর্ডার ট্র্যাকিং')
@section('content')
<div class="page"><div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / অর্ডার ট্র্যাকিং</div><h1 class="page-title">অর্ডার ট্র্যাকিং</h1><div class="panel" style="max-width:650px"><form method="GET" action="{{ route('track') }}"><label>অর্ডার নম্বর<input class="field" name="order_number" value="{{ request('order_number') }}" placeholder="BC-XXXXXXXX" required></label><button class="primary" type="submit">ট্র্যাক করুন</button></form>@if(request()->filled('order_number'))<div style="margin-top:24px">@if($order)<p><b>{{ $order->order_number }}</b> · {{ $order->status_label }}</p><p class="muted">মোট: ৳{{ number_format($order->total, 0) }}</p>@else<p class="muted">এই অর্ডার নম্বরের কোনো অর্ডার পাওয়া যায়নি।</p>@endif</div>@endif</div></div>
@endsection
