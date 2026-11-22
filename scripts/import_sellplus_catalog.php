<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = 'https://www.sellplusbd.com';

$fetch = static function (string $url): string {
    $response = Http::retry(2, 500)->timeout(30)->get($url);
    $response->throw();

    return $response->body();
};

$meta = static function (string $html, string $key): ?string {
    $patterns = [
        '/<meta[^>]+(?:name|property)="'.preg_quote($key, '/').'"[^>]+content="([^"]*)"/i',
        '/<meta[^>]+content="([^"]*)"[^>]+(?:name|property)="'.preg_quote($key, '/').'"/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $match)) {
            return trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    return null;
};

$absolute = static function (string $url) use ($source): string {
    $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (str_starts_with($url, '//')) {
        return 'https:'.$url;
    }
    if (str_starts_with($url, '/')) {
        return $source.$url;
    }

    return str_replace('https://sellplusbd.com', $source, $url);
};

$home = $fetch($source.'/');
preg_match_all('/href="([^"]*\/product-show\/[^"]+)"/i', $home, $linkMatches);
$links = array_values(array_filter(array_unique(array_map($absolute, $linkMatches[1] ?? [])), static fn (string $link): bool => ! preg_match('/\/product-show\/\d+$/', $link)));
if ($links === []) {
    throw new RuntimeException('No source products found.');
}

$categoryImages = [];
preg_match_all('/src="([^"]*\/posadmin\/images\/category\/([^"]+))"/i', $home, $categoryMatches);
foreach ($categoryMatches[1] ?? [] as $index => $image) {
    $filename = basename(parse_url($image, PHP_URL_PATH) ?: '');
    $categoryImages[Str::before($filename, '_')] = $absolute($image);
}

$products = [];
foreach ($links as $index => $link) {
    fwrite(STDOUT, sprintf("Reading %d/%d\r", $index + 1, count($links)));
    try {
        $html = $fetch($link);
    } catch (Throwable $exception) {
        fwrite(STDERR, PHP_EOL.'Source page skipped: '.$link.' ('.$exception->getMessage().')'.PHP_EOL);

        continue;
    }
    $name = $meta($html, 'product-name');
    $category = $meta($html, 'product-category') ?: 'Imported Products';
    $sourceId = $meta($html, 'product-id') ?: (string) ($index + 1);
    $selling = (float) ($meta($html, 'product-price') ?: 0);
    $regular = (float) ($meta($html, 'product:price:amount') ?: $selling);
    $mainImage = $meta($html, 'og:image');
    $description = $meta($html, 'description') ?: $name;

    if (! $name || $selling <= 0 || ! $mainImage) {
        continue;
    }

    preg_match_all('/<input[^>]+name="variant_([1-3])"[^>]+value="([^"]+)"/i', $html, $variantMatches);
    $variants = [];
    foreach ($variantMatches[1] ?? [] as $variantIndex => $variantGroup) {
        $value = trim(html_entity_decode($variantMatches[2][$variantIndex], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value !== '') {
            $variants[$variantGroup][] = $value;
        }
    }

    preg_match_all('/<img[^>]+src="([^"]*\/posadmin\/images\/product\/(?:large|medium)\/[^"]+)"/i', $html, $imageMatches);
    $images = array_values(array_unique(array_map($absolute, $imageMatches[1] ?? [])));
    $images = array_values(array_filter($images, static fn (string $image): bool => $image !== $absolute($mainImage)));

    $products[] = [
        'source_id' => $sourceId,
        'name' => $name,
        'category' => $category,
        'selling_price' => $selling,
        'regular_price' => $regular >= $selling ? $regular : null,
        'description' => trim(strip_tags($description)),
        'main_image' => $absolute($mainImage),
        'gallery' => $images,
        'variants' => $variants,
    ];
}
fwrite(STDOUT, PHP_EOL.'Prepared '.count($products).' products.'.PHP_EOL);

$download = static function (string $url, string $folder, string $prefix) use ($fetch): ?string {
    try {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'webp';
        $path = $folder.'/'.$prefix.'-'.substr(sha1($url), 0, 12).'.'.$extension;
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $fetch($url));
        }

        return '/storage/'.$path;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Image skipped: '.$url.' ('.$exception->getMessage().')'.PHP_EOL);

        return null;
    }
};

$resetTables = [
    'order_returns', 'order_cancellation_requests', 'order_refusals', 'order_status_histories',
    'payments', 'shipment_status_histories', 'shipments', 'order_items', 'orders',
    'inventory_transactions', 'purchase_items', 'purchases', 'product_images',
    'product_variants', 'products', 'categories', 'coupons', 'suppliers', 'audit_logs',
];

DB::statement('SET FOREIGN_KEY_CHECKS=0');
foreach ($resetTables as $table) {
    if (DB::getSchemaBuilder()->hasTable($table)) {
        DB::table($table)->truncate();
    }
}
DB::table('users')->where('role', '!=', 'admin')->delete();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

$categories = [];
foreach (array_values(array_unique(array_column($products, 'category'))) as $sort => $name) {
    $slug = Str::slug($name) ?: 'imported-products';
    $imageKey = collect(array_keys($categoryImages))->first(fn (string $key): bool => Str::contains($slug, $key) || Str::contains($key, $slug));
    $categories[$name] = \App\Models\Category::create([
        'name' => $name,
        'slug' => $slug.'-'.substr(sha1($name), 0, 6),
        'image_path' => $imageKey ? $download($categoryImages[$imageKey], 'categories', $slug) : null,
        'sort_order' => $sort,
        'is_active' => true,
    ]);
}

foreach ($products as $index => $item) {
    fwrite(STDOUT, sprintf("Importing %d/%d\r", $index + 1, count($products)));
    $mainImage = $download($item['main_image'], 'products', 'sellplus-'.$item['source_id']) ?: $item['main_image'];
    $product = \App\Models\Product::create([
        'category_id' => $categories[$item['category']]->id,
        'name' => $item['name'],
        'slug' => Str::slug($item['name']).'-'.Str::lower(Str::random(5)),
        'sku' => 'SP-'.$item['source_id'],
        'short_description' => Str::limit($item['description'], 255, ''),
        'full_description' => $item['description'],
        'selling_price' => $item['selling_price'],
        'regular_price' => $item['regular_price'],
        'stock_quantity' => 0,
        'low_stock_threshold' => 5,
        'main_image' => $mainImage,
        'is_featured' => $index < 12,
        'is_active' => true,
    ]);

    $sortOrder = 0;
    foreach ($item['gallery'] as $galleryImage) {
        $localImage = $download($galleryImage, 'products', 'sellplus-'.$item['source_id'].'-gallery');
        if ($localImage) {
            $product->images()->create(['image_path' => $localImage, 'sort_order' => $sortOrder++]);
        }
    }

    $variantValues = array_values(array_unique(array_merge(...array_values($item['variants'] ?: []))));
    if ($variantValues !== []) {
        foreach ($variantValues as $variantIndex => $value) {
            $product->variants()->create([
                'name' => $item['name'].' - '.$value,
                'sku' => 'SP-'.$item['source_id'].'-V'.($variantIndex + 1),
                'size' => $value,
                'stock_quantity' => 100,
                'is_active' => true,
            ]);
        }
    } else {
        $product->update(['stock_quantity' => 100]);
    }
}

fwrite(STDOUT, PHP_EOL.'Import complete: '.count($products).' products, '.count($categories).' categories.'.PHP_EOL);
