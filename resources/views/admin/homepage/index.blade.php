@extends('layouts.admin')
@section('title', 'হোমপেজ ম্যানেজমেন্ট')
@section('content')
<div class="pagehead"><div><h1>হোমপেজ ম্যানেজমেন্ট</h1><div class="sub">স্টোরফ্রন্টের ব্যানার, লেখা ও প্রদর্শন ক্রম পরিচালনা করুন</div></div><a class="btn secondary" href="{{ route('home') }}">স্টোরফ্রন্ট দেখুন</a></div>

@if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="panel" style="margin-bottom:20px">
    <div class="panelhead"><h3>নতুন ব্যানার যোগ করুন</h3></div>
    <form method="POST" action="{{ route('admin.homepage.banners.store') }}" class="formgrid">@csrf
        <label>ছোট শিরোনাম<input class="form-input" name="kicker" value="{{ old('kicker') }}" maxlength="100"></label>
        <label>সাজানোর ক্রম<input class="form-input" type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" required></label>
        <label>প্রধান শিরোনাম *<input class="form-input" name="headline" value="{{ old('headline') }}" maxlength="120" required></label>
        <label>হাইলাইট শিরোনাম *<input class="form-input" name="highlight" value="{{ old('highlight') }}" maxlength="120" required></label>
        <label class="full">বর্ণনা<textarea class="form-input" name="description" rows="2" maxlength="255">{{ old('description') }}</textarea></label>
        <label>বাটনের লেখা *<input class="form-input" name="button_text" value="{{ old('button_text', 'এখনই দেখুন ↗') }}" maxlength="80" required></label>
        <label>বাটনের লিংক *<input class="form-input" name="button_url" value="{{ old('button_url', '/shop') }}" maxlength="255" required></label>
        <label class="full">ছবির URL *<input class="form-input" type="url" name="image_url" value="{{ old('image_url') }}" maxlength="2048" placeholder="https://..." required></label>
        <label>ব্যাকগ্রাউন্ড রং *<input class="form-input" type="color" name="background_color" value="{{ old('background_color', '#e8f1e9') }}" required></label>
        <label class="d-flex align-items-center" style="gap:8px;margin-top:28px"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> সক্রিয়</label>
        <div class="full"><button class="btn" type="submit"><i class="fa-solid fa-plus"></i> ব্যানার যোগ করুন</button></div>
    </form>
</section>

<section class="panel"><div class="panelhead"><h3>বর্তমান ব্যানার</h3></div><div class="tablewrap"><table class="table"><thead><tr><th>ব্যানার</th><th>ছবি</th><th>ক্রম</th><th>অবস্থা</th><th>পরিচালনা</th></tr></thead><tbody>
@forelse($banners as $banner)
<tr><td><b>{{ $banner->headline }} {{ $banner->highlight }}</b><div class="muted small">{{ $banner->kicker }}</div></td><td><img src="{{ $banner->image_url }}" alt="{{ $banner->headline }}" style="width:90px;height:52px;object-fit:cover;border-radius:6px"></td><td>{{ $banner->sort_order }}</td><td><span class="status {{ $banner->is_active ? 'active' : 'inactive' }}">{{ $banner->is_active ? 'সক্রিয়' : 'বন্ধ' }}</span></td><td><details><summary class="btn secondary small">সম্পাদনা</summary><form method="POST" action="{{ route('admin.homepage.banners.update', $banner) }}" class="formgrid" style="min-width:320px;margin-top:10px">@csrf @method('PUT')
<label>ছোট শিরোনাম<input class="form-input" name="kicker" value="{{ $banner->kicker }}"></label><label>ক্রম<input class="form-input" type="number" name="sort_order" value="{{ $banner->sort_order }}" min="0" required></label><label>প্রধান শিরোনাম<input class="form-input" name="headline" value="{{ $banner->headline }}" required></label><label>হাইলাইট<input class="form-input" name="highlight" value="{{ $banner->highlight }}" required></label><label class="full">বর্ণনা<textarea class="form-input" name="description" rows="2">{{ $banner->description }}</textarea></label><label>বাটনের লেখা<input class="form-input" name="button_text" value="{{ $banner->button_text }}" required></label><label>বাটনের লিংক<input class="form-input" name="button_url" value="{{ $banner->button_url }}" required></label><label class="full">ছবির URL<input class="form-input" type="url" name="image_url" value="{{ $banner->image_url }}" required></label><label>ব্যাকগ্রাউন্ড রং<input class="form-input" type="color" name="background_color" value="{{ $banner->background_color }}" required></label><label class="d-flex align-items-center" style="gap:8px;margin-top:28px"><input type="checkbox" name="is_active" value="1" @checked($banner->is_active)> সক্রিয়</label><div class="full"><button class="btn" type="submit">আপডেট করুন</button></div></form></details><form method="POST" action="{{ route('admin.homepage.banners.destroy', $banner) }}" style="display:inline" onsubmit="return confirm('এই ব্যানারটি মুছে ফেলবেন?')">@csrf @method('DELETE')<button class="btn secondary small" type="submit">মুছুন</button></form></td></tr>
@empty<tr><td colspan="5" class="empty">কোনো ব্যানার নেই।</td></tr>@endforelse
</tbody></table></div>@if($banners->hasPages())<div style="padding:16px 20px">{{ $banners->links() }}</div>@endif</section>
@endsection
