{{-- Reuse the approved admin components; no dedicated variant mockup was supplied. --}}
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<section class="panel variant-manager">
    <div class="panelhead"><h3>পণ্যের ভ্যারিয়েন্ট</h3></div>
    <p class="muted">সাইজ অথবা রঙ দিন। নতুন ভ্যারিয়েন্টে একাধিক সাইজ বা রঙ কমা দিয়ে লিখলে প্রতিটি কম্বিনেশনের জন্য আলাদা ভ্যারিয়েন্ট তৈরি হবে। ফাঁকা মূল্য পণ্যের মূল্য ব্যবহার করবে। ভ্যারিয়েন্ট পরিবর্তনের আগে মূল পণ্যের স্টক শূন্য করুন। ইতিহাস রাখতে ভ্যারিয়েন্ট নিষ্ক্রিয় করুন।</p>
    <div class="tablewrap">
        <table class="table">
            <thead><tr><th>ভ্যারিয়েন্ট / SKU</th><th>সাইজ</th><th>রঙ</th><th>বিক্রয় মূল্য</th><th>নিয়মিত মূল্য</th><th>স্টক</th><th>অবস্থা</th></tr></thead>
            <tbody>
                @forelse($product->variants as $variant)
                    <tr>
                        <td>{{ $variant->name }}<br><span class="muted small">{{ $variant->sku }}</span></td>
                        <td>{{ $variant->size ?? '—' }}</td>
                        <td>{{ $variant->color ?? '—' }}</td>
                        <td>৳{{ number_format((float) ($variant->price_override ?? $product->selling_price), 2) }}</td>
                        <td>{{ ($variant->regular_price ?? $product->regular_price) === null ? '—' : '৳'.number_format((float) ($variant->regular_price ?? $product->regular_price), 2) }}</td>
                        <td>{{ $variant->stock_quantity }}</td>
                        <td>{{ $variant->is_active ? 'সক্রিয়' : 'নিষ্ক্রিয়' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">কোনো ভ্যারিয়েন্ট নেই।</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach($product->variants->concat([null]) as $variant)
        @php
            $formKey = $variant ? (string) $variant->id : 'new';
            $variantErrors = $errors->getBag('variant_'.$formKey);
            $submitted = old('variant_form') === $formKey;
            $values = $submitted ? old('variant_input', []) : [];
        @endphp
        <details @if($variantErrors->any()) open @endif>
            <summary>{{ $variant ? 'এডিট: '.$variant->name : 'নতুন ভ্যারিয়েন্ট যোগ করুন' }}</summary>
            @if($variantErrors->any())
                <div class="alert alert-error"><ul>@foreach($variantErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ $variant ? route('admin.products.variants.update', [$product, $variant]) : route('admin.products.variants.store', $product) }}" class="form-body">
                @csrf
                @if($variant) @method('PUT') @endif
                <input type="hidden" name="expected_stock" value="{{ $values['expected_stock'] ?? $variant?->stock_quantity ?? 0 }}">
                <input type="hidden" name="stock_operation_key" value="{{ $values['stock_operation_key'] ?? (string) \Illuminate\Support\Str::uuid() }}">
                <div class="field-row">
                    <div class="field">
                        <label for="variant-{{ $formKey }}-name">নাম *</label>
                        <input id="variant-{{ $formKey }}-name" name="name" value="{{ $values['name'] ?? $variant?->name ?? '' }}" class="form-input" maxlength="255" required>
                    </div>
                    <div class="field">
                        <label for="variant-{{ $formKey }}-sku">SKU *</label>
                        <input id="variant-{{ $formKey }}-sku" name="sku" value="{{ $values['sku'] ?? $variant?->sku ?? '' }}" class="form-input" maxlength="255" required>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="variant-{{ $formKey }}-size">সাইজ</label>
                        <input id="variant-{{ $formKey }}-size" name="size" value="{{ $submitted ? ($values['size'] ?? '') : ($variant?->size ?? '') }}" class="form-input" maxlength="255" placeholder="S, M, L">
                        @if(!$variant)<small class="muted">একাধিক সাইজ কমা দিয়ে লিখুন।</small>@endif
                    </div>
                    <div class="field">
                        <label for="variant-{{ $formKey }}-color">রঙ</label>
                        <input id="variant-{{ $formKey }}-color" name="color" value="{{ $submitted ? ($values['color'] ?? '') : ($variant?->color ?? '') }}" class="form-input" maxlength="255" placeholder="Black, White">
                        @if(!$variant)<small class="muted">একাধিক রঙ কমা দিয়ে লিখুন।</small>@endif
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="variant-{{ $formKey }}-selling">বিক্রয় মূল্য (৳)</label>
                        <input id="variant-{{ $formKey }}-selling" type="number" name="price_override" value="{{ $submitted ? ($values['price_override'] ?? '') : ($variant?->price_override ?? '') }}" class="form-input" min="0" step="0.01" placeholder="{{ $product->selling_price }}">
                    </div>
                    <div class="field">
                        <label for="variant-{{ $formKey }}-regular">নিয়মিত মূল্য (৳)</label>
                        <input id="variant-{{ $formKey }}-regular" type="number" name="regular_price" value="{{ $submitted ? ($values['regular_price'] ?? '') : ($variant?->regular_price ?? '') }}" class="form-input" min="0" step="0.01" placeholder="{{ $product->regular_price }}">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="variant-{{ $formKey }}-stock">{{ $variant ? 'স্টক পরিমাণ' : 'প্রারম্ভিক স্টক' }} *</label>
                        <input id="variant-{{ $formKey }}-stock" type="number" name="stock_quantity" value="{{ $values['stock_quantity'] ?? $variant?->stock_quantity ?? 0 }}" class="form-input" min="0" step="1" required>
                    </div>
                    <div class="field">
                        <label for="variant-{{ $formKey }}-active">অবস্থা</label>
                        <select id="variant-{{ $formKey }}-active" name="is_active" class="form-input">
                            <option value="1" @selected(($values['is_active'] ?? $variant?->is_active ?? true) == 1)>সক্রিয়</option>
                            <option value="0" @selected(($values['is_active'] ?? $variant?->is_active ?? true) == 0)>নিষ্ক্রিয়</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions"><button type="submit" class="btn">{{ $variant ? 'ভ্যারিয়েন্ট সংরক্ষণ করুন' : 'ভ্যারিয়েন্ট যোগ করুন' }}</button></div>
            </form>
        </details>
    @endforeach
</section>
