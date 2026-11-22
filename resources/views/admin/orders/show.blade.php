@extends('layouts.admin')
@section('title', 'অর্ডার: '.$order->order_number)
@section('content')

@php
    $statusLabels = ['pending' => 'অপেক্ষমাণ', 'processing' => 'প্রক্রিয়াকরণ', 'shipped' => 'পাঠানো হয়েছে', 'delivered' => 'ডেলিভারি হয়েছে', 'cancelled' => 'বাতিল'];
    $statusTargets = ['pending' => ['processing', 'cancelled'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => []];
    $returnLabels = ['none' => 'শুরু', 'requested' => 'অনুরোধকৃত', 'approved' => 'অনুমোদিত', 'rejected' => 'প্রত্যাখ্যাত', 'received' => 'ফেরত গৃহীত', 'restocked' => 'স্টকে ফেরত', 'completed' => 'সম্পন্ন'];
    $returnTargets = ['requested' => ['approved', 'rejected'], 'approved' => ['received'], 'received' => ['restocked', 'completed'], 'restocked' => ['completed'], 'rejected' => [], 'completed' => []];
    $historyLabel = static function ($status) use ($statusLabels, $returnLabels) {
        $parts = explode(':', $status);
        return count($parts) === 3 && $parts[0] === 'return'
            ? 'রিটার্ন #'.$parts[1].' — '.($returnLabels[$parts[2]] ?? $parts[2])
            : ($statusLabels[$status] ?? $status);
    };
@endphp

<div class="pagehead order-lifecycle-head">
    <div>
        <h1>অর্ডার: {{ $order->order_number }}</h1>
        <div class="sub"><a href="{{ route('admin.orders') }}">← অর্ডার তালিকায় ফিরুন</a></div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <a href="https://wa.me/88{{ ltrim($order->customer_phone, '0') }}?text={{ urlencode('আপনার অর্ডার '.$order->order_number.' সম্পর্কে জানাতে চাই।') }}"
           target="_blank" rel="noopener"
           style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;background:#25d366;color:#fff;font-weight:600;font-size:13px;text-decoration:none">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp MSG
        </a>
        <a href="{{ route('admin.orders.print', $order) }}" target="_blank"
           style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;background:#f1f5f9;color:#111;font-weight:600;font-size:13px;text-decoration:none;border:1px solid #e2e8f0">
            <i class="fa-solid fa-print"></i> ইনভয়েস প্রিন্ট
        </a>
    </div>
    @if($statusTargets[$order->status] ?? [])
        <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="filter-form">
            @csrf @method('PATCH')
            <div class="field">
                <label for="order-status">পরবর্তী স্ট্যাটাস</label>
                <select id="order-status" name="status" class="form-input" required>
                    @foreach($statusTargets[$order->status] as $target)
                        <option value="{{ $target }}" @selected(! old('_return_id') && old('status') === $target)>{{ $statusLabels[$target] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status-reason">কারণ (বাতিলের জন্য আবশ্যক)</label>
                <input id="status-reason" type="text" name="reason" class="form-input" maxlength="1000" value="{{ old('order_item_id') ? '' : old('reason') }}">
            </div>
            <button type="submit" class="btn">স্ট্যাটাস আপডেট করুন</button>
        </form>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
@endif


@if($order->payment_method === 'cash_on_delivery')
@php
    $reviewLabels = [
        'ready'              => ['label' => 'Ready to Ship', 'class' => 'badge-success'],
        'needs_confirmation' => ['label' => 'Needs Confirmation', 'class' => 'badge-warning'],
        'needs_correction'   => ['label' => 'Needs Correction', 'class' => 'badge-error'],
    ];
    $currentReview = $reviewLabels[$order->review_status] ?? null;
@endphp
<section class="panel" style="margin-bottom:20px">
    <div class="panelhead">
        <h3>COD Order Review</h3>
        @if($currentReview)
            <span class="badge {{ $currentReview['class'] }}">{{ $currentReview['label'] }}</span>
        @else
            <span class="badge badge-muted">Not Reviewed</span>
        @endif
    </div>

    @if($order->review_reasons && count($order->review_reasons))
        <div style="margin-bottom:14px">
            <p class="muted" style="margin-bottom:6px">Rules triggered:</p>
            <ul style="margin:0;padding-left:18px">
                @foreach($order->review_reasons as $reason)
                    <li>{{ \App\Services\Orders\OrderReviewService::reasonLabel($reason) }}</li>
                @endforeach
            </ul>
        </div>
    @elseif($order->review_status === 'ready')
        <p class="muted" style="margin-bottom:12px">No rules triggered — safe to book.</p>
    @endif

    @if($order->confirmed_by && $order->confirmed_at)
        <p class="muted">Confirmed by <b>{{ $order->confirmedBy?->name }}</b> on {{ $order->confirmed_at->format('d M Y H:i') }}@if($order->confirmation_note) · "{{ $order->confirmation_note }}"@endif</p>
    @endif

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
        @if(in_array($order->review_status, ['needs_confirmation', 'needs_correction'], true))
            <form method="POST" action="{{ route('admin.orders.review.confirm', $order) }}" class="filter-form" style="flex:1;min-width:260px">
                @csrf
                <label>Confirmation note (optional)<input class="form-input" name="note" maxlength="1000" placeholder="e.g. Customer confirmed by phone"></label>
                <button class="btn" type="submit">✓ Mark as Confirmed — Ready to Ship</button>
            </form>
        @endif

        <div style="display:flex;flex-direction:column;gap:8px">
            <form method="POST" action="{{ route('admin.orders.review.rerun', $order) }}">
                @csrf
                <button class="btn secondary" type="submit">↻ Re-run Review Rules</button>
            </form>
            @if($order->status === 'delivered')
                <form method="POST" action="{{ route('admin.orders.review.refusal', $order) }}" class="filter-form">
                    @csrf
                    <input class="form-input" name="reason" maxlength="500" placeholder="Refusal reason (optional)">
                    <button class="btn secondary" type="submit" style="background:#c0392b;color:#fff">Record Delivery Refusal</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endif

@php $steadfastShipment = $order->shipments->firstWhere('courier', 'steadfast'); @endphp
<section class="panel" style="margin-bottom:20px"><div class="panelhead"><h3>Steadfast delivery</h3></div>
@if($steadfastShipment)
    <p>Booking: <b>{{ $steadfastShipment->booking_state }}</b> · Provider status: <b>{{ $steadfastShipment->provider_status ?? '—' }}</b> · Local status: <b>{{ $steadfastShipment->local_status ?? '—' }}</b></p>
    @if($steadfastShipment->consignment_id)<p>Consignment: <code>{{ $steadfastShipment->consignment_id }}</code> · Tracking: <code>{{ $steadfastShipment->tracking_code }}</code></p>@endif
    @if($steadfastShipment->failure_reason)<p class="alert alert-error">{{ $steadfastShipment->failure_reason }}</p>@endif
    @if(in_array($steadfastShipment->booking_state, ['booked', 'outcome_unknown'], true))<form method="POST" action="{{ route('admin.shipments.refresh', $steadfastShipment) }}">@csrf<button class="btn secondary" type="submit">{{ $steadfastShipment->booking_state === 'outcome_unknown' ? 'Reconcile booking' : 'Refresh delivery status' }}</button></form>@endif
@else
    <p class="muted">Review the server-calculated COD amount before booking. Booking does not change stock, payment, or order status.</p>
    <form method="POST" action="{{ route('admin.orders.steadfast.book', $order) }}" class="formgrid">@csrf
        <label>Invoice reference<input class="form-input" value="{{ $order->order_number }}" readonly></label>
        <label>Recipient name<input class="form-input" value="{{ $order->customer_name }}" readonly></label>
        <label>Main phone<input class="form-input" value="{{ $order->customer_phone }}" readonly></label>
        <label>Alternative phone<input class="form-input" value="{{ $order->customer_additional_phone ?? '' }}" readonly></label>
        <label class="full">Delivery address<textarea class="form-input" rows="2" readonly>{{ trim($order->customer_address.' '.($order->customer_district ?? '')) }}</textarea></label>
        <label>COD amount<input class="form-input" value="৳{{ number_format(max(0, (float) $order->total - (float) $order->payments->where('status', 'paid')->sum('amount')), 2) }}" readonly></label>
        <label>Delivery note<input class="form-input" name="note" maxlength="500" value="{{ old('note') }}"></label>
        @if($order->status !== 'cancelled')<div class="full"><button class="btn" type="submit">Send to Steadfast</button></div>@else<p class="muted">Cancelled orders cannot be sent to Steadfast.</p>@endif
    </form>
@endif
</section>

@if($cancellationRequest)
    <section class="panel" style="margin-bottom:18px"><h3>কাস্টমারের বাতিল অনুরোধ · {{ $cancellationRequest->status }}</h3><p>{{ $cancellationRequest->reason }}</p>
    @if($cancellationRequest->status === 'requested')<form method="POST" action="{{ route('admin.orders.cancellation-requests.update', [$order, $cancellationRequest]) }}" class="filter-form">@csrf @method('PATCH')<label>পর্যালোচনা নোট<input class="form-input" name="note" maxlength="1000"></label><button class="btn" name="status" value="approved">অনুমোদন ও অর্ডার বাতিল</button><button class="btn secondary" name="status" value="rejected">প্রত্যাখ্যান</button></form>@endif</section>
@endif

@if($errors->any())
    <div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="dashboard-grid order-summary-grid">
    <section class="panel">
        <div class="panelhead"><h3>কাস্টমার তথ্য</h3></div>
        <div class="form-body" style="display:flex;flex-direction:column;gap:12px">
            <div><small class="muted">নাম</small><div><b>{{ $order->customer_name }}</b></div></div>
            <div><small class="muted">মোবাইল</small><div>{{ $order->customer_phone }}</div></div>
            <div><small class="muted">জেলা</small><div>{{ $order->customer_district }}</div></div>
            <div><small class="muted">ঠিকানা</small><div>{{ $order->customer_address }}</div></div>
            @if($order->note)
                <div><small class="muted">নোট</small><div>{{ $order->note }}</div></div>
            @endif
        </div>
    </section>
    <section class="panel">
        <div class="panelhead"><h3>অর্ডার সারসংক্ষেপ</h3></div>
        <div class="form-body" style="display:flex;flex-direction:column;gap:12px">
            <div><small class="muted">অর্ডার নম্বর</small><div><code>{{ $order->order_number }}</code></div></div>
            <div><small class="muted">তারিখ</small><div>{{ $order->created_at->format('d M Y, h:i A') }}</div></div>
            <div><small class="muted">পেমেন্ট পদ্ধতি</small><div>{{ $order->payment_method === 'sslcommerz' ? 'SSLCOMMERZ — bKash/Nagad/কার্ড/ব্যাংকিং' : 'ক্যাশ অন ডেলিভারি' }}</div></div>
            <div><small class="muted">স্ট্যাটাস</small><div><span class="status">{{ $order->status_label }}</span></div></div>
            @if($order->cancellation_reason)
                <div><small class="muted">বাতিলের কারণ</small><div>{{ $order->cancellation_reason }}</div></div>
            @endif
            <div style="border-top:1px solid var(--line);padding-top:12px">
                <div style="display:flex;justify-content:space-between"><span>সাবটোটাল</span><span>৳{{ number_format($order->subtotal, 0) }}</span></div>
                <div style="display:flex;justify-content:space-between"><span>শিপিং</span><span>৳{{ number_format($order->shipping_charge, 0) }}</span></div>
                <div style="display:flex;justify-content:space-between;font-weight:800;font-size:16px;margin-top:8px"><span>মোট</span><span>৳{{ number_format($order->total, 0) }}</span></div>
            </div>
        </div>
    </section>
</div>

<section class="panel" style="margin-top:20px">
    <div class="panelhead"><h3>পেমেন্টের তথ্য</h3></div>
    <div class="form-body">
        <p>পদ্ধতি: <b>{{ $order->payment_method === 'sslcommerz' ? 'SSLCOMMERZ (bKash/Nagad/কার্ড/ব্যাংকিং)' : 'ক্যাশ অন ডেলিভারি' }}</b></p>
        @forelse($order->payments as $payment)
            <div style="padding:10px 0;border-top:1px solid var(--line)"><b>{{ $payment->transaction_id }}</b> · {{ $payment->status }} · ৳{{ number_format((float)$payment->amount, 2) }} @if($payment->payment_channel) · {{ $payment->payment_channel }}@endif @if($payment->bank_transaction_id)<div class="muted small">ব্যাংক ট্রানজেকশন: {{ $payment->bank_transaction_id }}</div>@endif</div>
        @empty
            <p class="muted">এই অর্ডারের অনলাইন পেমেন্ট লেনদেন নেই।</p>
        @endforelse
    </div>
</section>

<section class="panel" style="margin-top:20px">
    <div class="panelhead"><h3>অর্ডার আইটেম</h3></div>
    <div class="tablewrap">
        <table class="table">
            <thead><tr><th>ছবি</th><th>পণ্য</th><th>SKU</th><th>একক মূল্য</th><th>পরিমাণ</th><th>সাবটোটাল</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            @if($item->product?->main_image)
                                <img src="{{ $item->product->main_image }}" alt="{{ $item->product_name }}" loading="lazy" style="width:52px;height:52px;object-fit:cover;border-radius:6px;border:1px solid #e5e9e6">
                            @endif
                        </td>
                        <td><b>{{ $item->product_name }}</b>@if($item->variant_name)<div class="muted small">{{ $item->variant_name }}</div>@endif</td>
                        <td><code>{{ $item->product_sku }}</code></td>
                        <td>৳{{ number_format($item->unit_price, 0) }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td><b>৳{{ number_format($item->subtotal, 0) }}</b></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panelhead"><h3>স্ট্যাটাস পরিবর্তনের ইতিহাস</h3></div>
    <div class="tablewrap">
        <table class="table">
            <thead><tr><th>তারিখ</th><th>আগে</th><th>পরে</th><th>কারণ / নোট</th><th>অপারেটর</th></tr></thead>
            <tbody>
                @forelse($order->statusHistories as $history)
                    <tr>
                        <td>{{ $history->created_at->format('d M Y, h:i A') }}</td>
                        <td>{{ $historyLabel($history->from_status) }}</td>
                        <td>{{ $historyLabel($history->to_status) }}</td>
                        <td>{{ $history->reason ?? '—' }}</td>
                        <td>{{ $history->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">স্ট্যাটাস পরিবর্তনের ইতিহাস নেই।</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if(auth()->user()?->isAdmin() && $order->status === 'delivered')
    <section class="panel">
        <div class="panelhead"><h3>পণ্য রিটার্ন</h3></div>
        <form method="POST" action="{{ route('admin.orders.returns.store', $order) }}" class="form-body order-return-form">
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="field">
                <label for="return-item">অর্ডার আইটেম <span class="req">*</span></label>
                <select id="return-item" name="order_item_id" class="form-input" required>
                    <option value="">— আইটেম বেছে নিন —</option>
                    @foreach($order->items as $item)
                        <option value="{{ $item->id }}" @selected(old('order_item_id') == $item->id)>{{ $item->product_name }} — {{ $item->variant_name ?? 'মূল পণ্য' }} / {{ $item->product_sku }} (অর্ডারের পরিমাণ: {{ $item->quantity }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="return-quantity">পরিমাণ <span class="req">*</span></label>
                    <input id="return-quantity" type="number" name="quantity" class="form-input" min="1" step="1" value="{{ old('quantity', 1) }}" required>
                </div>
                <div class="field">
                    <label for="return-reason">রিটার্নের কারণ <span class="req">*</span></label>
                    <input id="return-reason" type="text" name="reason" class="form-input" maxlength="1000" value="{{ old('order_item_id') ? old('reason') : '' }}" required>
                </div>
            </div>
            <button type="submit" class="btn">রিটার্ন অনুরোধ সংরক্ষণ করুন</button>
        </form>
        <div class="tablewrap">
            <table class="table">
                <thead><tr><th>রিটার্ন / আইটেম</th><th>পরিমাণ</th><th>স্ট্যাটাস</th><th>কারণ / নোট</th><th>স্টকে ফেরত</th><th>পরবর্তী পদক্ষেপ</th></tr></thead>
                <tbody>
                    @forelse($order->returns as $return)
                        @php $returnItem = $order->items->firstWhere('id', $return->order_item_id); @endphp
                        <tr>
                            <td>
                                <b>#{{ $return->id }} — {{ $returnItem?->product_name ?? '—' }}</b>
                                <div class="muted small">{{ $returnItem?->variant_name }} / {{ $returnItem?->product_sku }}</div>
                            </td>
                            <td>{{ $return->quantity }}</td>
                            <td><span class="status">{{ $returnLabels[$return->status] ?? $return->status }}</span></td>
                            <td>{{ $return->reason }}@if($return->disposition_note)<div class="muted small">{{ $return->disposition_note }}</div>@endif</td>
                            <td>{{ $return->restocked_quantity }}</td>
                            <td>
                                @if($returnTargets[$return->status] ?? [])
                                    <form method="POST" action="{{ route('admin.orders.returns.update', [$order, $return]) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="_return_id" value="{{ $return->id }}">
                                        <div class="field">
                                            <label for="return-status-{{ $return->id }}">পরবর্তী স্ট্যাটাস</label>
                                            <select id="return-status-{{ $return->id }}" name="status" class="form-input" required>
                                                @foreach($returnTargets[$return->status] as $target)
                                                    <option value="{{ $target }}" @selected(old('_return_id') == $return->id && old('status') === $target)>{{ $returnLabels[$target] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="return-note-{{ $return->id }}">পরিদর্শন / নিষ্পত্তির নোট @if($return->status === 'received')<span class="req">*</span>@endif</label>
                                            <textarea id="return-note-{{ $return->id }}" name="note" class="form-input" rows="2" maxlength="1000" @required($return->status === 'received')>{{ old('_return_id') == $return->id ? old('note') : '' }}</textarea>
                                        </div>
                                        <button type="submit" class="btn">রিটার্ন আপডেট করুন</button>
                                    </form>
                                @else
                                    <span class="muted">আর কোনো পদক্ষেপ নেই।</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">কোনো রিটার্ন অনুরোধ নেই।</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
