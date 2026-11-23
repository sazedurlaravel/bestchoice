@php $model = $model ?? null; @endphp
@if($errors->any())
    <div class="alert alert-error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<section class="panel">
    <div class="form-body" style="display:flex;flex-direction:column;gap:16px">
        <div class="field">
            <label>ক্যাটাগরির নাম <span class="req">*</span></label>
            <input type="text" name="name" value="{{ old('name', $model->name ?? '') }}" class="form-input" required>
        </div>
        <div class="field">
            <label>প্যারেন্ট ক্যাটাগরি <small class="muted">(সাবক্যাটাগরির জন্য)</small></label>
            <select name="parent_id" class="form-input">
                <option value="">— কোনো প্যারেন্ট নেই (টপ-লেভেল) —</option>
                @foreach($parents as $p)
                    <option value="{{ $p->id }}" @selected(old('parent_id', $model->parent_id ?? '') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>বিবরণ</label>
            <textarea name="description" class="form-input" rows="3">{{ old('description', $model->description ?? '') }}</textarea>
        </div>
        <div class="field">
            <label>ক্যাটাগরি ছবি <small class="muted">(ঐচ্ছিক)</small></label>
            <input type="file" name="image_upload" class="form-input" accept="image/jpeg,image/png,image/webp,image/avif">
            @if(!empty($model?->image_path))
                <img src="{{ $model->image_path }}" alt="{{ $model->name }}" style="width:96px;height:72px;object-fit:cover;border-radius:8px;margin-top:8px">
                <small class="muted d-block">নতুন ছবি না দিলে বর্তমান ছবিটি থাকবে। সর্বোচ্চ 5 MB।</small>
            @else
                <small class="muted">ছবি না দিলেও ক্যাটাগরি সংরক্ষণ করা যাবে। সর্বোচ্চ 5 MB।</small>
            @endif
        </div>
        <div class="field">
            <label>সর্ট অর্ডার <span class="req">*</span></label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $model->sort_order ?? 0) }}" class="form-input" min="0" required>
        </div>
        <label class="toggle-label">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $model->is_active ?? true))>
            <span>সক্রিয়</span>
        </label>
    </div>
</section>
