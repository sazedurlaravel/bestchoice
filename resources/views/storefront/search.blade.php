@extends('layouts.storefront')

@section('title', $isShop ? 'সব পণ্য' : ($term ? "\"$term\" - খোঁজার ফলাফল" : 'পণ্য খুঁজুন'))

@section('content')
<div class="page">
    <div class="breadcrumb">
        <a href="{{ route('home') }}">হোম</a> / {{ $isShop ? 'সব পণ্য' : 'পণ্য খুঁজুন' }}
    </div>

    <div class="search-header">
        <form action="{{ $isShop ? route('shop') : route('search') }}" method="GET" class="search-page-form" id="storefrontSearchForm">
            <div class="search suggestion-wrap">
                <input
                    type="search"
                    name="q"
                    value="{{ $term }}"
                    placeholder="পণ্যের নাম লিখুন..."
                    autocomplete="off"
                    aria-label="পণ্য খুঁজুন"
                    aria-autocomplete="list"
                    aria-controls="searchSuggestions"
                >
                <div class="search-suggestions" id="searchSuggestions" role="listbox" hidden></div>
                <button type="submit">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> খুঁজুন
                </button>
            </div>
        </form>

        @if($term)
            <p class="search-result-info muted">
                <strong>"{{ $term }}"</strong> এর জন্য
                {{ $products->total() }} টি ফলাফল পাওয়া গেছে
            </p>
        @else
                <p class="muted">{{ $isShop ? 'আমাদের সব সক্রিয় পণ্য দেখুন।' : 'কোনো পণ্য খুঁজতে উপরের বাক্সে টাইপ করুন।' }}</p>
        @endif
    </div>

    @if($term || $isShop)
        @if($products->isNotEmpty())
            <div class="products">
                @foreach($products as $product)
                    @include('storefront.components.product-card', compact('product'))
                @endforeach
            </div>
            <div class="pagination-wrap">
                {{ $products->links() }}
            </div>
        @else
            <div class="panel empty">
                <i class="fa-solid fa-magnifying-glass" style="font-size:36px;color:var(--muted);margin-bottom:12px"></i>
                <p><strong>"{{ $term }}"</strong> এর জন্য কোনো পণ্য পাওয়া যায়নি।</p>
                <p>অন্য কোনো শব্দ দিয়ে চেষ্টা করুন অথবা <a href="{{ route('home') }}" style="color:var(--green)">হোমে ফিরে যান</a>।</p>
            </div>
        @endif
    @endif
</div>
@endsection
