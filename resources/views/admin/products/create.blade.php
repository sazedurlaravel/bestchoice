@extends('layouts.admin')
@section('title', 'নতুন পণ্য যোগ করুন')
@section('content')

<div class="pagehead">
    <div>
        <h1>নতুন পণ্য যোগ করুন</h1>
        <div class="sub"><a href="{{ route('admin.products') }}">← পণ্য তালিকায় ফিরুন</a></div>
    </div>
</div>

<form method="POST" action="{{ route('admin.products.store') }}" class="product-form" enctype="multipart/form-data">
    @csrf
    @include('admin.products._form')
    <section class="panel" style="margin-top:20px">
        <div class="panelhead"><h3>সাইজ, রঙ ও ভ্যারিয়েন্ট</h3></div>
        <p class="muted">প্রতিটি ভ্যারিয়েন্টের আলাদা SKU, মূল্য ও স্টক যোগ করুন। সাইজ ও রঙ কমা দিয়ে লিখলে প্রতিটি কম্বিনেশনের জন্য আলাদা ভ্যারিয়েন্ট তৈরি হবে। ভ্যারিয়েন্ট ব্যবহার করলে উপরের মূল স্টক শূন্য রাখুন।</p>
        <div id="new-product-variants">
            @foreach(old('variants', []) as $i => $variant)
                @include('admin.products._new-variant-row', ['i' => $i, 'variant' => $variant])
            @endforeach
        </div>
        <button type="button" class="btn secondary" id="add-product-variant">আরেকটি ভ্যারিয়েন্ট যোগ করুন</button>
    </section>
    <div class="form-actions">
        <button type="submit" class="btn">পণ্য সংরক্ষণ করুন</button>
        <a href="{{ route('admin.products') }}" class="btn secondary">বাতিল</a>
    </div>
</form>
<template id="new-product-variant-template">
    @include('admin.products._new-variant-row', ['i' => '__INDEX__', 'variant' => []])
</template>
<script>
/* ── Variant add / remove ───────────────────────────────────── */
document.getElementById('add-product-variant')?.addEventListener('click', () => {
    const list = document.getElementById('new-product-variants');
    const index = list.children.length;
    list.insertAdjacentHTML('beforeend', document.getElementById('new-product-variant-template').innerHTML.replaceAll('__INDEX__', index));
    // Auto-fill SKU for new variant
    const newRow = list.lastElementChild;
    const variantSkuInput = newRow?.querySelector('[data-variant-sku]');
    if (variantSkuInput && !variantSkuInput.value) {
        variantSkuInput.value = generateSku(document.querySelector('[data-product-name]')?.value || '', index);
    }
});
document.getElementById('new-product-variants')?.addEventListener('click', (event) => {
    if (event.target.matches('[data-remove-variant]')) event.target.closest('[data-variant-row]').remove();
});
// Auto-fill variant SKU when variant name is typed
document.getElementById('new-product-variants')?.addEventListener('input', (event) => {
    if (event.target.matches('[data-variant-name]')) {
        const row = event.target.closest('[data-variant-row]');
        const skuInput = row?.querySelector('[data-variant-sku]');
        if (skuInput && !skuInput.dataset.userEdited) {
            const productName = document.querySelector('[data-product-name]')?.value || '';
            skuInput.value = generateSku(productName + ' ' + event.target.value, 0);
        }
    }
    if (event.target.matches('[data-variant-sku]')) {
        event.target.dataset.userEdited = '1';
    }
});

/* ── SKU generation ─────────────────────────────────────────── */
function generateSku(name, suffix) {
    const slug = name.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .slice(0, 20);
    const rand = Math.random().toString(36).slice(2, 6).toUpperCase();
    return (slug ? slug + '-' : '') + rand + (suffix > 0 ? suffix : '');
}

document.getElementById('btn-auto-sku')?.addEventListener('click', () => {
    const nameInput = document.querySelector('[data-product-name]');
    const skuInput = document.querySelector('[data-product-sku]');
    if (skuInput) skuInput.value = generateSku(nameInput?.value || '', 0);
});
// Auto-generate SKU on first name blur if SKU is empty
document.querySelector('[data-product-name]')?.addEventListener('blur', () => {
    const skuInput = document.querySelector('[data-product-sku]');
    if (skuInput && !skuInput.value.trim()) {
        skuInput.value = generateSku(document.querySelector('[data-product-name]').value, 0);
    }
});

/* ── Discount badge ─────────────────────────────────────────── */
function updateDiscountBadge() {
    const selling = parseFloat(document.querySelector('[data-selling-price]')?.value);
    const regular = parseFloat(document.querySelector('[data-regular-price]')?.value);
    const badge = document.getElementById('discount-badge');
    if (!badge) return;
    if (regular > 0 && selling > 0 && regular > selling) {
        const pct = Math.round((1 - selling / regular) * 100);
        badge.textContent = pct + '% ছাড়';
        badge.style.display = 'inline';
    } else {
        badge.style.display = 'none';
    }
}
document.querySelector('[data-selling-price]')?.addEventListener('input', updateDiscountBadge);
document.querySelector('[data-regular-price]')?.addEventListener('input', updateDiscountBadge);
updateDiscountBadge();

/* ── Smart base-stock hint ──────────────────────────────────── */
function updateStockHint() {
    const hasVariants = document.querySelectorAll('[data-variant-row]').length > 0;
    const hint = document.getElementById('base-stock-hint');
    if (hint) hint.style.display = hasVariants ? 'block' : 'none';
}
document.getElementById('new-product-variants')?.addEventListener('click', updateStockHint);
document.getElementById('add-product-variant')?.addEventListener('click', () => setTimeout(updateStockHint, 10));

/* ── Main image live preview ────────────────────────────────── */
function showMainPreview(src) {
    const preview = document.getElementById('main-image-preview');
    const img = document.getElementById('main-image-preview-img');
    if (!preview || !img) return;
    if (src) { img.src = src; preview.style.display = 'block'; }
    else { preview.style.display = 'none'; }
}
document.querySelector('[data-main-image-url]')?.addEventListener('input', (e) => {
    if (!document.querySelector('[data-main-image-upload]')?.files?.length) {
        showMainPreview(e.target.value.trim());
    }
});
document.querySelector('[data-main-image-upload]')?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (file) showMainPreview(URL.createObjectURL(file));
    else showMainPreview(document.querySelector('[data-main-image-url]')?.value.trim() || '');
});

// Show on page load if URL already has a value (old() on validation fail)
(function() {
    const urlInput = document.querySelector('[data-main-image-url]');
    if (urlInput?.value.trim()) showMainPreview(urlInput.value.trim());
})();
</script>
@endsection
