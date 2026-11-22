@extends('layouts.admin')
@section('title', isset($product) ? 'Edit Product' : 'Add Product')
@section('content')
@php
    $isEdit = isset($product);
    $formAction = $isEdit ? route('admin.products.update', $product) : route('admin.products.store');
    $variantSizes = $isEdit ? $product->variants->pluck('size')->filter()->unique()->implode(', ') : '';
    $variantColors = $isEdit ? $product->variants->pluck('color')->filter()->unique()->implode(', ') : '';
    $existingVariants = $isEdit ? $product->variants->map(fn ($variant) => [
        'id' => $variant->id,
        'name' => $variant->name,
        'sku' => $variant->sku,
        'size' => $variant->size,
        'color' => $variant->color,
        'price_override' => $variant->price_override,
        'regular_price' => $variant->regular_price,
        'stock_quantity' => $variant->stock_quantity,
        'is_active' => $variant->is_active,
    ])->values()->all() : [];
@endphp
<main class="product-reference-main">
    <p class="product-reference-breadcrumb"><a href="{{ route('admin.products') }}">Products</a><span>/</span><strong>{{ $isEdit ? 'Edit product' : 'Add product' }}</strong></p>
    <h1>{{ $isEdit ? 'Edit product' : 'Add product' }}</h1>
    <p class="product-reference-intro">Enter the product details, then set the price and stock.</p>

    @if($errors->any())
        <div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" id="product-create-form">
        @csrf
        @if($isEdit) @method('PUT') <input type="hidden" name="expected_stock" value="{{ old('expected_stock', $product->stock_quantity) }}"> @endif
        <input type="hidden" name="stock_operation_key" value="{{ old('stock_operation_key', (string) \Illuminate\Support\Str::uuid()) }}">

        <section class="product-reference-card">
            <div class="product-reference-card-head"><h2>Product details</h2></div>
            <div class="product-reference-card-body product-reference-grid">
                <label>Product name<input name="name" value="{{ old('name', $product->name ?? '') }}" placeholder="e.g. Classic Straight Jeans" @class(['form-input', 'is-invalid' => $errors->has('name')]) @if($errors->has('name')) aria-invalid="true" @endif required data-product-name>@error('name')<small class="field-error">Product name is required.</small>@enderror</label>
                <label>Category<select name="category_id" required><option value="">Select a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->parent ? $category->parent->name.' / ' : '' }}{{ $category->name }}</option>@endforeach</select></label>
                <label>SKU<input name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="e.g. JNS-CLASSIC-001" required></label>
                <label>Short summary<input name="short_description" value="{{ old('short_description', $product->short_description ?? '') }}" placeholder="A short product summary"></label>
                <div class="product-reference-wide">
                    <span class="product-reference-label">Description</span>
                    <div class="rich-text-editor product-reference-editor" data-rich-text-editor>
                        <div class="rich-text-toolbar" role="toolbar" aria-label="Description formatting">
                            <button type="button" class="btn secondary" data-editor-command="formatBlock" data-editor-value="p">Paragraph</button>
                            <button type="button" class="btn secondary" data-editor-command="formatBlock" data-editor-value="h3">Heading</button>
                            <button type="button" class="btn secondary" data-editor-command="bold"><strong>Bold</strong></button>
                            <button type="button" class="btn secondary" data-editor-command="italic"><em>Italic</em></button>
                            <button type="button" class="btn secondary" data-editor-command="insertUnorderedList">Bulleted list</button>
                            <button type="button" class="btn secondary" data-editor-command="insertOrderedList">Numbered list</button>
                        </div>
                        <div class="rich-text-content" contenteditable="true" role="textbox" aria-multiline="true" data-editor-input data-placeholder="Describe the product’s material, fit, and care instructions."></div>
                        <textarea name="full_description" class="rich-text-value" data-editor-value>{{ old('full_description', $product->full_description ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </section>

        <section class="product-reference-card">
            <div class="product-reference-card-head"><h2>Product images</h2></div>
            <div class="product-reference-card-body product-reference-grid">
                <div class="product-reference-media">
                    <label for="main-image-upload">Main product image</label>
                    <p>The primary image for the product listing.</p>
                    <input type="url" name="main_image" value="{{ old('main_image', $product->main_image ?? '') }}" placeholder="Optional image URL" data-main-image-url>
                    <input type="file" id="main-image-upload" name="main_image_upload" accept="image/jpeg,image/png,image/webp,image/avif" data-main-image-upload>
                    @if($isEdit && $product->main_image)<div class="product-reference-current-image" data-main-image-current><img src="{{ $product->main_image }}" alt="Current main product image"><button type="button" class="btn secondary" data-remove-main-image="current">Remove</button></div>@endif
                    <div id="main-image-preview" data-main-image-preview class="product-reference-main-preview"><img id="main-image-preview-img" src="" alt="Main preview"><button type="button" class="btn secondary" data-remove-main-image="preview">Remove</button></div>
                </div>
                <div class="product-reference-media">
                    <label for="gallery-upload">Gallery images</label>
                    <p>Add more angles or close-ups. You can select multiple images.</p>
                    <input type="file" id="gallery-upload" name="gallery_uploads[]" accept="image/jpeg,image/png,image/webp,image/avif" multiple data-gallery-upload>
                    <div id="gallery-upload-preview" class="product-reference-previews" aria-live="polite"></div>
                    <details class="product-reference-url-details">
                        <summary>Use image URLs instead</summary>
                        <div id="gallery-url-list">
                            <div class="product-reference-url-row">
                                <input type="url" name="gallery_images[0][url]" placeholder="https://example.com/image.jpg">
                                <input type="text" name="gallery_images[0][alt_text]" placeholder="Alt text">
                                <button type="button" class="btn secondary" data-remove-gallery-url>Remove</button>
                            </div>
                        </div>
                        <button type="button" class="btn secondary" id="add-gallery-url">+ Add image URL</button>
                    </details>
                    @if($isEdit && $product->images->isNotEmpty())
                        <div class="product-reference-existing-gallery gallery-existing">
                            @foreach($product->images as $image)
                                <figure><img src="{{ $image->image_path }}" alt="{{ $image->alt_text ?: $product->name }}"><button type="button" class="btn secondary" data-remove-existing-gallery="{{ $image->id }}">Remove</button></figure>
                            @endforeach
                        </div>
                        <div></div>
                    @endif
                </div>
            </div>
        </section>

        <section class="product-reference-card">
            <div class="product-reference-card-head product-reference-heading"><h2>Pricing &amp; inventory</h2><label class="product-reference-switch"><input type="checkbox" id="has-variants" name="has_variants" value="1" @checked(old('has_variants', $isEdit ? $product->variants->isNotEmpty() : true))><span></span>This product has variants</label></div>
            <div class="product-reference-card-body" id="variant-options">
                <div class="product-reference-grid">
                    <label>Sizes<input id="variant-sizes" value="{{ old('variant_sizes', $variantSizes) }}" placeholder="e.g. S, M, L"></label>
                    <label>Colors<input id="variant-colors" value="{{ old('variant_colors', $variantColors) }}" placeholder="e.g. Indigo, Black"></label>
                </div>
                <p class="product-reference-help">Separate values with commas. Leave one field blank to use a single option type.</p>
                <div class="product-reference-table-heading"><h3>Variant combinations</h3><span id="variant-count">0 variants</span></div>
                <div class="product-reference-table-wrap"><table class="product-reference-table"><thead><tr><th>Variant</th><th>SKU</th><th>Price (৳)</th><th>Stock</th></tr></thead><tbody id="variant-rows"></tbody></table></div>
                <p id="variant-status" class="product-reference-help" role="status"></p>
            </div>
            <div class="product-reference-card-body product-reference-grid product-reference-base-fields">
                <label>Selling price (৳)<input type="number" name="selling_price" data-selling-price value="{{ old('selling_price', $product->selling_price ?? '') }}" min="0" step="0.01" required></label>
                <label>Regular price (৳)<input type="number" name="regular_price" data-regular-price value="{{ old('regular_price', $product->regular_price ?? '') }}" min="0" step="0.01"></label>
                <label>Buying price (৳)<input type="number" name="buying_price" value="{{ old('buying_price', $product->buying_price ?? '') }}" min="0" step="0.01"></label>
                <label id="main-stock-field">Stock quantity<input type="number" name="stock_quantity" id="stock-quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" min="0" required></label>
                <label>Low stock threshold<input type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" min="0" required></label>
            </div>
            <div class="product-reference-card-body product-reference-publishing"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> Active in store</label><label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))> Featured on homepage</label></div>
        </section>

        <div class="product-reference-actions"><a href="{{ route('admin.products') }}" class="product-reference-cancel">Cancel</a><button type="submit" class="product-reference-save">{{ $isEdit ? 'Save changes' : 'Save product' }}</button></div>
    </form>
    @if($isEdit)
        @include('admin.products._variants', ['product' => $product])
    @endif
</main>

<script>
const variantRows = document.getElementById('variant-rows');
const variantCount = document.getElementById('variant-count');
const variantStatus = document.getElementById('variant-status');
const variantOptions = document.getElementById('variant-options');
const variantToggle = document.getElementById('has-variants');
const mainStockField = document.getElementById('main-stock-field');
const mainStockInput = document.getElementById('stock-quantity');
const existingVariants = @json($existingVariants);
const parseOptions = (input) => [...new Set(input.value.split(',').map((value) => value.trim()).filter(Boolean))];
const variantKey = (size, color) => String(size || '').trim().toLowerCase() + '|' + String(color || '').trim().toLowerCase();
const makeSku = (name, index) => {
    const slug = name.toLowerCase().replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-').slice(0, 18);
    return `${slug ? slug + '-' : ''}${Math.random().toString(36).slice(2, 6).toUpperCase()}${index || ''}`;
};
function renderVariants() {
    mainStockField.hidden = variantToggle.checked;
    if (variantToggle.checked) mainStockInput.value = '0';
    if (!variantToggle.checked) { variantRows.replaceChildren(); variantCount.textContent = 'Variants disabled'; variantOptions.hidden = true; return; }
    variantOptions.hidden = false;
    const sizes = parseOptions(document.getElementById('variant-sizes'));
    const colors = parseOptions(document.getElementById('variant-colors'));
    const combinations = sizes.length || colors.length ? (sizes.length ? sizes : ['']).flatMap((size) => (colors.length ? colors : ['']).map((color) => ({size, color}))) : [];
    variantRows.replaceChildren();
    const productName = document.querySelector('[data-product-name]').value || 'PRODUCT';
    const existingByKey = new Map(existingVariants.map((variant) => [variantKey(variant.size, variant.color), variant]));
    combinations.forEach(({size, color}, index) => {
        const existing = existingByKey.get(variantKey(size, color));
        const name = existing?.name || [size, color].filter(Boolean).join(' / ') || 'Default';
        const sku = existing?.sku || makeSku(productName + ' ' + name, index);
        const price = existing?.price_override ?? '';
        const stock = existing?.stock_quantity ?? 0;
        const expectedStock = existing?.stock_quantity ?? 0;
        const operationKey = crypto.randomUUID();
        const row = document.createElement('tr');
        const escapeHtml = (value) => String(value).replace(/[&<>\"']/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[character]));
        row.innerHTML = '<td><strong>' + escapeHtml(name) + '</strong>' + (existing ? '<input type="hidden" name="variants[' + index + '][id]" value="' + existing.id + '">' : '') + '<input type="hidden" name="variants[' + index + '][name]" value="' + escapeHtml(name) + '"><input type="hidden" name="variants[' + index + '][size]" value="' + escapeHtml(size) + '"><input type="hidden" name="variants[' + index + '][color]" value="' + escapeHtml(color) + '"><input type="hidden" name="variants[' + index + '][expected_stock]" value="' + expectedStock + '"><input type="hidden" name="variants[' + index + '][stock_operation_key]" value="' + operationKey + '"></td><td><input required name="variants[' + index + '][sku]" value="' + escapeHtml(sku) + '"></td><td><input type="number" min="0" step="0.01" name="variants[' + index + '][price_override]" value="' + escapeHtml(price) + '" placeholder="Base price"></td><td><input required type="number" min="0" name="variants[' + index + '][stock_quantity]" value="' + stock + '"></td>';
        variantRows.append(row);
    });
    variantCount.textContent = `${combinations.length} variant${combinations.length === 1 ? '' : 's'}`;
    variantStatus.textContent = combinations.length === 0 ? 'Enter sizes or colors to generate combinations, or turn variants off.' : '';
}
document.getElementById('variant-sizes').addEventListener('input', renderVariants);
document.getElementById('variant-colors').addEventListener('input', renderVariants);
variantToggle.addEventListener('change', renderVariants);
renderVariants();

const galleryUrlList = document.getElementById('gallery-url-list');
document.getElementById('add-gallery-url').addEventListener('click', () => {
    const index = galleryUrlList.children.length;
    const row = document.createElement('div');
    row.className = 'product-reference-url-row';
    row.innerHTML = `<input type="url" name="gallery_images[${index}][url]" placeholder="https://example.com/image.jpg"><input type="text" name="gallery_images[${index}][alt_text]" placeholder="Alt text"><button type="button" class="btn secondary" data-remove-gallery-url>Remove</button>`;
    galleryUrlList.append(row);
});
galleryUrlList.addEventListener('click', (event) => event.target.closest('[data-remove-gallery-url]')?.closest('.product-reference-url-row')?.remove());
</script>
@endsection
