@extends('layouts.storefront')

@section('content')
<div class="page account-page">
    @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="breadcrumb">হোম / আমার অ্যাকাউন্ট</div>
    <div class="sectionhead account-heading"><div><p class="section-kicker">MY ACCOUNT</p><h1 class="page-title">আমার অ্যাকাউন্ট</h1><p class="muted">স্বাগতম, {{ auth()->user()->name }}। আপনার অর্ডার ও তথ্য এক জায়গায় দেখুন।</p></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit"><i class="fa-solid fa-right-from-bracket"></i> লগআউট</button></form></div>
    <div class="account-grid">
        <form class="panel" method="POST" action="{{ route('account.profile.update') }}"><h2>ব্যক্তিগত তথ্য</h2><p class="muted">আপনার ডেলিভারি যোগাযোগের তথ্য আপডেট রাখুন।</p>@csrf @method('PATCH')<label>নাম<input class="field" type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required></label>@error('name')<small class="error">{{ $message }}</small>@enderror<label>ইমেইল<input class="field" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required></label>@error('email')<small class="error">{{ $message }}</small>@enderror<label>মোবাইল<input class="field" type="tel" name="mobile" value="{{ old('mobile', auth()->user()->mobile) }}"></label>@error('mobile')<small class="error">{{ $message }}</small>@enderror<button class="primary" type="submit">তথ্য সংরক্ষণ করুন</button></form>
        <section class="panel"><div class="sectionhead"><h2>আমার অর্ডার</h2><a href="{{ route('shop') }}">শপিং করুন →</a></div>
            @forelse($orders as $order)
                <div class="account-order"><div><b>{{ $order->order_number }}</b><small>{{ $order->created_at->format('d M Y') }}</small></div><div><span class="pill">{{ $order->status_label }}</span><b>৳{{ number_format((float) $order->total, 0) }}</b></div></div>
                @php($cancelRequest = $order->cancellationRequests->sortByDesc('created_at')->first())
                @if(in_array($order->status, ['pending','processing'], true) && !($cancelRequest?->status === 'requested'))
                    <form method="POST" action="{{ route('account.orders.cancel-request', $order) }}" class="account-request-form">@csrf<label>বাতিলের কারণ<input class="field" name="reason" maxlength="1000" required></label><button class="secondary" type="submit">বাতিল অনুরোধ</button></form>
                @elseif($cancelRequest)<p class="muted">বাতিল অনুরোধ: {{ $cancelRequest->status === 'requested' ? 'পর্যালোচনাধীন' : ($cancelRequest->status === 'approved' ? 'অনুমোদিত' : 'প্রত্যাখ্যাত') }}</p>@endif
                @if($order->status === 'delivered')
                    @foreach($order->items as $item)
                        @php($returnRequest = $order->returns->firstWhere('order_item_id', $item->id))
                        @if(!$returnRequest || in_array($returnRequest->status, ['rejected','completed'], true))
                            <form method="POST" action="{{ route('account.orders.return-request', $order) }}" class="account-request-form">@csrf<input type="hidden" name="order_item_id" value="{{ $item->id }}"><b>{{ $item->product_name }}{{ $item->variant_name ? ' · '.$item->variant_name : '' }}</b><label>পরিমাণ<input class="field" type="number" name="quantity" min="1" max="{{ $item->quantity }}" value="1" required></label><label>রিটার্ন/রিফান্ডের কারণ<input class="field" name="reason" maxlength="1000" required></label><button class="secondary" type="submit">রিটার্ন / রিফান্ড অনুরোধ</button></form>
                        @else<p class="muted">{{ $item->product_name }} — রিটার্ন অনুরোধ: {{ $returnRequest->status }}</p>@endif
                    @endforeach
                @endif
            @empty<div class="empty">এখনও কোনো অর্ডার নেই।<br><a href="{{ route('shop') }}">পণ্য দেখুন →</a></div>@endforelse
        </section>
    </div>
    <div class="account-security-grid" id="account-security">
        <section class="panel"><h2>নিরাপত্তা</h2><form method="POST" action="{{ route('account.password.update') }}">@csrf @method('PATCH')<label>বর্তমান পাসওয়ার্ড<input class="field" type="password" name="current_password" required autocomplete="current-password"></label><label>নতুন পাসওয়ার্ড<input class="field" type="password" name="password" minlength="8" required autocomplete="new-password"></label><label>নতুন পাসওয়ার্ড নিশ্চিত করুন<input class="field" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password"></label><button class="primary" type="submit">পাসওয়ার্ড পরিবর্তন</button></form></section>
        <section class="panel"><h2>পছন্দের তালিকা</h2><p>সংরক্ষিত পণ্য: {{ $wishlistCount }}</p><a class="primary" href="{{ route('wishlist') }}">উইশলিস্ট দেখুন</a></section>
    </div>
</div>
@endsection
