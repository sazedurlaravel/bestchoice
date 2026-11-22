@extends('layouts.storefront')

@section('title', $product->name)

@section('content')
@php
    $hasVariants = $product->variants->isNotEmpty();
    $activeVariants = $product->variants->where('is_active', true)->values();
    $isAvailable = $hasVariants
        ? $product->stock_quantity == 0 && $activeVariants->contains(fn ($variant) => $variant->combination_key !== null && $variant->stock_quantity > 0)
        : $product->stock_quantity > 0;
    $isWishlisted = in_array($product->id, array_map('intval', session('wishlist', [])), true);
    $variantColors = $activeVariants->filter(fn ($variant) => filled($variant->color))->unique('color')->values();
    $variantSizes = $activeVariants->filter(fn ($variant) => filled($variant->size))->unique('size')->values();
    $colorSwatches = ['কালো' => '#202523', 'সাদা' => '#f4f4ee', 'সবুজ' => '#235d45', 'লাল' => '#bd5b5b', 'নীল' => '#416b9b', 'বাদামি' => '#986b4b'];
    $galleryPhotos = array_values(array_unique(array_merge([$product->display_image], $product->images->pluck('image_path')->all())));
    $detailVariantData = $activeVariants->map(fn ($variant) => [
        'id' => $variant->id,
        'color' => $variant->color,
        'size' => $variant->size,
        'sku' => $variant->sku,
        'price' => (float) ($variant->price_override ?? $product->selling_price),
        'regular_price' => ($variant->regular_price ?? $product->regular_price) === null ? null : (float) ($variant->regular_price ?? $product->regular_price),
        'stock' => (int) $variant->stock_quantity,
    ])->values();
    $detailProductData = ['has_variants' => $hasVariants, 'stock' => (int) $product->stock_quantity, 'price' => (float) $product->selling_price, 'regular_price' => $product->regular_price === null ? null : (float) $product->regular_price];
    $storePhone = trim((string) ($storeSettings['phone'] ?? ''));
    $whatsappPhone = preg_replace('/\D+/', '', $storePhone);
    if (str_starts_with($whatsappPhone, '0')) {
        $whatsappPhone = '88'.$whatsappPhone;
    }
@endphp

