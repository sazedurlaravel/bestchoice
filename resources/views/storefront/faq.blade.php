@extends('layouts.storefront')

@section('title', 'সাধারণ প্রশ্ন | '.$storeName)

@section('content')
<div class="page info-page">
    <div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / সাধারণ প্রশ্ন</div>
    <section class="info-hero">
        <span class="section-kicker">BEST CHOICE ONLINE SHOPPING</span>
        <h1>সাধারণ প্রশ্ন</h1>
        <p>আমাদের সেবা ও অর্ডার সম্পর্কিত সচরাচর জিজ্ঞাসার উত্তর এখানে পাবেন।</p>
    </section>

    <section class="info-content-grid" style="grid-template-columns: 1fr;">
        <article class="panel info-card">
            <h2><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> অর্ডার করা</h2>
            <dl>
                <dt>কীভাবে অর্ডার করব?</dt>
                <dd>পছন্দের পণ্য কার্টে যোগ করুন, তারপর চেকআউটে আপনার নাম, ঠিকানা ও ফোন নম্বর দিয়ে অর্ডার নিশ্চিত করুন।</dd>

                <dt>অর্ডার করার পর কী হবে?</dt>
                <dd>অর্ডার নিশ্চিত হলে আমাদের টিম আপনার সাথে যোগাযোগ করবে এবং পণ্য প্রস্তুত করে ডেলিভারির ব্যবস্থা করবে।</dd>

                <dt>অর্ডার বাতিল করা যাবে কি?</dt>
                <dd>অর্ডার প্রক্রিয়া শুরু হওয়ার আগে বাতিল করা সম্ভব। বিস্তারিত জানতে আমাদের সাথে যোগাযোগ করুন।</dd>
            </dl>
        </article>

        <article class="panel info-card">
            <h2><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> ডেলিভারি</h2>
            <dl>
                <dt>ডেলিভারি চার্জ কত?</dt>
                <dd>ঢাকার ভেতরে ডেলিভারি চার্জ ৳{{ number_format($storeSettings['delivery_charge_inside_dhaka'] ?? 60) }} এবং ঢাকার বাইরে ৳{{ number_format($storeSettings['delivery_charge_outside_dhaka'] ?? 120) }}।</dd>

                <dt>কত দিনের মধ্যে পাব?</dt>
                <dd>সাধারণত ঢাকার ভেতরে ১–৩ কার্যদিবস এবং ঢাকার বাইরে ৩–৫ কার্যদিবসের মধ্যে পৌঁছে যায়। পরিস্থিতিভেদে সময় কম-বেশি হতে পারে।</dd>
            </dl>
        </article>

        <article class="panel info-card">
            <h2><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> পেমেন্ট</h2>
            <dl>
                <dt>কোন পদ্ধতিতে পেমেন্ট করা যাবে?</dt>
                <dd>ক্যাশ অন ডেলিভারি (COD) গ্রহণযোগ্য। অন্যান্য পেমেন্ট অপশনের জন্য আমাদের সাথে যোগাযোগ করুন।</dd>
            </dl>
        </article>

        <article class="panel info-card">
            <h2><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> রিটার্ন ও রিফান্ড</h2>
            <dl>
                <dt>পণ্য ফেরত দেওয়া যাবে কি?</dt>
                <dd>পণ্যে সমস্যা থাকলে অথবা ভুল পণ্য পৌঁছালে আমাদের সাথে যোগাযোগ করুন। আমরা যথাসাধ্য সমাধানের চেষ্টা করব।</dd>
            </dl>
        </article>
    </section>

    <section class="info-callout">
        <div><span class="section-kicker">আরও প্রশ্ন?</span><h2>আমাদের সাথে কথা বলুন</h2><p>উপরে উত্তর না পেলে সরাসরি আমাদের টিমের সাথে যোগাযোগ করুন।</p></div>
        <a class="primary" href="{{ route('contact') }}">যোগাযোগ করুন <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </section>
</div>
@endsection
