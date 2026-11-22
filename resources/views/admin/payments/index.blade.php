@extends('layouts.admin')
@section('title', 'Payment Management')
@section('content')
<div class="pagehead"><div><h1>Payment Management</h1><div class="sub">Online transactions, payment status and gateway settings</div></div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="stats" style="margin-bottom:20px">
    @foreach(['paid'=>'Successful','pending'=>'Pending','failed'=>'Failed','cancelled'=>'Cancelled','review'=>'Review Required'] as $key=>$label)
        <div class="stat"><div><div class="name">{{ $label }}</div><div class="value">{{ $counts[$key] ?? 0 }}</div></div></div>
    @endforeach
</section>

<section class="panel" style="margin-bottom:20px">
    <div class="panelhead"><h3>SSLCOMMERZ Gateway</h3></div>
    <div class="form-body">
        <p>{{ $configured ? 'Merchant credentials are configured.' : 'Merchant credentials are not configured.' }} · Environment: {{ config('services.sslcommerz.sandbox') ? 'Sandbox' : 'Live' }}</p>
        <p class="muted">The gateway payment page will show the active bKash, Nagad, Rocket, card, and other banking channels from the merchant account. Keep credentials in the .env file; they are not stored in the database.</p>
        @if(!$configured)<p>Add <code>SSLCOMMERZ_STORE_ID</code> and <code>SSLCOMMERZ_STORE_PASSWORD</code>, then refresh the config cache. Enter this IPN URL in the Merchant Panel: <code>{{ route('payments.sslcommerz.ipn') }}</code></p>@endif
        <form method="POST" action="{{ route('admin.payments.settings') }}" class="filter-form">@csrf @method('PATCH')
            <input type="hidden" name="enabled" value="0">
            <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="enabled" value="1" @checked($enabled) @disabled(!$configured)> Enable online payments</label>
            <button class="btn" type="submit">Save Settings</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panelhead"><h3>Transaction List</h3><span class="sub">{{ $payments->total() }} online payments</span></div>
    <div class="tablewrap"><table class="table">
        <thead><tr><th>Order</th><th>Transaction ID</th><th>Channel</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($payments as $payment)
            @php($labels = ['pending'=>'Pending','paid'=>'Successful','failed'=>'Failed','cancelled'=>'Cancelled','review'=>'Review Required'])
            <tr><td><a href="{{ route('admin.orders.show', $payment->order) }}"><code>{{ $payment->order->order_number }}</code></a></td><td><code>{{ $payment->transaction_id }}</code></td><td>{{ $payment->payment_channel ?? '—' }}</td><td><b>৳{{ number_format((float)$payment->amount, 2) }}</b></td><td><span class="status {{ $payment->status === 'paid' ? 'active' : ($payment->status === 'failed' || $payment->status === 'cancelled' ? 'inactive' : 'pending') }}">{{ $labels[$payment->status] ?? $payment->status }}</span></td><td>{{ $payment->created_at->format('d M Y, h:i A') }}</td><td><a href="{{ route('admin.orders.show', $payment->order) }}" class="iconbtn" title="View Order"><i class="fa-solid fa-eye"></i></a></td></tr>
        @empty
            <tr><td colspan="7" class="empty">No online payments yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($payments->hasPages())<div style="padding:16px 20px">{{ $payments->links() }}</div>@endif
</section>
@endsection
