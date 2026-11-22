@extends('layouts.storefront')

@section('title', 'আমাদের সম্পর্কে | '.$storeName)

@section('content')
<div class="page info-page">
    <div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / আমাদের সম্পর্কে</div>
    <section class="info-hero">
        <span class="section-kicker">BEST CHOICE ONLINE SHOPPING</span>
        <h1>আপনার প্রতিদিনের কেনাকাটার নির্ভরযোগ্য ঠিকানা</h1>
        <p>{{ $storeSettings['store_description'] ?? 'ফ্যাশন, ইলেকট্রনিক্স, হোম ও কিচেন, বিউটি এবং অ্যাকসেসরিজ—প্রয়োজনীয় পণ্য সহজে এবং নির্ভরতার সঙ্গে পৌঁছে দিতে আমরা কাজ করি।' }}</p>
    </section>

    <section class="info-content-grid">
        <article class="panel info-card"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><h2>সহজ কেনাকাটা</h2><p>পছন্দের পণ্য খুঁজে দেখা, কার্টে যোগ করা এবং অর্ডার করা—সবকিছু সহজ ও পরিষ্কারভাবে সাজানো।</p></article>
        <article class="panel info-card"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i><h2>বিশ্বাসযোগ্য সেবা</h2><p>সঠিক পণ্যের তথ্য, স্বচ্ছ মূল্য এবং অর্ডারের প্রতিটি ধাপে সহযোগিতা দেওয়াই আমাদের লক্ষ্য।</p></article>
        <article class="panel info-card"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i><h2>দেশজুড়ে ডেলিভারি</h2><p>বাংলাদেশের বিভিন্ন প্রান্তে আপনার অর্ডার নিরাপদে পৌঁছে দেওয়ার জন্য আমরা কাজ করি।</p></article>
    </section>

    <section class="info-callout">
        <div><span class="section-kicker">STAY CONNECTED</span><h2>কোনো প্রশ্ন আছে?</h2><p>আমাদের টিম আপনার পণ্য, অর্ডার বা ডেলিভারি সংক্রান্ত প্রশ্নে সাহায্য করতে প্রস্তুত।</p></div>
        <a class="primary" href="{{ route('contact') }}">যোগাযোগ করুন <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </section>
</div>
@endsection
