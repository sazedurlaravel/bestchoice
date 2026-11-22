@extends('layouts.admin')
@section('title', 'ক্রয় রেকর্ড')
@section('content')
<div class="pagehead"><div><h1>ক্রয় রেকর্ড</h1><div class="sub">সরবরাহকারীর ক্রয় এবং স্টকে যোগ হওয়া পণ্যের হিসাব</div></div><div class="d-flex" style="gap:8px"><a class="btn secondary" href="{{ route('admin.suppliers') }}">সরবরাহকারী</a><a class="btn" href="{{ route('admin.purchases.create') }}">নতুন ক্রয়</a></div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<section class="panel tablewrap"><table class="table"><thead><tr><th>রেকর্ড</th><th>তারিখ</th><th>সরবরাহকারী</th><th>অবস্থা</th><th>মোট</th><th></th></tr></thead><tbody>
@forelse($purchases as $purchase)<tr><td><b>{{ $purchase->purchase_number }}</b></td><td>{{ $purchase->purchased_at->format('d M Y') }}</td><td>{{ $purchase->supplier->name }}</td><td><span class="status active">স্টকে গৃহীত</span></td><td>৳{{ number_format((float) $purchase->total, 2) }}</td><td><a class="btn secondary small" href="{{ route('admin.purchases.show', $purchase) }}">বিস্তারিত</a></td></tr>@empty<tr><td colspan="6" class="empty">কোনো ক্রয় রেকর্ড নেই।</td></tr>@endforelse
</tbody></table>@if($purchases->hasPages())<div style="padding:16px">{{ $purchases->links() }}</div>@endif</section>
@endsection
