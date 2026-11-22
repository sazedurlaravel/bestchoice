<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        return $this->save($request, $product);
    }

    public function update(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        return $this->save($request, $product, $variant);
    }

    private function save(Request $request, Product $product, ?ProductVariant $variant = null): RedirectResponse
    {
        $form = $variant ? (string) $variant->id : 'new';

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'sku' => ['required', 'string', 'max:255', Rule::unique('product_variants', 'sku')->ignore($variant?->id)],
                'size' => ['nullable', 'required_without:color', 'string', 'max:255'],
                'color' => ['nullable', 'required_without:size', 'string', 'max:255'],
                'regular_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'price_override' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'stock_quantity' => ['required', 'integer', 'min:0'],
                'expected_stock' => [$variant ? 'required' : 'nullable', 'integer', 'min:0'],
                'stock_operation_key' => ['required', 'uuid'],
                'is_active' => ['required', 'boolean'],
            ]);

            $expandedVariants = $variant === null ? $this->expandVariantOptions($data) : [$data];

            try {
                DB::transaction(function () use ($request, $product, $variant, $data, $expandedVariants): void {
                    $parent = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                    if ((int) $parent->stock_quantity !== 0) {
                        throw ValidationException::withMessages(['stock_quantity' => 'ভ্যারিয়েন্ট পরিবর্তনের আগে পণ্যের মূল স্টক স্পষ্টভাবে শূন্য করুন।']);
                    }

                    if ($variant === null && ! $parent->variants()->exists()
                        && OrderItem::query()->where('product_id', $parent->id)->whereNull('variant_id')
                            ->whereColumn('quantity', '>', 'stock_restored_quantity')->exists()) {
                        throw ValidationException::withMessages(['size' => 'সাধারণ পণ্যের অর্ডারে ফেরতযোগ্য পরিমাণ আছে। নতুন ভ্যারিয়েন্ট পণ্য SKU তৈরি করুন অথবা পর্যালোচিত মাইগ্রেশন করুন।']);
                    }

                    $records = $variant !== null
                        ? [$variant]
                        : array_fill(0, count($expandedVariants), null);
                    foreach ($records as $recordIndex => $existingVariant) {
                        $variantData = $variant !== null ? $data : $expandedVariants[$recordIndex];
                        $record = $existingVariant
                            ? $parent->variants()->whereKey($existingVariant->id)->lockForUpdate()->firstOrFail()
                            : new ProductVariant(['product_id' => $parent->id, 'stock_quantity' => 0]);

                        $attributes = $variantData;
                        unset($attributes['stock_quantity'], $attributes['expected_stock'], $attributes['stock_operation_key']);
                        $record->fill($attributes);
                        $record->setRelation('product', $parent);

                        $regular = $record->effectiveRegularPrice();
                        if ($regular !== null && $regular < $record->effectiveSellingPrice()) {
                            throw ValidationException::withMessages(['regular_price' => 'কার্যকর নিয়মিত মূল্য বিক্রয় মূল্যের চেয়ে কম হতে পারবে না।']);
                        }

                        $key = ProductVariant::combinationKeyFor($record->size, $record->color);
                        if ($parent->variants()->where('combination_key', $key)->when($record->exists, fn ($query) => $query->whereKeyNot($record->id))->exists()) {
                            throw ValidationException::withMessages(['size' => 'এই সাইজ ও রঙের ভ্যারিয়েন্ট ইতিমধ্যে আছে।']);
                        }

                        $record->save();
                        $this->inventory->setStock(
                            $parent, (int) $variantData['stock_quantity'],
                            $variant ? 'ভ্যারিয়েন্ট স্টক সমন্বয়' : 'ভ্যারিয়েন্টের প্রারম্ভিক স্টক',
                            $request->user()->id, $record,
                            $variant ? $variantData['stock_operation_key'] : ($recordIndex === 0 ? $data['stock_operation_key'] : (string) Str::uuid()),
                            $variant ? (int) $variantData['expected_stock'] : 0,
                        );
                    }
                }, 3);
            } catch (UniqueConstraintViolationException $exception) {
                // A concurrent insert can win after the validation queries.
                $field = ProductVariant::query()->where('sku', $data['sku'])
                    ->when($variant, fn ($query) => $query->whereKeyNot($variant->id))->exists() ? 'sku' : 'size';
                throw ValidationException::withMessages([$field => $field === 'sku' ? 'এই SKU ইতিমধ্যে ব্যবহৃত হয়েছে।' : 'এই সাইজ ও রঙের ভ্যারিয়েন্ট ইতিমধ্যে আছে।']);
            }
        } catch (ValidationException $exception) {
            return redirect()->route('admin.products.edit', $product)
                ->withErrors($exception->errors(), 'variant_'.$form)
                ->withInput(['variant_form' => $form, 'variant_input' => $request->only([
                    'name', 'sku', 'size', 'color', 'regular_price', 'price_override', 'stock_quantity',
                    'expected_stock', 'stock_operation_key', 'is_active',
                ])]);
        }

        return redirect()->route('admin.products.edit', $product)->with('success', 'ভ্যারিয়েন্ট সফলভাবে সংরক্ষণ করা হয়েছে।');
    }

    /** @return array<int, array<string, mixed>> */
    private function expandVariantOptions(array $variant): array
    {
        $sizes = $this->splitVariantOptions($variant['size'] ?? null, 40, 'size');
        $colors = $this->splitVariantOptions($variant['color'] ?? null, 80, 'color');
        $sizes = $sizes ?: [null];
        $colors = $colors ?: [null];
        $combinations = [];

        foreach ($sizes as $size) {
            foreach ($colors as $color) {
                $combinations[] = [$size, $color];
            }
        }

        if (count($combinations) > 100) {
            throw ValidationException::withMessages(['size' => 'একবারে সর্বোচ্চ ১০০টি ভ্যারিয়েন্ট তৈরি করা যাবে।']);
        }

        return array_map(function (array $combination, int $index) use ($variant, $combinations): array {
            [$size, $color] = $combination;
            $expanded = [...$variant, 'size' => $size, 'color' => $color];
            if (count($combinations) > 1) {
                $expanded['name'] = trim($variant['name']).' - '.implode(' / ', array_filter([$size, $color], filled(...)));
                $suffix = implode('-', array_filter(array_map(fn (?string $option): string => Str::slug((string) $option), [$size, $color]))).'-'.($index + 1);
                $expanded['sku'] = Str::substr($variant['sku'], 0, max(1, 255 - strlen($suffix) - 1)).'-'.$suffix;
            }

            return $expanded;
        }, $combinations, array_keys($combinations));
    }

    /** @return array<int, string> */
    private function splitVariantOptions(?string $options, int $maxLength, string $field): array
    {
        $values = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $options)), filled(...))));
        foreach ($values as $value) {
            if (mb_strlen($value) > $maxLength) {
                throw ValidationException::withMessages([$field => "প্রতিটি {$field} সর্বোচ্চ {$maxLength} অক্ষরের হতে পারবে।"]);
            }
        }

        return $values;
    }
}
