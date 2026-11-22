@extends('layouts.admin')
@section('title', 'Inventory Management')
@section('content')

<div class="pagehead">
    <div>
        <h1>Inventory Management</h1>
        <div class="sub">Stock in/out and transaction log</div>
    </div>
    <div class="d-flex flex-wrap" style="gap:8px"><a class="btn secondary" href="{{ route('admin.suppliers') }}">Suppliers</a><a class="btn" href="{{ route('admin.purchases.create') }}">New Purchase Record</a></div>
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="dashboard-grid inventory-stock-grid">

    {{-- Stock Adjustment Form --}}
    <section class="panel">
        <div class="panelhead"><h3>Adjust Stock</h3></div>
        <form method="POST" action="{{ route('admin.inventory.adjust') }}" class="form-body" style="display:flex;flex-direction:column;gap:14px">
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="field">
                <label>Select Product <span class="req">*</span></label>
                <select name="product_id" class="form-input" required>
                    <option value="">— Choose Product —</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} (Stock: {{ $p->stock_quantity }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="inventory-variant">Variant (Size / Color / SKU)</label>
                <select id="inventory-variant" name="variant_id" class="form-input">
                    <option value="">— Main Product Stock —</option>
                    @foreach($products as $p)
                        @foreach($p->variants as $variant)
                            <option value="{{ $variant->id }}" @selected(old('variant_id') == $variant->id)>{{ $p->name }} — {{ $variant->size ?? '—' }} / {{ $variant->color ?? '—' }} / {{ $variant->sku ?? '—' }} (Stock: {{ $variant->stock_quantity }})</option>
                        @endforeach
                    @endforeach
                </select>
                <small class="muted">Select a variant for the selected product.</small>
            </div>
            <div class="field-row">
                <div class="field">
                    <label>Type <span class="req">*</span></label>
                    <select name="type" class="form-input" required>
                        <option value="stock_in" @selected(old('type', 'stock_in') === 'stock_in')>Stock In (+)</option>
                        <option value="stock_out" @selected(old('type') === 'stock_out')>Stock Out (−)</option>
                        <option value="adjustment" @selected(old('type') === 'adjustment')>Stock Adjustment (final quantity)</option>
                    </select>
                </div>
                <div class="field">
                    <label>Quantity <span class="req">*</span></label>
                    <input type="number" name="quantity" class="form-input" min="0" step="1" value="{{ old('quantity', 1) }}" required>
                </div>
            </div>
            <div class="field">
                <label for="expected-stock">Expected Current Stock (required for adjustment)</label>
                <input id="expected-stock" type="number" name="expected_stock" class="form-input" min="0" step="1" value="{{ old('expected_stock') }}">
                <small class="muted">For an adjustment, enter the current stock here and the final stock in the quantity field.</small>
            </div>
            <div class="field">
                <label>Reason</label>
                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" class="form-input" placeholder="e.g. new stock, sale, damaged...">
            </div>
            <button type="submit" class="btn">Update Stock</button>
        </form>
    </section>

    {{-- Low Stock Alert --}}
    <section class="panel">
        <div class="panelhead"><h3><i class="fa-solid fa-triangle-exclamation" style="color:#f4bb70"></i> Low Stock Products</h3></div>
        <div class="tablewrap">
            <table class="table">
                <thead><tr><th>Product</th><th>Stock</th><th>Threshold</th></tr></thead>
                <tbody>
                    @foreach($lowStockProducts as $p)
                        <tr>
                            <td><b>{{ $p->name }}</b><br><span class="muted small">{{ $p->sku }}</span></td>
                            <td><span class="status pending">{{ $p->stock_quantity }}</span></td>
                            <td>{{ $p->low_stock_threshold }}</td>
                        </tr>
                    @endforeach
                    @foreach($lowStockVariants ?? [] as $variant)
                        <tr>
                            <td><b>{{ $variant->product->name }}</b><div class="muted small">{{ $variant->name }} / {{ $variant->sku ?? '—' }}</div></td>
                            <td><span class="status pending">{{ $variant->stock_quantity }}</span></td>
                            <td>{{ $variant->product->low_stock_threshold }}</td>
                        </tr>
                    @endforeach
                    @if($lowStockProducts->isEmpty() && count($lowStockVariants ?? []) === 0)
                        <tr><td colspan="3" class="empty">All product stock levels are normal.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
</div>

{{-- Transaction Log --}}
<section class="panel" style="margin-top:20px">
    <div class="panelhead">
        <h3>Transaction Log</h3>
        <form method="GET" action="{{ route('admin.inventory') }}" class="filter-form">
            <select name="product_id" class="form-input">
                <option value="">All Products</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="type" class="form-input">
                <option value="">All Types</option>
                <option value="stock_in" @selected(request('type') === 'stock_in')>Stock In</option>
                <option value="stock_out" @selected(request('type') === 'stock_out')>Stock Out</option>
                <option value="adjustment" @selected(request('type') === 'adjustment')>Stock Adjustment</option>
            </select>
            <button type="submit" class="btn secondary">Filter</button>
        </form>
    </div>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Product</th><th>Type</th><th>Change</th><th>Before</th><th>After</th><th>Reason</th><th>Operator</th><th>Reference</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td><small>{{ $tx->created_at->format('d M Y, h:i A') }}</small></td>
                        <td>
                            {{ $tx->product->name ?? '—' }}
                            <div class="muted small">SKU: {{ $tx->variant?->sku ?? $tx->product?->sku ?? '—' }}</div>
                            @if($tx->variant)
                                <div class="muted small">{{ $tx->variant->name }} — {{ $tx->variant->size ?? '—' }} / {{ $tx->variant->color ?? '—' }}</div>
                            @endif
                        </td>
                        <td>
                            @if($tx->type === 'stock_in')
                                <span class="status active">Stock In</span>
                            @elseif($tx->type === 'stock_out')
                                <span class="status pending">Stock Out</span>
                            @else
                                <span class="status">Stock Adjustment</span>
                            @endif
                        </td>
                        <td><b>{{ $tx->quantity_change > 0 ? '+' : '' }}{{ $tx->quantity_change }}</b></td>
                        <td>{{ $tx->stock_before }}</td>
                        <td>{{ $tx->stock_after }}</td>
                        <td><small class="muted">{{ $tx->reason ?? '—' }}</small></td>
                        <td>{{ $tx->user?->name ?? '—' }}</td>
                        <td>
                            @if($tx->reference_type && $tx->reference_id)
                                @if($tx->reference_type === 'purchase_item' && $purchaseItems->has($tx->reference_id))
                                    @php($purchase = $purchaseItems->get($tx->reference_id)->purchase)
                                    <a href="{{ route('admin.purchases.show', $purchase) }}"><small>{{ $purchase->purchase_number }}</small></a>
                                @else
                                    <small>{{ $tx->reference_type }} #{{ $tx->reference_id }}</small>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No transactions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div style="padding:16px 20px">{{ $transactions->links() }}</div>
    @endif
</section>
@endsection
