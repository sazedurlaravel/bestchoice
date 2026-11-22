@extends('layouts.admin')
@section('title', 'কাস্টমার তালিকা')
@section('content')
<div class="pagehead"><div><h1>কাস্টমার তালিকা</h1><div class="sub">অর্ডার ডেটা থেকে সংগৃহীত কাস্টমার</div></div></div>
<section class="panel tablewrap"><table class="table"><thead><tr><th>নাম</th><th>মোবাইল</th><th>জেলা</th><th>সর্বশেষ অর্ডার</th></tr></thead><tbody>@forelse($customers as $customer)<tr><td><b>{{ $customer->customer_name }}</b></td><td>{{ $customer->customer_phone }}</td><td>{{ $customer->customer_district }}</td><td>{{ $customer->created_at?->format('d M Y') }}</td></tr>@empty<tr><td colspan="4" class="empty">কোনো কাস্টমার ডেটা পাওয়া যায়নি।</td></tr>@endforelse</tbody></table>@if($customers->hasPages())<div style="padding:16px 20px">{{ $customers->links() }}</div>@endif</section>
@endsection
