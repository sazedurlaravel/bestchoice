<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $query = Product::with('category')->latest();

        if ($search = $request->input('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $products = $query->paginate(20)->withQueryString();
        $categories = Category::active()->orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $categories = Category::active()->with('parent')->orderBy('name')->get();

        return view('admin.products.create', compact('categories'));
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['name', 'sku', 'category_id', 'short_description', 'full_description', 'regular_price', 'selling_price', 'stock_quantity', 'low_stock_threshold', 'main_image', 'is_featured', 'is_active']);
            Product::query()->with('category')->orderBy('id')->chunk(500, function ($products) use ($output): void {
                foreach ($products as $product) {
                    fputcsv($output, [$product->name, $product->sku, $product->category_id, $product->short_description, $product->full_description, $product->regular_price, $product->selling_price, $product->stock_quantity, $product->low_stock_threshold, $product->main_image, (int) $product->is_featured, (int) $product->is_active]);
                }
            });
            fclose($output);
        }, 'products-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        $expected = ['name', 'sku', 'category_id', 'short_description', 'full_description', 'regular_price', 'selling_price', 'stock_quantity', 'low_stock_threshold', 'main_image', 'is_featured', 'is_active'];
        if (! is_array($headers)) {
            fclose($handle);

            return back()->withErrors(['file' => 'CSV ফাইলটি খালি বা সঠিক ফরম্যাটে নেই।']);
        }
        $headers[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', $headers[0]);
        if ($headers !== $expected) {
            fclose($handle);

            return back()->withErrors(['file' => 'CSV কলামগুলো এক্সপোর্ট করা টেমপ্লেটের সঙ্গে মিলতে হবে।']);
        }

        $created = 0;
        $errors = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }
            if (count($values) !== count($expected)) {
                $errors[] = "সারি {$line}: কলামের সংখ্যা সঠিক নয়।";

                continue;
            }
            $row = array_combine($expected, $values);
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'], 'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
                'category_id' => ['required', 'integer', 'exists:categories,id'], 'short_description' => ['nullable', 'string', 'max:255'],
                'full_description' => ['nullable', 'string'], 'regular_price' => ['nullable', 'numeric', 'min:0', 'gte:selling_price'],
                'selling_price' => ['required', 'numeric', 'min:0'], 'stock_quantity' => ['required', 'integer', 'min:0'],
                'low_stock_threshold' => ['required', 'integer', 'min:0'], 'main_image' => ['nullable', 'url', 'max:255'],
                'is_featured' => ['required', 'in:0,1'], 'is_active' => ['required', 'in:0,1'],
            ]);
            if ($validator->fails()) {
                $errors[] = "সারি {$line}: ".$validator->errors()->first();

                continue;
            }
            $data = $validator->validated();
            $stock = (int) $data['stock_quantity'];
            unset($data['stock_quantity']);
            $data['slug'] = Str::substr(Str::slug($data['name']), 0, 249).'-'.Str::random(5);
            $data['is_featured'] = (bool) $data['is_featured'];
            $data['is_active'] = (bool) $data['is_active'];
            try {
                DB::transaction(function () use ($data, $stock, $request): void {
                    $product = Product::create([...$data, 'stock_quantity' => 0]);
                    $this->inventory->setStock($product, $stock, 'CSV পণ্য আমদানি', $request->user()->id, null, (string) Str::uuid(), 0);
                }, 3);
                $created++;
            } catch (\Throwable $exception) {
                report($exception);
                $errors[] = "সারি {$line}: পণ্যটি সংরক্ষণ করা যায়নি।";
            }
        }
        fclose($handle);
        $request->session()->flash('import_errors', array_slice($errors, 0, 20));

        return back()->with('success', "{$created}টি পণ্য আমদানি হয়েছে.".($errors ? ' কিছু সারিতে সমস্যা হয়েছে।' : ''));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'full_description' => ['nullable', 'string'],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'gte:selling_price'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'buying_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'stock_operation_key' => ['required', 'uuid'],
            'variants' => ['nullable', 'array', 'max:100'],
            'has_variants' => ['nullable', 'boolean'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['required', 'string', 'max:255', 'distinct', 'unique:product_variants,sku'],
            'variants.*.size' => ['nullable', 'required_without:variants.*.color', 'string', 'max:255'],
            'variants.*.color' => ['nullable', 'required_without:variants.*.size', 'string', 'max:255'],
            'variants.*.price_override' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.regular_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'main_image' => ['nullable', 'url', 'max:255'],
            'main_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'gallery_images' => ['nullable', 'array', 'max:20'],
            'gallery_images.*.url' => ['nullable', 'url', 'max:255'],
            'gallery_images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'gallery_uploads' => ['nullable', 'array', 'max:20'],
            'gallery_uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $data['full_description'] = $this->sanitizeProductDescription($data['full_description'] ?? null);
        $data['slug'] = Str::substr(Str::slug($data['name']), 0, 249).'-'.Str::random(5);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $mainImageUpload = $request->file('main_image_upload');
        unset($data['main_image_upload']);
        if ($mainImageUpload) {
            $data['main_image'] = Storage::disk('public')->url($mainImageUpload->store('products', 'public'));
        }
        $galleryImages = array_values(array_filter($data['gallery_images'] ?? [], fn (array $image): bool => filled($image['url'] ?? null)));
        $galleryUploads = $request->file('gallery_uploads', []);
        unset($data['gallery_images']);
        $variants = $data['variants'] ?? [];
        unset($data['variants']);
        unset($data['has_variants']);
        $variants = $this->expandVariantOptions($variants);
        foreach ($variants as $index => $variant) {
            $selling = (float) ($variant['price_override'] ?? $data['selling_price']);
            $regular = $variant['regular_price'] ?? $data['regular_price'] ?? null;
            if ($regular !== null && (float) $regular < $selling) {
                throw ValidationException::withMessages(["variants.{$index}.regular_price" => 'ভ্যারিয়েন্টের নিয়মিত মূল্য বিক্রয় মূল্যের চেয়ে কম হতে পারবে না।']);
            }
            $key = ProductVariant::combinationKeyFor($variant['size'] ?? null, $variant['color'] ?? null);
            foreach (array_slice($variants, 0, $index) as $previous) {
                if (ProductVariant::combinationKeyFor($previous['size'] ?? null, $previous['color'] ?? null) === $key) {
                    throw ValidationException::withMessages(["variants.{$index}.size" => 'একই সাইজ ও রঙের ভ্যারিয়েন্ট একাধিকবার দেওয়া হয়েছে।']);
                }
            }
        }

        $stock = (int) $data['stock_quantity'];
        if ($request->boolean('has_variants') || $variants !== []) {
            $stock = 0;
        }
        $operationKey = $data['stock_operation_key'];
        unset($data['stock_operation_key']);
        $data['stock_quantity'] = 0;

        DB::transaction(function () use ($data, $stock, $operationKey, $request, $variants, $galleryImages, $galleryUploads): void {
            $product = Product::create($data);
            $sortOrder = 0;
            foreach ($galleryImages as $index => $image) {
                $product->images()->create(['image_path' => $image['url'], 'alt_text' => $image['alt_text'] ?? null, 'sort_order' => $sortOrder++]);
            }
            foreach ($galleryUploads as $upload) {
                $product->images()->create(['image_path' => Storage::disk('public')->url($upload->store('products', 'public')), 'alt_text' => null, 'sort_order' => $sortOrder++]);
            }
            $this->inventory->setStock($product, $stock, 'পণ্যের প্রারম্ভিক স্টক', $request->user()->id, null, $operationKey, 0);
            foreach ($variants as $variantData) {
                $variantStock = (int) $variantData['stock_quantity'];
                unset($variantData['stock_quantity']);
                $variant = $product->variants()->create([...$variantData, 'is_active' => true]);
                $this->inventory->setStock($product, $variantStock, 'ভ্যারিয়েন্টের প্রারম্ভিক স্টক', $request->user()->id, $variant, (string) Str::uuid(), 0);
            }
        }, 3);

        return redirect()->route('admin.products')->with('success', 'পণ্য সফলভাবে যোগ করা হয়েছে।');
    }

    public function edit(Product $product): View
    {
        $product->load(['variants', 'images']);
        $categories = Category::active()->with('parent')->orderBy('name')->get();

        return view('admin.products.create', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'short_description' => ['nullable', 'string', 'max:255'],
            'full_description' => ['nullable', 'string'],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'gte:selling_price'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'buying_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'expected_stock' => ['required', 'integer', 'min:0'],
            'stock_operation_key' => ['required', 'uuid'],
            'has_variants' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['required', 'string', 'max:255', 'distinct'],
            'variants.*.size' => ['nullable', 'required_without:variants.*.color', 'string', 'max:255'],
            'variants.*.color' => ['nullable', 'required_without:variants.*.size', 'string', 'max:255'],
            'variants.*.price_override' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.regular_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.expected_stock' => ['required', 'integer', 'min:0'],
            'variants.*.stock_operation_key' => ['required', 'uuid'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'main_image' => ['nullable', 'url', 'max:255'],
            'main_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'remove_main_image' => ['boolean'],
            'gallery_images' => ['nullable', 'array', 'max:20'],
            'gallery_images.*.url' => ['nullable', 'url', 'max:255'],
            'gallery_images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'gallery_uploads' => ['nullable', 'array', 'max:20'],
            'gallery_uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'remove_gallery_image_ids' => ['nullable', 'array', 'max:20'],
            'remove_gallery_image_ids.*' => ['integer', 'distinct'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $data['full_description'] = $this->sanitizeProductDescription($data['full_description'] ?? null);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $mainImageUpload = $request->file('main_image_upload');
        $removeMainImage = $request->boolean('remove_main_image');
        unset($data['main_image_upload']);
        unset($data['remove_main_image']);
        if ($mainImageUpload) {
            $data['main_image'] = Storage::disk('public')->url($mainImageUpload->store('products', 'public'));
        } elseif ($removeMainImage && blank($data['main_image'] ?? null)) {
            $data['main_image'] = null;
        }
        $galleryImages = array_values(array_filter($data['gallery_images'] ?? [], fn (array $image): bool => filled($image['url'] ?? null)));
        $galleryUploads = $request->file('gallery_uploads', []);
        $removeGalleryImageIds = $data['remove_gallery_image_ids'] ?? [];
        unset($data['remove_gallery_image_ids']);
        unset($data['gallery_images']);

        $hasVariants = $request->boolean('has_variants');
        $variants = $this->expandVariantOptions($data['variants'] ?? []);
        unset($data['variants'], $data['has_variants']);

        foreach ($variants as $index => $variant) {
            $selling = (float) ($variant['price_override'] ?? $data['selling_price']);
            $regular = $variant['regular_price'] ?? $data['regular_price'] ?? null;
            if ($regular !== null && (float) $regular < $selling) {
                throw ValidationException::withMessages(["variants.{$index}.regular_price" => 'ভ্যারিয়েন্টের নিয়মিত মূল্য বিক্রয় মূল্যের চেয়ে কম হতে পারবে না।']);
            }

            $skuQuery = ProductVariant::query()->where('sku', $variant['sku']);
            if (filled($variant['id'] ?? null)) {
                $skuQuery->whereKeyNot($variant['id']);
            }
            if ($skuQuery->exists()) {
                throw ValidationException::withMessages(["variants.{$index}.sku" => 'এই SKU ইতিমধ্যে ব্যবহৃত হয়েছে।']);
            }

            $key = ProductVariant::combinationKeyFor($variant['size'] ?? null, $variant['color'] ?? null);
            foreach (array_slice($variants, 0, $index) as $previous) {
                if (ProductVariant::combinationKeyFor($previous['size'] ?? null, $previous['color'] ?? null) === $key) {
                    throw ValidationException::withMessages(["variants.{$index}.size" => 'একই সাইজ ও রঙের ভ্যারিয়েন্ট একাধিকবার দেওয়া হয়েছে।']);
                }
            }
        }

        $stock = (int) $data['stock_quantity'];
        $expectedStock = (int) $data['expected_stock'];
        $operationKey = $data['stock_operation_key'];
        unset($data['stock_quantity'], $data['expected_stock'], $data['stock_operation_key']);

        if ($hasVariants || $variants !== []) {
            $stock = 0;
        }

        DB::transaction(function () use ($product, $data, $stock, $expectedStock, $operationKey, $request, $galleryImages, $galleryUploads, $removeGalleryImageIds, $variants): void {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $lockedProduct->fill($data);
            foreach ($lockedProduct->variants()->orderBy('id')->lockForUpdate()->get() as $variant) {
                $selling = (float) ($variant->price_override ?? $lockedProduct->selling_price);
                $regular = $variant->regular_price ?? $lockedProduct->regular_price;
                if ($regular !== null && (float) $regular < $selling) {
                    throw ValidationException::withMessages(['regular_price' => 'এই মূল্য পরিবর্তনে একটি ভ্যারিয়েন্টের নিয়মিত মূল্য বিক্রয় মূল্যের চেয়ে কম হয়ে যাবে। আগে ভ্যারিয়েন্টের মূল্য সমন্বয় করুন।']);
                }
            }
            $lockedProduct->save();
            if ($removeGalleryImageIds !== []) {
                $lockedProduct->images()->whereKey($removeGalleryImageIds)->delete();
            }
            $nextSortOrder = (int) $lockedProduct->images()->max('sort_order') + 1;
            $offset = 0;
            foreach ($galleryImages as $index => $image) {
                $lockedProduct->images()->create(['image_path' => $image['url'], 'alt_text' => $image['alt_text'] ?? null, 'sort_order' => $nextSortOrder + $offset++]);
            }
            foreach ($galleryUploads as $upload) {
                $lockedProduct->images()->create(['image_path' => Storage::disk('public')->url($upload->store('products', 'public')), 'alt_text' => null, 'sort_order' => $nextSortOrder + $offset++]);
            }
            $this->inventory->setStock($lockedProduct, $stock, 'পণ্য ফর্মে স্টক সমন্বয়', $request->user()->id, null, $operationKey, $expectedStock);

            foreach ($variants as $variantData) {
                $variantId = $variantData['id'] ?? null;
                $record = $variantId
                    ? $lockedProduct->variants()->whereKey($variantId)->lockForUpdate()->firstOrFail()
                    : new ProductVariant(['product_id' => $lockedProduct->id, 'stock_quantity' => 0, 'is_active' => true]);
                $attributes = $variantData;
                unset($attributes['id'], $attributes['stock_quantity'], $attributes['expected_stock'], $attributes['stock_operation_key']);
                if (! array_key_exists('is_active', $attributes)) {
                    $attributes['is_active'] = $record->exists ? $record->is_active : true;
                }
                $record->fill($attributes);
                $record->setRelation('product', $lockedProduct);
                $record->save();
                $this->inventory->setStock(
                    $lockedProduct,
                    (int) $variantData['stock_quantity'],
                    'পণ্য ফর্মে ভ্যারিয়েন্ট স্টক সমন্বয়',
                    $request->user()->id,
                    $record,
                    $variantData['stock_operation_key'],
                    (int) $variantData['expected_stock'],
                );
            }
        }, 3);

        return redirect()->route('admin.products')->with('success', 'পণ্য সফলভাবে আপডেট করা হয়েছে।');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteProduct($product);

        return redirect()->route('admin.products')->with('success', 'পণ্য এবং এর সংশ্লিষ্ট রেকর্ড স্থায়ীভাবে মুছে ফেলা হয়েছে।');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $deleted = 0;
        $failed = [];

        foreach ($data['product_ids'] as $productId) {
            $product = Product::query()->find($productId);
            if (! $product) {
                continue;
            }

            try {
                $this->deleteProduct($product);
                $deleted++;
            } catch (ValidationException $exception) {
                $failed[] = $product->name.': '.(collect($exception->errors())->flatten()->first() ?? 'পণ্যটি মুছে ফেলা যায়নি।');
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = $product->name.': পণ্যটি মুছে ফেলা যায়নি।';
            }
        }

        $message = $deleted.'টি পণ্য মুছে ফেলা হয়েছে।';
        if ($failed) {
            $message .= ' বাদ গেছে: '.implode(' | ', $failed);
        }

        return redirect()->route('admin.products')->with($deleted > 0 ? 'success' : 'error', $message);
    }

    private function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $orderItemIds = OrderItem::query()
                ->where('product_id', $lockedProduct->id)
                ->pluck('id');

            if ($orderItemIds->isNotEmpty()) {
                DB::table('order_returns')->whereIn('order_item_id', $orderItemIds)->delete();
                DB::table('order_items')->whereIn('id', $orderItemIds)->delete();
            }

            DB::table('purchase_items')->where('product_id', $lockedProduct->id)->delete();
            DB::table('inventory_transactions')->where('product_id', $lockedProduct->id)->delete();
            DB::table('product_images')->where('product_id', $lockedProduct->id)->delete();
            DB::table('product_variants')->where('product_id', $lockedProduct->id)->delete();
            DB::table('products')->where('id', $lockedProduct->id)->delete();
        }, 3);
    }

    private function sanitizeProductDescription(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }

        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><div>';
        $withoutExecutableBlocks = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $description) ?? '';
        $clean = strip_tags($withoutExecutableBlocks, $allowedTags);

        return preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li|div)\b[^>]*>/i', '<$1>', $clean) ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    private function expandVariantOptions(array $variants): array
    {
        $expanded = [];
        $usedSkus = [];

        foreach ($variants as $index => $variant) {
            $sizes = $this->splitVariantOptions($variant['size'] ?? null);
            $colors = $this->splitVariantOptions($variant['color'] ?? null);

            foreach ($sizes as $size) {
                if (mb_strlen($size) > 40) {
                    throw ValidationException::withMessages(["variants.{$index}.size" => 'প্রতিটি সাইজ সর্বোচ্চ ৪০ অক্ষরের হতে পারবে।']);
                }
            }
            foreach ($colors as $color) {
                if (mb_strlen($color) > 80) {
                    throw ValidationException::withMessages(["variants.{$index}.color" => 'প্রতিটি রঙ সর্বোচ্চ ৮০ অক্ষরের হতে পারবে।']);
                }
            }

            if ($sizes === [] && $colors === []) {
                throw ValidationException::withMessages(["variants.{$index}.size" => 'কমপক্ষে একটি সাইজ অথবা রঙ দিতে হবে।']);
            }

            $sizes = $sizes ?: [null];
            $colors = $colors ?: [null];
            $combinations = [];
            foreach ($sizes as $size) {
                foreach ($colors as $color) {
                    $combinations[] = [$size, $color];
                }
            }

            foreach ($combinations as $combinationIndex => [$size, $color]) {
                $expandedVariant = $variant;
                $expandedVariant['size'] = $size;
                $expandedVariant['color'] = $color;
                if (count($combinations) > 1) {
                    $label = implode(' / ', array_filter([$size, $color], filled(...)));
                    $expandedVariant['name'] = trim($variant['name']).' - '.$label;
                    $expandedVariant['sku'] = $this->variantSkuWithSuffix($variant['sku'], [$size, $color], $combinationIndex);
                }

                $skuKey = Str::lower($expandedVariant['sku']);
                if (isset($usedSkus[$skuKey])) {
                    throw ValidationException::withMessages(["variants.{$index}.sku" => 'একাধিক ভ্যারিয়েন্টের SKU একই হতে পারবে না।']);
                }
                $usedSkus[$skuKey] = true;
                $expanded[] = $expandedVariant;
            }
        }

        if (count($expanded) > 100) {
            throw ValidationException::withMessages(['variants' => 'একবারে সর্বোচ্চ ১০০টি ভ্যারিয়েন্ট তৈরি করা যাবে।']);
        }

        return $expanded;
    }

    /** @return array<int, string> */
    private function splitVariantOptions(?string $options): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $options)), filled(...))));
    }

    /** @param array<int, ?string> $options */
    private function variantSkuWithSuffix(string $sku, array $options, int $index): string
    {
        $suffix = implode('-', array_filter(array_map(fn (?string $option): string => Str::slug((string) $option), $options))) ?: 'option';
        $suffix .= '-'.($index + 1);
        $baseLength = max(1, 255 - strlen($suffix) - 1);

        return Str::substr($sku, 0, $baseLength).'-'.$suffix;
    }
}
