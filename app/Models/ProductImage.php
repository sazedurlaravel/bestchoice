<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'image_path', 'alt_text', 'sort_order'];

    public function getImagePathAttribute(?string $value): ?string
    {
        $host = $value ? parse_url($value, PHP_URL_HOST) : null;

        return in_array($host, ['localhost', '127.0.0.1'], true)
            ? (parse_url($value, PHP_URL_PATH) ?: $value)
            : $value;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
