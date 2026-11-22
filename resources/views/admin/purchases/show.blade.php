@extends('layouts.admin')
@section('title', 'ক্রয় রেকর্ড')
@section('content')
<div class="pagehead"><div><h1>{{ $purchase->purchase_number }}</h1><div class="sub">{{ $purchase->supplier->name }} · {{ $purchase->purchased_at->format('d M Y, h:i A') }}</div></div><a class="btn secondary" href="{{ route('admin.purchases') }}">সব ক্রয় রেকর্ড</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<section class="panel tablewrap"><table class="table"><thead><tr><th>পণ্য</th><th>SKU</th><th>পরিমাণ</th><th>একক ক্রয়মূল্য</th><th>লাইন মোট</th></tr></thead><tbody>
@foreach($purchase->items as $item)<tr><td><b>{{ $item->product_name }}</b>@if($item->variant_name)<div class="muted small">{{ $item->variant_name }}</div>@endif</td><td>{{ $item->sku }}</td><td>{{ number_format($item->quantity) }}</td><td>৳{{ number_format((float) $item->unit_cost, 2) }}</td><td>৳{{ number_format((float) $item->line_total, 2) }}</td></tr>@endforeach
</tbody><tfoot><tr><th colspan="4" class="text-right">সর্বমোট</th><th>৳{{ number_format((float) $purchase->total, 2) }}</th></tr></tfoot></table></section>
<section class="panel" style="margin-top:16px"><b>স্ট্যাটাস:</b> স্টকে গৃহীত <span class="muted ml-3">রেকর্ড করেছেন: {{ $purchase->receivedBy?->name ?? '—' }}</span>@if($purchase->notes)<p class="mb-0 mt-2">{{ $purchase->notes }}</p>@endif</section>
@endsection
