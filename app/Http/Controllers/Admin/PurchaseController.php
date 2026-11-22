<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    private const MAX_PURCHASE_CENTS = 99999999999999;

    public function index(): View
    {
        return view('admin.purchases.index', [
            'purchases' => Purchase::query()->with('supplier')->latest('purchased_at')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.purchases.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->with(['variants' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            'requestToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'request_token' => ['required', 'uuid'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);

        if ($purchase = Purchase::query()->where('request_token', $data['request_token'])->first()) {
            return redirect()->route('admin.purchases.show', $purchase)->with('success', 'এই ক্রয়টি আগেই সংরক্ষিত হয়েছে।');
        }

        $supplier = Supplier::query()->where('is_active', true)->find($data['supplier_id']);
        if (! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => 'সক্রিয় সরবরাহকারী নির্বাচন করুন।']);
        }

        $resolved = [];
        foreach ($data['items'] as $index => $line) {
            $product = Product::query()->with('variants')->findOrFail($line['product_id']);
            $variant = null;
            if (! empty($line['variant_id'])) {
                $variant = ProductVariant::query()->where('product_id', $product->id)->find($line['variant_id']);
                if (! $variant) {
                    throw ValidationException::withMessages(["items.$index.variant_id" => 'এই পণ্যের সঠিক ভ্যারিয়েন্ট নির্বাচন করুন।']);
                }
            } elseif ($product->variants->isNotEmpty()) {
                throw ValidationException::withMessages(["items.$index.variant_id" => 'ভ্যারিয়েন্টযুক্ত পণ্যের জন্য ভ্যারিয়েন্ট নির্বাচন আবশ্যক।']);
            }
            $resolved[] = [$product, $variant, $line];
        }

        try {
            $purchase = DB::transaction(function () use ($data, $supplier, $resolved, $request, $inventory): Purchase {
                $purchaseNumber = 'PUR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
                $totalCents = 0;
                foreach ($resolved as [, , $line]) {
                    $unitCents = (int) round((float) $line['unit_cost'] * 100);
                    $quantity = (int) $line['quantity'];
                    if ($unitCents > intdiv(self::MAX_PURCHASE_CENTS, $quantity)) {
                        throw ValidationException::withMessages(['items' => 'ক্রয়ের মোট পরিমাণ অনুমোদিত সীমার বেশি।']);
                    }
                    $lineCents = $unitCents * $quantity;
                    if ($totalCents > self::MAX_PURCHASE_CENTS - $lineCents) {
                        throw ValidationException::withMessages(['items' => 'ক্রয়ের মোট পরিমাণ অনুমোদিত সীমার বেশি।']);
                    }
                    $totalCents += $lineCents;
                }

                $purchase = Purchase::create([
                    'supplier_id' => $supplier->id,
                    'received_by' => $request->user()->id,
                    'purchase_number' => $purchaseNumber,
                    'request_token' => $data['request_token'],
                    'status' => 'received',
                    'purchased_at' => $data['purchased_at'],
                    'total' => number_format($totalCents / 100, 2, '.', ''),
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($resolved as [$product, $variant, $line]) {
                    $unitCents = (int) round((float) $line['unit_cost'] * 100);
                    $item = $purchase->items()->create([
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                        'product_name' => $product->name,
                        'sku' => $variant?->sku ?? $product->sku,
                        'variant_name' => $variant?->name,
                        'quantity' => $line['quantity'],
                        'unit_cost' => number_format($unitCents / 100, 2, '.', ''),
                        'line_total' => number_format(($unitCents * (int) $line['quantity']) / 100, 2, '.', ''),
                    ]);

                    $inventory->stockIn(
                        $product,
                        (int) $line['quantity'],
                        Str::limit('Purchase '.$purchase->purchase_number.' from '.$supplier->name, 255, ''),
                        $request->user()->id,
                        $variant,
                        'purchase-item:'.$item->id.':receive',
                        'purchase_item',
                        $item->id,
                    );
                }

                return $purchase;
            }, 3);
        } catch (QueryException $exception) {
            $duplicate = Purchase::query()->where('request_token', $data['request_token'])->first();
            if ($duplicate) {
                return redirect()->route('admin.purchases.show', $duplicate)->with('success', 'এই ক্রয়টি আগেই সংরক্ষিত হয়েছে।');
            }
            throw $exception;
        }

        return redirect()->route('admin.purchases.show', $purchase)->with('success', 'ক্রয় রেকর্ড সংরক্ষিত হয়েছে এবং স্টক যোগ হয়েছে।');
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['supplier', 'receivedBy', 'items.product', 'items.variant']);

        return view('admin.purchases.show', compact('purchase'));
    }
}
