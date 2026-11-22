@extends('layouts.admin')
@section('title', 'পণ্য এডিট করুন')
@section('content')

<div class="pagehead">
    <div>
        <h1>পণ্য এডিট করুন</h1>
        <div class="sub"><a href="{{ route('admin.products') }}">← পণ্য তালিকায় ফিরুন</a></div>
    </div>
</div>

<form method="POST" action="{{ route('admin.products.update', $product) }}" class="product-form" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.products._form')
    <div class="form-actions">
        <button type="submit" class="btn">পরিবর্তন সংরক্ষণ করুন</button>
        <a href="{{ route('admin.products') }}" class="btn secondary">বাতিল</a>
    </div>
</form>

@include('admin.products._variants', ['product' => $product])

<script>
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
</script>
@endsection
