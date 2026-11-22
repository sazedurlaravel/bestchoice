@extends('layouts.admin')
@section('title', 'অডিট লগ')
@section('content')
<div class="pagehead"><div><h1>অডিট লগ</h1><div class="sub">অ্যাডমিন ও স্টাফদের সংবেদনশীল কার্যক্রমের রেকর্ড</div></div></div>
<section class="panel"><div class="tablewrap"><table class="table"><thead><tr><th>সময়</th><th>ব্যবহারকারী</th><th>কার্যক্রম</th><th>রুট</th><th>স্ট্যাটাস</th><th>IP</th></tr></thead><tbody>
@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d M Y, h:i A') }}</td><td>{{ $log->user?->name ?? 'মুছে ফেলা ব্যবহারকারী' }}</td><td><code>{{ $log->action }}</code></td><td>{{ $log->method }} {{ $log->route_name ?: '—' }}</td><td>{{ $log->status_code ?: '—' }}</td><td>{{ $log->ip_address ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="empty">কোনো অডিট লগ নেই।</td></tr>@endforelse
</tbody></table></div>@if($logs->hasPages())<div style="padding:16px 20px">{{ $logs->links() }}</div>@endif</section>
@endsection
