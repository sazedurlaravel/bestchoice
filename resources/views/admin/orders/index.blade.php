@extends('layouts.admin')
@section('title', 'অর্ডার ব্যবস্থাপনা')
@section('content')

@php
$allStatuses = [
    'pending'    => ['label' => 'অপেক্ষমাণ',      'icon' => 'fa-clock'],
    'processing' => ['label' => 'প্রক্রিয়াকরণ',    'icon' => 'fa-box'],
    'shipped'    => ['label' => 'পাঠানো হয়েছে',   'icon' => 'fa-truck'],
    'delivered'  => ['label' => 'ডেলিভারি',        'icon' => 'fa-circle-check'],
    'cancelled'  => ['label' => 'বাতিল',            'icon' => 'fa-ban'],
];
$total = $statusCounts->sum();
@endphp

<div class="pagehead orders-pagehead">
    <div>
        <h1>অর্ডার ব্যবস্থাপনা</h1>
        <div class="sub">মোট {{ $total }} অর্ডার</div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
@endif

{{-- Status Pipeline Bar --}}
<div class="order-pipeline orders-kpis">
    <a href="{{ route('admin.orders') }}"
       class="pipeline-tab {{ ! request('status') ? 'pipeline-active' : '' }}"
       >
        <span style="font-size:22px;font-weight:800">{{ $total }}</span>
        <span style="font-size:11px;margin-top:2px">সব অর্ডার</span>
    </a>
    @foreach($allStatuses as $key => $meta)
        @php $count = $statusCounts[$key] ?? 0; $active = request('status') === $key; @endphp
        <a href="{{ route('admin.orders', ['status' => $key] + request()->only('q')) }}"
           class="pipeline-tab {{ $active ? 'pipeline-active' : '' }}"
           >
            <span style="font-size:22px;font-weight:800">{{ $count }}</span>
            <span style="font-size:11px;margin-top:2px"><i class="fa-solid {{ $meta['icon'] }}" style="margin-right:3px"></i>{{ $meta['label'] }}</span>
        </a>
    @endforeach
</div>