<div class="page detail-page" data-product-detail>
    <div class="breadcrumb">
        <a href="{{ route('home') }}">হোম</a>
        / <a href="{{ route('search') }}">সব পণ্য</a>
        / {{ $product->name }}
    </div>

    @if($errors->any())
        <div class="flash error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="detail-premium">
        <section class="gallery-section" aria-label="পণ্যের ছবি">
            <div class="detail-main-frame">
                <img id="detailMainPhoto" src="{{ $galleryPhotos[0] }}" alt="{{ $product->name }}" data-detail-zoom>
                <span class="gallery-badge">{{ $product->is_featured ? 'জনপ্রিয়' : 'Best Choice' }}</span>
                <button class="gallery-zoom" type="button" data-detail-zoom-button aria-label="ছবি বড় করে দেখুন"><i class="fa-solid fa-expand"></i></button>
                <button class="gallery-arrow gallery-left" type="button" data-gallery-move="-1" aria-label="আগের ছবি"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="gallery-arrow gallery-right" type="button" data-gallery-move="1" aria-label="পরের ছবি"><i class="fa-solid fa-chevron-right"></i></button>
                <span class="gallery-counter" id="galleryCounter">1 / {{ count($galleryPhotos) }}</span>
            </div>
            <div class="detail-thumbs">
                @foreach($galleryPhotos as $index => $photo)
                    <button class="detail-thumb @if($index === 0) selected @endif" type="button" data-gallery-index="{{ $index }}" aria-label="ছবি {{ $index + 1 }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                        <img src="{{ $photo }}" alt="{{ $product->name }} — ছবি {{ $index + 1 }}" loading="lazy">
                    </button>
                @endforeach
            </div>
            <p class="gallery-hint"><i class="fa-solid fa-magnifying-glass-plus"></i> বড় করে দেখতে ছবিতে ক্লিক করুন</p>
            @if($product->full_description || $product->short_description)
                <div class="detail-description gallery-description">{!! $product->full_description ?: e($product->short_description) !!}</div>
            @endif
        </section>

        <section class="detail-buy-panel">
            <div class="detail-category">{{ $product->category->name }} <span class="detail-demo-label">Best Choice</span></div>
            <h1>{{ $product->name }}</h1>

            <div class="detail-price-line">
                <strong id="detailPrice">{{ $product->formatted_selling_price }}</strong>
                @if($product->regular_price !== null)
                    <del id="detailRegularPrice">{{ $product->formatted_regular_price }}</del>
                @endif
                @if($product->has_discount)
                    <span class="detail-discount">{{ round((1 - ((float) $product->selling_price / (float) $product->regular_price)) * 100) }}% ছাড়</span>
                @endif
            </div>

            @if($hasVariants && ($variantColors->isNotEmpty() || $variantSizes->isNotEmpty()))
                @if($variantColors->isNotEmpty())
                    <div class="variant-block">
                        <div class="variant-label"><b>রং নির্বাচন করুন</b><span data-selected-color>একটি রং বেছে নিন</span></div>
                        <div class="variant-colors">
                            @foreach($variantColors as $variant)
                                <button type="button" class="color-option" data-detail-color="{{ $variant->color }}" aria-label="{{ $variant->color }}" aria-pressed="false">
                                    <span style="background:{{ $colorSwatches[$variant->color] ?? '#d6dfd8' }}"></span><small>{{ $variant->color }}</small>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($variantSizes->isNotEmpty())
                    <div class="variant-block">
                        <div class="variant-label"><b>সাইজ নির্বাচন করুন</b><span data-selected-size>সাইজ বেছে নিন</span></div>
                        <div class="variant-sizes">
                            @foreach($variantSizes as $variant)
                                <button type="button" class="size-option" data-detail-size="{{ $variant->size }}" aria-pressed="false">{{ $variant->size }}</button>
                            @endforeach
                        </div>
                        <small class="variant-tip">স্টক নেই এমন সাইজ নির্বাচন করা যাবে না</small>
                    </div>
                @endif
            @endif

            <div class="detail-stock-row">
                <span><i class="fa-solid fa-box"></i> <span id="detailStock">{{ $isAvailable ? 'স্টকে আছে' : 'স্টক নেই' }}</span></span>
                <span>SKU: <b id="detailSku">{{ $product->sku }}</b></span>
            </div>

            <div class="detail-quantity-line">
                <b>পরিমাণ</b>
                <div class="detail-qty">
                    <button type="button" data-detail-quantity="-1" aria-label="পরিমাণ কমান">−</button>
                    <span id="detailQuantity">1</span>
                    <button type="button" data-detail-quantity="1" aria-label="পরিমাণ বাড়ান">+</button>
                </div>
            </div>

            <div class="detail-buttons">
                @if($isAvailable)
                    <form method="POST" action="{{ route('cart.add') }}" class="detail-purchase-form" data-detail-purchase-form>
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="qty" value="1" data-detail-qty-input>
                        <input type="hidden" name="variant_id" value="" data-detail-variant-input>
                        <input type="hidden" name="cart_request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <button class="detail-purchase detail-add" type="submit" @disabled($hasVariants)><i class="fa-solid fa-cart-plus"></i> কার্টে যোগ করুন</button>
                    </form>
                    <form method="POST" action="{{ route('cart.add') }}" class="detail-purchase-form" data-detail-purchase-form>
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="qty" value="1" data-detail-qty-input>
                        <input type="hidden" name="variant_id" value="" data-detail-variant-input>
                        <input type="hidden" name="redirect_to" value="checkout">
                        <input type="hidden" name="cart_request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <button class="detail-purchase detail-buy" type="submit" @disabled($hasVariants)><i class="fa-solid fa-bag-shopping"></i> এখনই কিনুন</button>
                    </form>
                @else
                    <button class="detail-purchase detail-buy" type="button" disabled>স্টক নেই</button>
                @endif
            </div>

            @if($storePhone !== '')
                <div class="detail-contact-actions">
                    <a href="tel:{{ preg_replace('/\D+/', '', $storePhone) }}"><i class="fa-solid fa-phone" aria-hidden="true"></i><span>{{ $storePhone }}</span></a>
                    @if($whatsappPhone !== '')<a href="https://wa.me/{{ $whatsappPhone }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>{{ $storePhone }}</span></a>@endif
                </div>
            @endif

            <form method="POST" action="{{ route('wishlist.toggle') }}" class="detail-wishlist-form">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <button class="detail-wishlist @if($isWishlisted) is-saved @endif" type="submit">
                    <i class="{{ $isWishlisted ? 'fa-solid' : 'fa-regular' }} fa-heart" aria-hidden="true"></i>
                    {{ $isWishlisted ? 'পছন্দের তালিকা থেকে সরান' : 'পছন্দের তালিকায় রাখুন' }}
                </button>
            </form>

            <div class="detail-benefits">
                <div><i class="fa-solid fa-truck-fast"></i><span><b>সারাদেশে ডেলিভারি</b><small>নিরাপদে পণ্য পৌঁছে দেওয়া হয়</small></span></div>
                <div><i class="fa-solid fa-shield-heart"></i><span><b>সহজ অর্ডার প্রক্রিয়া</b><small>ক্যাশ অন ডেলিভারি সুবিধা</small></span></div>
                <div><i class="fa-solid fa-rotate-left"></i><span><b>রিটার্ন ও এক্সচেঞ্জ</b><small>প্রযোজ্য নিয়ম অনুযায়ী</small></span></div>
            </div>
        </section>
    </div>

    <section class="detail-info-section">
        <h2>পণ্যের তথ্য</h2>
        <div class="detail-spec">
            <div><span>ক্যাটাগরি</span><b>{{ $product->category->name }}</b></div>
            <div><span>SKU</span><b>{{ $product->sku }}</b></div>
            <div><span>ভ্যারিয়েন্ট</span><b>{{ $hasVariants ? 'সাইজ ও রং অনুযায়ী' : 'প্রযোজ্য নয়' }}</b></div>
        </div>
    </section>

    @if($relatedProducts->isNotEmpty())
        <section class="section related-products-section">
            <div class="sectionhead"><h2>আপনার জন্য আরও পণ্য</h2><a href="{{ route('search') }}">সব পণ্য দেখুন</a></div>
            <div class="products">
                @foreach($relatedProducts as $relatedProduct)
                    @include('storefront.components.product-card', ['product' => $relatedProduct])
                @endforeach
            </div>
        </section>
    @endif
</div>

<script type="application/json" id="detailGalleryData">@json($galleryPhotos)</script>
<script type="application/json" id="detailVariantData">@json($detailVariantData)</script>
<script type="application/json" id="detailProductData">@json($detailProductData)</script>
<div id="detailLightbox" class="detail-lightbox" hidden>
    <button type="button" data-detail-close-zoom aria-label="বন্ধ করুন">✕</button>
    <img alt="{{ $product->name }}">
</div>
@endsection
