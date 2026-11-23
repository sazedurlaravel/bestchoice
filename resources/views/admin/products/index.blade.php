@extends('layouts.admin')
@section('title', 'Product Management')
@section('content')

<div class="pagehead">
    <div>
        <h1>Product Management</h1>
        <div class="sub">Total {{ $products->total() }} products</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form id="bulk-product-form" method="POST" action="{{ route('admin.products.bulk-delete') }}" onsubmit="return confirmBulkProductDelete()">
            @csrf
            <button class="btn secondary" type="submit"><i class="fa-solid fa-trash"></i> Delete Selected</button>
        </form>
        <a class="btn secondary" href="{{ route('admin.products.export') }}"><i class="fa-solid fa-download"></i> CSV Export</a>
        <a class="btn" href="{{ route('admin.products.create') }}"><i class="fa-solid fa-plus"></i> Add New Product</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('import_errors'))
    <div class="alert alert-error"><b>Import row errors:</b><ul>@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<section class="panel" style="margin-bottom:16px;padding:16px 20px">
    <form method="POST" action="{{ route('admin.products.import') }}" enctype="multipart/form-data" class="filter-form">
        @csrf
        <label for="productCsv">Import Products from CSV</label>
        <input id="productCsv" type="file" name="file" accept=".csv,text/csv" class="form-input" required>
        <button class="btn secondary" type="submit"><i class="fa-solid fa-upload"></i> Import</button>
        <small class="muted">Use the same column headers as the exported CSV. Products with new SKUs will be created; existing SKUs will be skipped.</small>
    </form>
</section>

<section class="panel">
    <div class="panelhead">
        <form method="GET" action="{{ route('admin.products') }}" class="filter-form">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by name or SKU..." class="form-input">
            <select name="category" class="form-input">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>
                        {{ $cat->parent ? $cat->parent->name.' › ' : '' }}{{ $cat->name }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="form-input">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="btn secondary">Filter</button>
            @if(request()->hasAny(['q','category','status']))
                <a href="{{ route('admin.products') }}" class="btn secondary">Reset</a>
            @endif
        </form>
    </div>
    <div class="tablewrap">
        <table class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" data-select-all="product"></th>
                    <th>Product Image</th>
                    <th>Name / SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" form="bulk-product-form" data-row-select="product"></td>
                        <td><img src="{{ $product->display_image }}" alt="{{ $product->name }}" style="width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid #e5e9e6"></td>
                        <td>
                            <b>{{ $product->name }}</b><br>
                            <span class="muted small">{{ $product->sku }}</span>
                        </td>
                        <td>{{ $product->category->name ?? '—' }}</td>
                        <td>
                            <b>{{ $product->formatted_selling_price }}</b>
                            @if($product->has_discount)
                                <br><del class="muted small">{{ $product->formatted_regular_price }}</del>
                            @endif
                        </td>
                        <td>
                            @if($product->stock_quantity <= $product->low_stock_threshold)
                                <span class="status pending">{{ $product->stock_quantity }}</span>
                            @else
                                {{ $product->stock_quantity }}
                            @endif
                        </td>
                        <td>
                            @if($product->is_active)
                                <span class="status active">Active</span>
                            @else
                                <span class="status inactive">Inactive</span>
                            @endif
                            @if($product->is_featured)
                                <span class="status featured">Featured</span>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('products.show', $product) }}" target="_blank" class="iconbtn" title="View in Store"><i class="fa-solid fa-eye"></i></a>
                                <a href="{{ route('admin.products.edit', $product) }}" class="iconbtn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete “{{ addslashes($product->name) }}”? This action cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="iconbtn danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
        <div style="padding:16px 20px">{{ $products->links() }}</div>
    @endif
</section>
<script>
    function confirmBulkProductDelete() {
        const selected = document.querySelectorAll('[data-row-select="product"]:checked');
        if (!selected.length) {
            alert('Select at least one product.');
            return false;
        }

        return confirm(`Delete ${selected.length} selected product(s)? This action cannot be undone.`);
    }

    document.querySelector('[data-select-all="product"]')?.addEventListener('change', (event) => {
        document.querySelectorAll('[data-row-select="product"]').forEach((checkbox) => { checkbox.checked = event.target.checked; });
    });
</script>
@endsection