<section class="panel">
    {{-- Filter Bar --}}
    <div class="panelhead orders-filter-strip">
        <form method="GET" action="{{ route('admin.orders') }}" class="filter-form orders-filter-form">
            <label class="orders-filter-field"><span><i class="fa-regular fa-calendar"></i> শুরু তারিখ</span><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input"></label>
            <label class="orders-filter-field"><span><i class="fa-regular fa-calendar"></i> শেষ তারিখ</span><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input"></label>
            <label class="orders-filter-field"><span><i class="fa-solid fa-warehouse"></i> Warehouse</span><select class="form-input" disabled><option>All Warehouse</option></select></label>
            <label class="orders-filter-field"><span><i class="fa-solid fa-tag"></i> Sale Type</span><select name="sale_type" class="form-input"><option value="">All Sale</option><option value="cash_on_delivery" @selected(request('sale_type') === 'cash_on_delivery')>Cash on Delivery</option><option value="sslcommerz" @selected(request('sale_type') === 'sslcommerz')>Online Payment</option></select></label>
            <label class="orders-filter-field"><span><i class="fa-solid fa-truck"></i> Courier</span><select name="courier" class="form-input"><option value="">All Courier</option>@foreach($couriers as $courier)<option value="{{ $courier }}" @selected(request('courier') === $courier)>{{ ucfirst($courier) }}</option>@endforeach</select></label>
            <label class="orders-filter-field"><span><i class="fa-solid fa-print"></i> Print</span><select class="form-input" disabled><option>All</option></select></label>
            <label class="orders-filter-field orders-search-field"><span><i class="fa-solid fa-magnifying-glass"></i> Search</span><input type="search" name="q" value="{{ request('q') }}" placeholder="নাম, ফোন বা অর্ডার নম্বর..." class="form-input"></label>
            <select name="status" class="form-input" onchange="this.form.submit()">
                <option value="">সব স্ট্যাটাস</option>
                @foreach($allStatuses as $v => $meta)
                    <option value="{{ $v }}" @selected(request('status') === $v)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn secondary">ফিল্টার</button>
            @if(request()->hasAny(['q','status','date_from','date_to','sale_type','courier']))
                <a href="{{ route('admin.orders') }}" class="btn secondary">রিসেট</a>
            @endif
        </form>
    </div>

    {{-- Bulk Action Bar --}}
    <div id="bulk-bar" class="orders-action-toolbar" style="display:flex">
        <span id="bulk-count" class="orders-toolbar-selection">অর্ডার নির্বাচন করুন</span>
        <form method="POST" action="{{ route('admin.orders.bulk-update') }}" id="bulk-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            @csrf
            <div id="bulk-ids"></div>
            <input type="hidden" name="action" id="bulk-action-input" value="">

            {{-- Status update --}}
            <select id="bulk-status-select" class="form-input" style="width:auto" data-requires-selection disabled>
                <option value="">স্ট্যাটাস বেছে নিন...</option>
                @foreach($allStatuses as $v => $meta)
                    <option value="{{ $v }}">→ {{ $meta['label'] }}</option>
                @endforeach
            </select>
            <button type="button" class="btn orders-toolbar-btn status" data-requires-selection disabled onclick="submitBulk('status', document.getElementById('bulk-status-select').value)"><i class="fa-solid fa-rotate"></i> স্ট্যাটাস আপডেট</button>

            <button type="button" class="btn orders-toolbar-btn courier" data-requires-selection disabled onclick="submitBulk('courier', 'steadfast')"><i class="fa-solid fa-truck-fast"></i> Send to Steadfast</button>

        </form>
    </div>

    <div class="tablewrap">
        <table class="table orders-table" id="orders-table">
            <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" id="select-all" title="সব নির্বাচন করুন"></th>
                    <th>অ্যাকশন</th>
                    <th>তারিখ</th>
                    <th>ইমেজ</th>
                    <th>প্রোডাক্টস</th>
                    <th>কাস্টমার</th>
                    <th>সেল স্ট্যাটাস</th>
                    <th>নোট</th>
                    <th title="Fraud Risk Score (rule-based)">Fraud CK</th>
                    <th>কুরিয়ার</th>
                    <th>মোট</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    @php
                        $badgeStyle = match($order->status) {
                            'pending'    => 'background:#fef9c3;color:#854d0e;border:1px solid #fde68a',
                            'processing' => 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe',
                            'shipped'    => 'background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd',
                            'delivered'  => 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0',
                            'cancelled'  => 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca',
                            default      => '',
                        };
                    @endphp
                    <tr data-order-id="{{ $order->id }}">
                        <td><input type="checkbox" class="order-checkbox" value="{{ $order->id }}"></td>
                        <td>
                            <code style="font-size:12px">{{ $order->order_number }}</code>
                            <div class="orders-row-actions">
                                <a href="{{ route('admin.orders.show', $order) }}" class="iconbtn" title="বিস্তারিত দেখুন"><i class="fa-solid fa-eye"></i></a>
                                <a href="{{ route('admin.orders.show', $order) }}" class="order-edit-link" title="অর্ডার এডিট করুন"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                <a href="{{ route('admin.orders.print', $order) }}" class="iconbtn" title="ইনভয়েস প্রিন্ট করুন" target="_blank"><i class="fa-solid fa-print"></i></a>
                                @php
                                    $steadfastShipment = $order->shipments->firstWhere('courier', 'steadfast');
                                @endphp
                                @if($order->status !== 'cancelled' && ! $steadfastShipment)
                                    <form method="POST" action="{{ route('admin.orders.steadfast.book', $order) }}" class="order-steadfast-form" onsubmit="return confirm('এই অর্ডারটি Steadfast-এ পাঠাবেন?')">
                                        @csrf
                                        <button type="submit" class="order-steadfast-btn" title="Steadfast-এ পাঠান"><i class="fa-solid fa-truck-fast"></i> Steadfast</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                        <td><small>{{ $order->created_at->format('d M Y') }}<br>{{ $order->created_at->format('h:i A') }}</small></td>
                        <td>
                            @if($order->items->first()?->product?->main_image)
                                <img src="{{ $order->items->first()->product->main_image }}" alt="{{ $order->items->first()->product_name }}" loading="lazy" class="order-product-image">
                            @else
                                <span class="order-image-placeholder"><i class="fa-solid fa-image"></i></span>
                            @endif
                        </td>
                        <td>
                            <div class="order-product-lines">
                                @foreach($order->items->take(3) as $item)
                                    @php
                                        $variantOptions = collect([
                                            'size' => $item->variant?->size,
                                            'color' => $item->variant?->color,
                                        ])->filter(fn ($option) => filled($option))->values();
                                        if ($variantOptions->isEmpty()) {
                                            $variantOptions = collect($item->variant?->options ?? [])
                                                ->except('name')
                                                ->filter(fn ($option) => filled($option))
                                                ->values();
                                        }
                                        $variantLabel = $variantOptions->unique()->implode(' / ') ?: $item->variant_name;
                                    @endphp
                                    <div class="order-product-line">
                                        <span class="order-product-name" title="{{ $item->product_name }}">{{ $item->product_name }}</span>
                                        @if($variantLabel)
                                            <small class="order-product-variant" title="{{ $variantLabel }}"><i class="fa-solid fa-sliders"></i> {{ $variantLabel }}</small>
                                        @endif
                                        <small class="muted">×{{ $item->quantity }}</small>
                                    </div>
                                @endforeach
                            </div>
                            @if($order->items->count() > 3)<small class="muted">+{{ $order->items->count() - 3 }} আরও পণ্য</small>@endif
                        </td>
                        <td>
                            <b>{{ $order->customer_name }}</b><br>
                            <small class="muted"><a href="tel:{{ $order->customer_phone }}" style="color:inherit;text-decoration:none">{{ $order->customer_phone }}</a></small><br>
                            <small class="muted">{{ $order->customer_district }}</small><br>
                            <a href="https://wa.me/88{{ ltrim($order->customer_phone, '0') }}?text={{ urlencode('আপনার অর্ডার '.$order->order_number.' সম্পর্কে জানাতে চাই।') }}" target="_blank" rel="noopener" title="WhatsApp এ মেসেজ করুন" class="order-whatsapp"><i class="fa-brands fa-whatsapp"></i> MSG</a>
                        </td>
                        <td class="order-status-cell">
                            <span class="order-status-badge" style="{{ $badgeStyle }}">{{ $order->status_label }}</span>
                            <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="order-status-form">
                                @csrf @method('PATCH')
                                <select name="status" class="form-input" onchange="this.form.submit()" title="স্ট্যাটাস পরিবর্তন করুন">
                                    @foreach($allStatuses as $v => $meta)<option value="{{ $v }}" @selected($order->status === $v)>{{ $meta['label'] }}</option>@endforeach
                                </select>
                            </form>
                            @if($order->payment_method === 'cash_on_delivery')
                                @php $rsBadge = match($order->review_status) { 'ready' => ['🟢', '#16a34a', '#dcfce7', 'Ready'], 'needs_confirmation' => ['🟡', '#d97706', '#fef3c7', 'Confirm'], 'needs_correction' => ['🔴', '#dc2626', '#fee2e2', 'Correct'], default => ['⚪', '#6b7280', '#f3f4f6', 'Pending'] }; @endphp
                                <small style="color:{{ $rsBadge[1] }};font-weight:700">{{ $rsBadge[0] }} {{ $rsBadge[3] }}</small>
                                @if(in_array($order->review_status, ['needs_confirmation', 'needs_correction'], true))
                                    <form method="POST" action="{{ route('admin.orders.review.confirm', $order) }}" class="order-review-form">
                                        @csrf
                                        <button type="submit" class="order-review-btn" title="Approve COD review"><i class="fa-solid fa-check"></i> Approve COD</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                        <td style="max-width:140px">
                            <form method="POST" action="{{ route('admin.orders.note', $order) }}" class="inline-note-form">
                                @csrf @method('PATCH')
                                <textarea name="admin_note" rows="2" class="form-input" placeholder="নোট লিখুন..." onblur="this.form.requestSubmit()">{{ $order->admin_note }}</textarea>
                            </form>
                        </td>
                        @php
                            $fraudScore = $fraudScores[$order->id] ?? 0;
                            $fraudColor = $fraudScore >= 70 ? '#dc2626' : ($fraudScore >= 40 ? '#d97706' : '#16a34a');
                            $fraudBg    = $fraudScore >= 70 ? '#fee2e2' : ($fraudScore >= 40 ? '#fef3c7' : '#dcfce7');
                        @endphp
                        <td style="text-align:center;min-width:70px">
                            <span title="ফ্রড রিস্ক: {{ $fraudScore }}%&#10;{{ $fraudScore >= 70 ? 'উচ্চ ঝুঁকি' : ($fraudScore >= 40 ? 'মাঝারি ঝুঁকি' : 'কম ঝুঁকি') }}"
                                  style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:800;background:{{ $fraudBg }};color:{{ $fraudColor }};min-width:48px">
                                {{ $fraudScore }}%
                            </span>
                        </td>
                        <td>
                            @php($shipment = $order->shipments->first())
                            @if($shipment)
                                <span class="order-courier"><i class="fa-solid fa-truck"></i>{{ ucfirst($shipment->courier) }}</span>
                                <small class="muted d-block">{{ $shipment->local_status ?: $shipment->booking_state }}</small>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td><b>৳{{ number_format($order->total, 0) }}</b><br><small class="muted">{{ $order->payment_method === 'cash_on_delivery' ? 'COD' : 'অনলাইন' }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="empty">কোনো অর্ডার পাওয়া যায়নি।</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($orders->hasPages())
        <div style="padding:16px 20px">{{ $orders->links() }}</div>
    @endif
</section>

@push('scripts')
<script>
(function () {
    const selectAll = document.getElementById('select-all');
    const checkboxes = () => document.querySelectorAll('.order-checkbox');
    const bulkBar = document.getElementById('bulk-bar');
    const bulkCount = document.getElementById('bulk-count');
    const bulkIdsContainer = document.getElementById('bulk-ids');

    function selectedIds() {
        return [...checkboxes()].filter(c => c.checked).map(c => c.value);
    }

    function updateBulkBar() {
        const ids = selectedIds();
        bulkBar.style.display = 'flex';
        bulkCount.textContent = ids.length > 0 ? ids.length + ' টি নির্বাচিত' : 'অর্ডার নির্বাচন করুন';
        document.querySelectorAll('[data-requires-selection]').forEach((control) => {
            control.disabled = ids.length === 0;
        });
    }

    selectAll.addEventListener('change', function () {
        checkboxes().forEach(c => c.checked = this.checked);
        updateBulkBar();
    });

    document.querySelectorAll('.order-checkbox').forEach(c => {
        c.addEventListener('change', updateBulkBar);
    });

    updateBulkBar();

    window.submitBulk = function (action, value) {
        const ids = selectedIds();
        if (ids.length === 0) { alert('অন্তত একটি অর্ডার নির্বাচন করুন।'); return; }
        if (action === 'status' && ! value) { alert('স্ট্যাটাস বেছে নিন।'); return; }
        if (action === 'assign' && ! value) { alert('স্টাফ বেছে নিন।'); return; }

        document.getElementById('bulk-action-input').value = action;
        bulkIdsContainer.innerHTML = '';
        ids.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'order_ids[]'; inp.value = id;
            bulkIdsContainer.appendChild(inp);
        });

        if (action === 'status') {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'status'; inp.value = value;
            bulkIdsContainer.appendChild(inp);
        } else if (action === 'assign') {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'assigned_to'; inp.value = value;
            bulkIdsContainer.appendChild(inp);
        } else if (action === 'courier') {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'courier'; inp.value = value;
            bulkIdsContainer.appendChild(inp);
        }

        if (confirm(ids.length + ' টি অর্ডার আপডেট করবেন?')) {
            document.getElementById('bulk-form').submit();
        }
    };
})();
</script>
@endpush

@endsection
