@extends('layouts.storefront')

@section('title', 'যোগাযোগ | '.$storeName)

@section('content')
<div class="page info-page">
    <div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / যোগাযোগ</div>
    <div class="info-heading"><span class="section-kicker">{{ $storeSettings['contact_kicker'] ?? 'GET IN TOUCH' }}</span><h1 class="page-title">{{ $storeSettings['contact_title'] ?? 'আমাদের সঙ্গে যোগাযোগ করুন' }}</h1><p class="muted">{{ $storeSettings['contact_description'] ?? 'পণ্য, অর্ডার বা ডেলিভারি বিষয়ে সহায়তার জন্য নিচের যেকোনো মাধ্যমে যোগাযোগ করুন।' }}</p></div>
    <section class="contact-grid">
        <div class="panel contact-card"><i class="fa-solid fa-phone" aria-hidden="true"></i><h2>ফোন</h2>@if(!empty($storeSettings['phone']))<a href="tel:{{ $storeSettings['phone'] }}">{{ $storeSettings['phone'] }}</a>@else<p class="muted">ফোন নম্বর শীঘ্রই যোগ করা হবে।</p>@endif</div>
        <div class="panel contact-card"><i class="fa-solid fa-envelope" aria-hidden="true"></i><h2>ইমেইল</h2>@if(!empty($storeSettings['store_email']))<a href="mailto:{{ $storeSettings['store_email'] }}">{{ $storeSettings['store_email'] }}</a>@else<p class="muted">ইমেইল ঠিকানা শীঘ্রই যোগ করা হবে।</p>@endif</div>
        <div class="panel contact-card"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><h2>ঠিকানা</h2><p>{{ $storeSettings['store_address'] ?? 'বাংলাদেশ' }}</p></div>
    </section>
    <section class="panel contact-help"><h2>{{ $storeSettings['contact_help_title'] ?? 'অর্ডার সংক্রান্ত সহায়তা' }}</h2><p>{{ $storeSettings['contact_help_text'] ?? 'অর্ডার নম্বর হাতে রাখলে আমাদের টিম দ্রুত সহায়তা করতে পারবে। আপনার অর্ডারের অবস্থা জানতে অর্ডার ট্র্যাকিং পেজ ব্যবহার করুন।' }}</p><a class="primary" href="{{ route('track') }}">অর্ডার ট্র্যাক করুন <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></section>
</div>
@endsection
