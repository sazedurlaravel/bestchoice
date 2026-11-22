@extends('layouts.storefront')

@section('title', 'Best Choice Online Shopping | Fashion, Gadgets, Home & Beauty')

@section('content')
    <div class="new-home" style="--home-products-desktop:{{ $homepageContent['productColumnsDesktop'] }};--home-products-mobile:{{ $homepageContent['productColumnsMobile'] }}">
        <section class="bc-hero" aria-label="Featured offers">
            <div class="bc-slides" id="bcSlides">
                @foreach($homepageBanners as $index => $banner)
                <div class="bc-slide @if($index === 0)bc-active @endif" style="--slide-bg:{{ $banner['backgroundColor'] }}">
                    <div class="bc-slide-copy">
                        @if($banner['kicker'])<span class="bc-kicker">{{ $banner['kicker'] }}</span>@endif
                        <h1>{{ $banner['headline'] }}<br><em>{{ $banner['highlight'] }}</em></h1>
                        @if($banner['description'])<p>{{ $banner['description'] }}</p>@endif
                        <a class="primary" href="{{ $banner['buttonUrl'] }}">{{ $banner['buttonText'] }}</a>
                    </div>
                    <img src="{{ $banner['imageUrl'] }}" alt="{{ $banner['headline'] }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}">
                </div>
                @endforeach
            </div>
            <button class="bc-hero-arrow bc-prev" aria-label="Previous banner" type="button">‹</button>
            <button class="bc-hero-arrow bc-next" aria-label="Next banner" type="button">›</button>
        </section>
        <div class="bc-dots" aria-label="Banner pagination">
            @foreach($homepageBanners as $index => $banner)
                <button class="@if($index === 0)selected @endif" aria-label="Banner {{ $index + 1 }}" type="button"></button>
            @endforeach
        </div>

        @foreach($frontendCollections as $collection)
            @if(count($collection['items']) > 0)
                <section class="bc-collection">
                    <div class="bc-collection-title">
                        <h2>✦ {{ $collection['title'] }} ✦</h2>
                        <small>{{ $homepageContent['collectionSubtitle'] }}</small>
                    </div>
                    <div class="bc-collection-grid">
                        @foreach($collection['items'] as $item)
                            <a class="bc-collection-card" href="{{ $item['url'] }}" aria-label="{{ $item['name'] }} পণ্য দেখুন">
                                <span class="bc-collection-count">{{ $item['count'] }}</span>
                                <span class="bc-collection-image"><img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('images/category-placeholder.svg') }}';"></span>
                                <span class="bc-collection-label">{{ $item['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if(count($frontendProducts) > 0)
            <section class="section bestsellers-section">
                <div class="sectionhead"><div><span class="section-kicker">{{ $homepageContent['featuredKicker'] }}</span><h2>{{ $homepageContent['featuredTitle'] }}</h2></div><a href="{{ route('shop') }}">সব পণ্য →</a></div>
                <div class="products">
                    @foreach(array_slice($frontendProducts, 0, 8) as $product)
                        <article class="product">
                            <div class="product-image-wrap"><a href="{{ $product['url'] }}"><img src="{{ $product['img'] }}" alt="{{ $product['name'] }}" loading="lazy"></a>@if($product['tag'])<span class="bc-product-tag">{{ $product['tag'] }}</span>@endif</div>
                            <div class="product-info">
                                <div class="muted product-category">{{ $frontendCategories[$product['cat']][0] ?? 'পণ্য' }}</div>
                                <h3><a href="{{ $product['url'] }}">{{ $product['name'] }}</a></h3>
                                <div class="product-pricing"><span class="selling-price">৳{{ number_format($product['price'], 0) }}</span>@if($product['regularPrice'])<del class="regular-price" aria-label="নিয়মিত মূল্য">৳{{ number_format($product['regularPrice'], 0) }}</del>@endif</div>
                                <div class="product-actions">
                                    @if($product['hasVariants'])
                                        <a href="{{ $product['url'] }}" class="bc-buy-now"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i><span class="bc-buy-label">অপশন বেছে নিন</span></a>
                                    @elseif($product['stock'] > 0)
                                        <form method="POST" action="{{ route('cart.add') }}" class="card-cart-form">@csrf<input type="hidden" name="product_id" value="{{ $product['id'] }}"><input type="hidden" name="qty" value="1"><input type="hidden" name="cart_request_id" value="{{ \Illuminate\Support\Str::uuid() }}"><button class="bc-buy-now" type="submit"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i><span class="bc-buy-label">অর্ডার করুন</span></button></form>
                                    @else
                                        <button class="bc-buy-now" type="button" disabled>স্টক শেষ</button>
                                    @endif
                                    @php($isWishlisted = in_array($product['id'], $wishlistIds, true))
                                    <form method="POST" action="{{ route('wishlist.toggle') }}" class="card-wishlist-form">@csrf<input type="hidden" name="product_id" value="{{ $product['id'] }}"><button class="iconbtn wish-button {{ $isWishlisted ? 'is-saved' : '' }}" type="submit" aria-label="{{ $isWishlisted ? 'পছন্দের তালিকা থেকে সরান' : 'পছন্দে যোগ করুন' }}"><i class="{{ $isWishlisted ? 'fa-solid' : 'fa-regular' }} fa-heart"></i></button></form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
