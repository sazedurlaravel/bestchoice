<input type="hidden" name="expected_stock" value="{{ old('expected_stock', $product->stock_quantity ?? 0) }}">
<input type="hidden" name="stock_operation_key" value="{{ old('stock_operation_key', (string) \Illuminate\Support\Str::uuid()) }}">

@if($errors->any())
    <div class="alert alert-error">
        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="form-grid">
    <section class="panel">
        <div class="panelhead"><h3>মূল তথ্য</h3></div>
        <div class="form-body">
            <div class="field">
                <label>পণ্যের নাম <span class="req">*</span></label>
                <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" @class(['form-input', 'is-invalid' => $errors->has('name')]) @if($errors->has('name')) aria-invalid="true" @endif required data-product-name>
                @error('name')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field-row">
                <div class="field">
                    <label>SKU <span class="req">*</span>
                        @if(empty($product->sku ?? null))<button type="button" id="btn-auto-sku" class="btn secondary" style="font-size:11px;padding:1px 8px;margin-left:8px;vertical-align:middle" title="পণ্যের নাম থেকে SKU তৈরি করুন">⟳ স্বয়ংক্রিয়</button>@endif
                    </label>
                    <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" @class(['form-input', 'is-invalid' => $errors->has('sku')]) @if($errors->has('sku')) aria-invalid="true" @endif required data-product-sku>
                    @error('sku')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label>ক্যাটাগরি <span class="req">*</span></label>
                    <select name="category_id" @class(['form-input', 'is-invalid' => $errors->has('category_id')]) @if($errors->has('category_id')) aria-invalid="true" @endif required>
                        <option value="">নির্বাচন করুন</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id ?? '') == $cat->id)>
                                {{ $cat->parent ? $cat->parent->name.' › ' : '' }}{{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="field">
                <label>সংক্ষিপ্ত বিবরণ</label>
                <textarea name="short_description" @class(['form-input', 'is-invalid' => $errors->has('short_description')]) @if($errors->has('short_description')) aria-invalid="true" @endif rows="3">{{ old('short_description', $product->short_description ?? '') }}</textarea>
                @error('short_description')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field">
                <label>বিস্তারিত বিবরণ</label>
                <div class="rich-text-editor @if($errors->has('full_description')) is-invalid @endif" data-rich-text-editor>
                    <div class="rich-text-toolbar" role="toolbar" aria-label="বিবরণ ফরম্যাটিং">
                        <button type="button" class="btn secondary" data-editor-command="bold" title="গাঢ়">B</button>
                        <button type="button" class="btn secondary" data-editor-command="italic" title="ইটালিক"><em>I</em></button>
                        <button type="button" class="btn secondary" data-editor-command="underline" title="আন্ডারলাইন"><u>U</u></button>
                        <button type="button" class="btn secondary" data-editor-command="insertUnorderedList" title="বুলেট তালিকা">• তালিকা</button>
                        <button type="button" class="btn secondary" data-editor-command="insertOrderedList" title="ক্রমিক তালিকা">1. তালিকা</button>
                    </div>
                    <div class="rich-text-content" contenteditable="true" role="textbox" aria-multiline="true" data-editor-input @if($errors->has('full_description')) aria-invalid="true" @endif></div>
                    <textarea name="full_description" class="rich-text-value" data-editor-value>{{ old('full_description', $product->full_description ?? '') }}</textarea>
                </div>
                @error('full_description')<small class="field-error">{{ $message }}</small>@enderror
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panelhead"><h3>মূল্য ও স্টক</h3></div>
        <div class="form-body">
            <div class="field-row">
                <div class="field">
                    <label>বিক্রয় মূল্য (৳) <span class="req">*</span> <span id="discount-badge" style="display:none;background:#e8f5e9;color:#2e7d32;font-size:11px;font-weight:600;padding:2px 7px;border-radius:12px;margin-left:4px"></span></label>
                    <input type="number" name="selling_price" data-selling-price value="{{ old('selling_price', $product->selling_price ?? '') }}" @class(['form-input', 'is-invalid' => $errors->has('selling_price')]) @if($errors->has('selling_price')) aria-invalid="true" @endif min="0" step="0.01" required>
                    @error('selling_price')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label>নিয়মিত মূল্য (৳) <small class="muted">ছাড়ের জন্য</small></label>
                    <input type="number" name="regular_price" value="{{ old('regular_price', $product->regular_price ?? '') }}" @class(['form-input', 'is-invalid' => $errors->has('regular_price')]) @if($errors->has('regular_price')) aria-invalid="true" @endif min="0" step="0.01" data-regular-price>
                    @error('regular_price')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="field">
                <label>ক্রয়মূল্য (৳) <small class="muted">লাভ হিসাবের জন্য</small></label>
                <input type="number" name="buying_price" value="{{ old('buying_price', $product->buying_price ?? '') }}" @class(['form-input', 'is-invalid' => $errors->has('buying_price')]) @if($errors->has('buying_price')) aria-invalid="true" @endif min="0" step="0.01">
                @error('buying_price')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <div class="field-row" id="base-stock-row">
                <div class="field">
                    <label>স্টক পরিমাণ <span class="req">*</span></label>
                    <input type="number" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" @class(['form-input', 'is-invalid' => $errors->has('stock_quantity')]) @if($errors->has('stock_quantity')) aria-invalid="true" @endif min="0" required>
                    <small id="base-stock-hint" class="muted" style="display:none">ভ্যারিয়েন্ট যোগ করলে এখানে ০ রাখুন।</small>
                    @error('stock_quantity')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label>কম স্টকের সীমা <span class="req">*</span></label>
                    <input type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" @class(['form-input', 'is-invalid' => $errors->has('low_stock_threshold')]) @if($errors->has('low_stock_threshold')) aria-invalid="true" @endif min="0" required>
                    @error('low_stock_threshold')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
        </div>

        <div class="panelhead" style="margin-top:4px"><h3>ছবি</h3></div>
        <div class="form-body">
            <div class="field">
                <label>প্রধান ছবির URL <small class="muted">(ঐচ্ছিক)</small></label>
                <input type="url" name="main_image" value="{{ old('main_image', $product->main_image ?? '') }}" @class(['form-input', 'is-invalid' => $errors->has('main_image')]) @if($errors->has('main_image')) aria-invalid="true" @endif placeholder="https://..." data-main-image-url>
                @error('main_image')<small class="field-error">{{ $message }}</small>@enderror
                <label style="display:block;margin-top:12px">অথবা প্রধান ছবি আপলোড করুন <small class="muted">(ঐচ্ছিক)</small></label>
                <input type="file" name="main_image_upload" @class(['form-input', 'is-invalid' => $errors->has('main_image_upload')]) @if($errors->has('main_image_upload')) aria-invalid="true" @endif accept="image/jpeg,image/png,image/webp,image/avif" data-main-image-upload>
                @error('main_image_upload')<small class="field-error">{{ $message }}</small>@enderror
                <small class="muted">JPG, PNG, WebP বা AVIF; সর্বোচ্চ 5 MB। আপলোড করা ফাইল URL-এর চেয়ে অগ্রাধিকার পাবে।</small>
                <div id="main-image-preview" data-main-image-preview style="display:none;position:relative;width:90px;margin-top:10px"><img id="main-image-preview-img" src="" alt="প্রিভিউ" style="width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #d1d5db"><button type="button" class="btn secondary" data-remove-main-image="preview" aria-label="প্রধান ছবি সরান" title="প্রধান ছবি সরান" style="position:absolute;top:-8px;right:-8px;width:24px;height:24px;padding:0;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;color:#b42318;border:1px solid #b42318"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="sr-only">প্রধান ছবি সরান</span></button><small class="muted" style="display:block;margin-top:4px">নতুন ছবির প্রিভিউ</small></div>
                @if(!empty($product->main_image ?? null))
                    <div data-main-image-current style="position:relative;width:90px;margin-top:8px"><img src="{{ $product->main_image }}" alt="বর্তমান প্রধান ছবি" style="width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #e5e9e6"><button type="button" class="btn secondary" data-remove-main-image="current" aria-label="প্রধান ছবি সরান" title="প্রধান ছবি সরান" style="position:absolute;top:-8px;right:-8px;width:24px;height:24px;padding:0;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;color:#b42318;border:1px solid #b42318"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="sr-only">প্রধান ছবি সরান</span></button><small class="muted" style="display:block">বর্তমান প্রধান ছবি</small></div>
                @endif
            </div>
            <div class="field">
                <label>গ্যালারির ছবির URL <small class="muted">(ঐচ্ছিক)</small></label>
                <p class="muted">ছবি যোগ করতে URL বা আপলোড ব্যবহার করুন; দুটিই ঐচ্ছিক। একাধিক ছবি যোগ করতে প্রতিটি URL আলাদা ঘরে দিন। আগের গ্যালারি ছবি সংরক্ষিত থাকবে।</p>
                <div class="gallery-image-inputs">
                    @foreach(old('gallery_images', [['url' => '', 'alt_text' => '']]) as $i => $image)
                        <div class="field-row gallery-image-row">
                        <div class="field"><input type="url" name="gallery_images[{{ $i }}][url]" value="{{ $image['url'] ?? '' }}" @class(['form-input', 'is-invalid' => $errors->has("gallery_images.{$i}.url")]) @if($errors->has("gallery_images.{$i}.url")) aria-invalid="true" @endif placeholder="https://..." @if($i === 0) data-first-gallery-url @endif>@error("gallery_images.{$i}.url")<small class="field-error">{{ $message }}</small>@enderror</div>
                            <div class="field"><input type="text" name="gallery_images[{{ $i }}][alt_text]" value="{{ $image['alt_text'] ?? '' }}" @class(['form-input', 'is-invalid' => $errors->has("gallery_images.{$i}.alt_text")]) @if($errors->has("gallery_images.{$i}.alt_text")) aria-invalid="true" @endif placeholder="ছবির বর্ণনা (ঐচ্ছিক)">@error("gallery_images.{$i}.alt_text")<small class="field-error">{{ $message }}</small>@enderror</div>
                            <button type="button" class="btn secondary" data-remove-gallery-image aria-label="ছবি সরান" title="ছবি সরান"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="sr-only">ছবি সরান</span></button>
                        </div>
                    @endforeach
                </div>
                @if(isset($product))
                    <div class="gallery-existing" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0">
                        @foreach($product->images as $image)
                            <figure style="position:relative;margin:0;text-align:center;width:90px">
                                <img src="{{ $image->image_path }}" alt="{{ $image->alt_text ?? $product->name }}" style="width:90px;height:90px;object-fit:cover;border-radius:8px">
                                <button type="button" class="btn secondary" data-remove-existing-gallery="{{ $image->id }}" aria-label="ছবি সরান" title="ছবি সরান" style="position:absolute;top:-8px;right:-8px;width:24px;height:24px;padding:0;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;color:#b42318;border:1px solid #b42318"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="sr-only">ছবি সরান</span></button>
                                <figcaption class="muted">বর্তমান ছবি</figcaption>
                            </figure>
                        @endforeach
                    </div>
                    <div data-removed-gallery-images></div>
                @endif
            </div>
            <div class="field">
                <label>ছবি আপলোড করুন <small class="muted">(ঐচ্ছিক)</small></label>
                <input type="file" name="gallery_uploads[]" @class(['form-input', 'is-invalid' => $errors->has('gallery_uploads') || $errors->has('gallery_uploads.*')]) @if($errors->has('gallery_uploads') || $errors->has('gallery_uploads.*')) aria-invalid="true" @endif accept="image/jpeg,image/png,image/webp,image/avif" multiple data-gallery-upload>
                @error('gallery_uploads')<small class="field-error">{{ $message }}</small>@enderror
                @error('gallery_uploads.*')<small class="field-error">{{ $message }}</small>@enderror
                <small class="muted">JPG, PNG, WebP বা AVIF; প্রতিটি সর্বোচ্চ 5 MB, একবারে সর্বোচ্চ 20টি। আপলোড করা ছবি গ্যালারিতে যুক্ত হবে।</small>
                <div id="gallery-upload-preview" aria-live="polite" style="display:none;gap:10px;flex-wrap:wrap;margin-top:12px"></div>
            </div>
        </div>

        <div class="panelhead" style="margin-top:4px"><h3>প্রকাশনা</h3></div>
        <div class="form-body">
            <label class="toggle-label">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
                <span>স্টোরে সক্রিয়</span>
            </label>
            <label class="toggle-label" style="margin-top:10px">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))>
                <span>হোমপেজে ফিচার্ড</span>
            </label>
        </div>
    </section>
</div>
