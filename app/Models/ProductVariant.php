<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'name', 'options', 'sku', 'size', 'color', 'regular_price', 'price_override', 'stock_quantity', 'is_active'];

    protected static function booted(): void
    {
        static::deleting(function (self $variant): void {
            if (InventoryTransaction::query()->where('variant_id', $variant->id)->exists()
                || OrderItem::query()->where('variant_id', $variant->id)->exists()) {
                throw ValidationException::withMessages(['variant' => 'ইতিহাসযুক্ত ভ্যারিয়েন্ট মুছবেন না; নিষ্ক্রিয় করুন।']);
            }
        });

        static::saving(function (self $variant): void {
            $typed = filled($variant->size) || filled($variant->color);
            $key = $typed ? self::combinationKeyFor($variant->size, $variant->color) : null;
            $originalTyped = filled($variant->getOriginal('size')) || filled($variant->getOriginal('color'));
            $originalKey = $originalTyped ? self::combinationKeyFor($variant->getOriginal('size'), $variant->getOriginal('color')) : null;

            if ($variant->exists && ($variant->isDirty('product_id') || $key !== $originalKey)
                && (InventoryTransaction::query()->where('variant_id', $variant->id)->exists()
                    || OrderItem::query()->where('variant_id', $variant->id)->exists())) {
                throw ValidationException::withMessages(['size' => 'স্টক বা অর্ডার ইতিহাসযুক্ত ভ্যারিয়েন্টের সাইজ, রঙ বা মূল পণ্য পরিবর্তন করা যাবে না। নতুন ভ্যারিয়েন্ট তৈরি করুন।']);
            }

            // Untyped legacy variants retain their existing options and null key.
            if ($typed) {
                $variant->size = filled($variant->size) ? trim($variant->size) : null;
                $variant->color = filled($variant->color) ? trim($variant->color) : null;
                $variant->combination_key = self::combinationKeyFor($variant->size, $variant->color);
                $variant->options = array_replace($variant->options ?? [], ['size' => $variant->size, 'color' => $variant->color]);
            } elseif ($variant->exists && $originalTyped && $variant->isDirty(['size', 'color'])) {
                $variant->combination_key = null;
                $variant->options = array_diff_key($variant->options ?? [], array_flip(['size', 'color']));
            }
        });
    }

    public static function combinationKeyFor(?string $size, ?string $color): string
    {
        return hash('sha256', json_encode([Str::lower(trim($size ?? '')), Str::lower(trim($color ?? ''))], JSON_THROW_ON_ERROR));
    }

    protected function casts(): array
    {
        return ['options' => 'array', 'regular_price' => 'decimal:2', 'price_override' => 'decimal:2', 'stock_quantity' => 'integer', 'is_active' => 'boolean'];
    }

    public function effectiveSellingPrice(): float
    {
        return (float) ($this->price_override ?? $this->product->selling_price);
    }

    public function effectiveRegularPrice(): ?float
    {
        $price = $this->regular_price ?? $this->product->regular_price;

        return $price === null ? null : (float) $price;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
