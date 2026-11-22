@extends('layouts.admin')
@section('title', 'কুপন ও ছাড়')
@section('content')
<div class="pagehead"><div><h1>কুপন ও ছাড়</h1><div class="sub">চেকআউটে প্রয়োগযোগ্য ডিসকাউন্ট কোড</div></div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="panel" style="padding:20px;margin-bottom:20px"><h2>নতুন কুপন</h2><form method="POST" action="{{ route('admin.coupons.store') }}" class="formgrid">@csrf
<label>কোড<input class="form-input" name="code" value="{{ old('code') }}" required></label>
<label>ছাড়ের ধরন<select class="form-input" name="type"><option value="percent">শতকরা</option><option value="fixed">নির্দিষ্ট টাকা</option></select></label>
<label>ছাড়ের পরিমাণ<input class="form-input" type="number" step="0.01" min="0.01" name="value" required></label>
<label>ন্যূনতম পণ্য মূল্য<input class="form-input" type="number" step="0.01" min="0" name="minimum_subtotal" value="0" required></label>
<label>ব্যবহার সীমা (ঐচ্ছিক)<input class="form-input" type="number" min="1" name="usage_limit"></label>
<label>শুরু সময়<input class="form-input" type="datetime-local" name="starts_at"></label><label>শেষ সময়<input class="form-input" type="datetime-local" name="ends_at"></label>
<div><button class="btn" type="submit">কুপন তৈরি</button></div></form></section>
<section class="panel tablewrap"><table class="table"><thead><tr><th>কোড</th><th>ছাড়</th><th>ন্যূনতম</th><th>ব্যবহার</th><th>অবস্থা</th><th></th></tr></thead><tbody>
@forelse($coupons as $coupon)<tr><td><b>{{ $coupon->code }}</b></td><td>{{ $coupon->type === 'percent' ? $coupon->value.'%' : '৳'.number_format((float) $coupon->value, 2) }}</td><td>৳{{ number_format((float) $coupon->minimum_subtotal, 2) }}</td><td>{{ $coupon->usage_count }} / {{ $coupon->usage_limit ?? '∞' }}</td><td>{{ $coupon->is_active ? 'সক্রিয়' : 'বন্ধ' }}</td><td style="display:flex;gap:6px"><form method="POST" action="{{ route('admin.coupons.toggle', $coupon) }}">@csrf @method('PATCH')<button class="btn secondary">{{ $coupon->is_active ? 'বন্ধ করুন' : 'চালু করুন' }}</button></form><form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('কুপনটি মুছবেন?')">@csrf @method('DELETE')<button class="btn secondary">মুছুন</button></form></td></tr>@empty<tr><td colspan="6">এখনো কুপন নেই।</td></tr>@endforelse
</tbody></table>{{ $coupons->links() }}</section>
@endsection
