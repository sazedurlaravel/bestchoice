@extends('layouts.admin')
@section('title', 'New Purchase Record')
@section('content')
<div class="pagehead"><div><h1>New Purchase Record</h1><div class="sub">Quantities will be added to stock upon receipt</div></div><a class="btn secondary" href="{{ route('admin.purchases') }}">Purchase Records</a></div>
@if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($suppliers->isEmpty())<div class="alert alert-error">Add an active supplier first. <a href="{{ route('admin.suppliers') }}">Supplier Management</a></div>@endif
@if($products->isEmpty())<div class="alert alert-error">No products available for purchase.</div>@endif
<form method="POST" action="{{ route('admin.purchases.store') }}" id="purchase-form">@csrf<input type="hidden" name="request_token" value="{{ old('request_token', $requestToken) }}">
<section class="panel" style="margin-bottom:16px"><div class="formgrid"><label>Supplier *<select name="supplier_id" class="form-input" required><option value="">— Choose Supplier —</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label><label>Purchase Date *<input type="datetime-local" name="purchased_at" class="form-input" value="{{ old('purchased_at', now()->format('Y-m-d\TH:i')) }}" required></label><label class="full">Note<textarea name="notes" class="form-input" rows="2">{{ old('notes') }}</textarea></label></div></section>
<section class="panel"><div class="panelhead"><h3>Purchased Products</h3><button type="button" class="btn secondary" id="add-purchase-line"><i class="fa-solid fa-plus"></i> Add Product</button></div><div class="tablewrap"><table class="table"><thead><tr><th>Product</th><th>Variant</th><th>Quantity</th><th>Unit Buying Price (৳)</th><th></th></tr></thead><tbody id="purchase-lines"></tbody></table></div><div class="d-flex justify-content-end mt-3"><button class="btn" type="submit" @disabled($suppliers->isEmpty() || $products->isEmpty())>Save Purchase & Add to Stock</button></div></section>
</form>
<script>
(() => {
    const products = @js($products->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'variants' => $product->variants->map(fn ($variant) => ['id' => $variant->id, 'name' => $variant->name, 'sku' => $variant->sku, 'active' => $variant->is_active])->values()])->values());
    const oldLines = @js(old('items', [['product_id' => '', 'variant_id' => '', 'quantity' => 1, 'unit_cost' => '']]));
    const body = document.getElementById('purchase-lines');
    let rowIndex = 0;
    const addLine = (data = {}) => {
        const row = document.createElement('tr');
        const productCell = document.createElement('td');
        const productSelect = document.createElement('select');
        productSelect.className = 'form-input'; productSelect.required = true; productSelect.name = `items[${rowIndex}][product_id]`;
        productSelect.add(new Option('— Choose Product —', ''));
        products.forEach(product => productSelect.add(new Option(`${product.name} · ${product.sku}`, product.id)));
        productCell.append(productSelect);
        const variantCell = document.createElement('td');
        const variantSelect = document.createElement('select');
        variantSelect.className = 'form-input'; variantSelect.name = `items[${rowIndex}][variant_id]`;
        const fillVariants = () => {
            variantSelect.replaceChildren(new Option('— Main Product Stock —', ''));
            const product = products.find(item => String(item.id) === productSelect.value);
            if (product?.variants.length) {
                variantSelect.replaceChildren(new Option('— Choose Variant —', ''));
                product.variants.forEach(variant => variantSelect.add(new Option(`${variant.name} · ${variant.sku}${variant.active ? '' : ' (Inactive)'}`, variant.id)));
                variantSelect.required = true;
            } else variantSelect.required = false;
        };
        productSelect.addEventListener('change', fillVariants);
        variantCell.append(variantSelect);
        const qtyCell = document.createElement('td');
        const qty = document.createElement('input'); qty.type = 'number'; qty.min = '1'; qty.max = '2147483647'; qty.step = '1'; qty.required = true; qty.value = data.quantity || 1; qty.className = 'form-input'; qty.name = `items[${rowIndex}][quantity]`; qtyCell.append(qty);
        const costCell = document.createElement('td');
        const cost = document.createElement('input'); cost.type = 'number'; cost.min = '0'; cost.max = '9999999999.99'; cost.step = '0.01'; cost.required = true; cost.value = data.unit_cost || ''; cost.className = 'form-input'; cost.name = `items[${rowIndex}][unit_cost]`; costCell.append(cost);
        const actionCell = document.createElement('td');
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn secondary small'; remove.textContent = 'Remove'; remove.addEventListener('click', () => row.remove()); actionCell.append(remove);
        row.append(productCell, variantCell, qtyCell, costCell, actionCell); body.append(row);
        productSelect.value = data.product_id || ''; fillVariants(); variantSelect.value = data.variant_id || '';
        rowIndex++;
    };
    oldLines.forEach(addLine);
    document.getElementById('add-purchase-line').addEventListener('click', () => addLine());
    document.getElementById('purchase-form').addEventListener('submit', event => {
        if (!body.children.length) { event.preventDefault(); window.alert('Add at least one product.'); }
    });
})();
</script>
@endsection
