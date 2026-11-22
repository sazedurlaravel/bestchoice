<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseItem;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $query = InventoryTransaction::with(['product', 'variant', 'user'])->latest();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $transactions = $query->paginate(30)->withQueryString();
        $purchaseItems = PurchaseItem::query()->with('purchase')
            ->whereIn('id', $transactions->getCollection()->pluck('reference_id')->all())
            ->get()->keyBy('id');
        $products = Product::with('variants')->orderBy('name')->get();
        $lowStockProducts = $this->inventory->lowStockProducts();
        $lowStockVariants = $this->inventory->lowStockVariants();

        return view('admin.inventory.index', compact('transactions', 'products', 'lowStockProducts', 'lowStockVariants', 'purchaseItems'));
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:stock_in,stock_out,adjustment'],
            'quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['nullable', 'string', 'max:255'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'operation_key' => ['required', 'uuid'],
            'expected_stock' => ['required_if:type,adjustment', 'nullable', 'integer', 'min:0'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = isset($data['variant_id']) ? ProductVariant::where('product_id', $product->id)->find($data['variant_id']) : null;
        if (isset($data['variant_id']) && ! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'সঠিক পণ্যের ভ্যারিয়েন্ট নির্বাচন করুন।']);
        }

        try {
            if ($data['type'] === 'stock_in') {
                $this->inventory->stockIn($product, $data['quantity'], $data['reason'] ?? null, $request->user()->id, $variant, $data['operation_key']);
            } elseif ($data['type'] === 'stock_out') {
                $this->inventory->stockOut($product, $data['quantity'], $data['reason'] ?? null, $request->user()->id, $variant, $data['operation_key']);
            } else {
                $this->inventory->setStock($product, $data['quantity'], $data['reason'] ?? null, $request->user()->id, $variant, $data['operation_key'], $data['expected_stock']);
            }
        } catch (ValidationException $e) {
            return redirect()->route('admin.inventory')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.inventory')->with('success', 'স্টক সফলভাবে আপডেট করা হয়েছে।');
    }
}
