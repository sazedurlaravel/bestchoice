@extends('layouts.storefront')

@section('title', 'নীতিমালা | '.$storeName)

@section('content')
<div class="page info-page">
    <div class="breadcrumb"><a href="{{ route('home') }}">হোম</a> / নীতিমালা</div>
    <section class="info-hero">
        <span class="section-kicker">BEST CHOICE ONLINE SHOPPING</span>
        <h1>নীতিমালা</h1>
        <p>আমাদের সেবা ব্যবহারের শর্ত ও নীতিমালা নিচে বিস্তারিত দেওয়া হলো।</p>
    </section>

    <section class="info-content-grid" style="grid-template-columns: 1fr;">
        <article class="panel info-card">
            <h2><i class="fa-solid fa-file-contract" aria-hidden="true"></i> সেবার শর্তাবলি</h2>
            <p>এই ওয়েবসাইট ব্যবহার করলে আপনি আমাদের শর্তাবলি মেনে নিচ্ছেন বলে গণ্য হবে। আমরা যেকোনো সময় শর্তাবলি পরিবর্তন করার অধিকার সংরক্ষণ করি।</p>
        </article>

        <article class="panel info-card">
            <h2><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> গোপনীয়তা নীতি</h2>
            <p>আপনার ব্যক্তিগত তথ্য (নাম, ঠিকানা, ফোন নম্বর) শুধুমাত্র অর্ডার প্রক্রিয়াকরণ ও ডেলিভারির উদ্দেশ্যে ব্যবহৃত হবে। তৃতীয় পক্ষের কাছে আপনার তথ্য বিক্রি বা শেয়ার করা হবে না।</p>
        </article>

        <article class="panel info-card">
            <h2><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> রিটার্ন নীতি</h2>
            <p>পণ্যে ত্রুটি থাকলে বা ভুল পণ্য পৌঁছালে পণ্য পাওয়ার পরে দ্রুত আমাদের সাথে যোগাযোগ করুন। রিটার্নের বিস্তারিত নিয়মকানুন জানতে আমাদের টিমের সাথে কথা বলুন।</p>
        </article>

        <article class="panel info-card">
            <h2><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> ডেলিভারি নীতি</h2>
            <p>ঢাকার ভেতরে ও বাইরে আলাদা ডেলিভারি চার্জ প্রযোজ্য। চেকআউটের সময় আপনার এলাকা অনুযায়ী সঠিক চার্জ দেখানো হবে। ডেলিভারির আনুমানিক সময় এলাকাভেদে পরিবর্তিত হতে পারে।</p>
        </article>

        <article class="panel info-card">
            <h2><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> বিরোধ নিষ্পত্তি</h2>
            <p>কোনো সমস্যা বা অভিযোগের ক্ষেত্রে প্রথমে আমাদের সাথে সরাসরি যোগাযোগ করুন। আমরা আলোচনার মাধ্যমে সমাধানের চেষ্টা করব।</p>
        </article>
    </section>

    <section class="info-callout">
        <div><span class="section-kicker">প্রশ্ন আছে?</span><h2>আমাদের সাথে কথা বলুন</h2><p>নীতিমালা সম্পর্কে কোনো প্রশ্ন থাকলে সরাসরি আমাদের সাথে যোগাযোগ করুন।</p></div>
        <a class="primary" href="{{ route('contact') }}">যোগাযোগ করুন <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </section>
</div>
@endsection
