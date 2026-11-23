<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'slug', 'sku', 'short_description', 'full_description', 'regular_price', 'selling_price', 'buying_price', 'stock_quantity', 'low_stock_threshold', 'main_image', 'is_featured', 'is_active'];

    protected static function booted(): void
    {
        static::deleting(function (self $product): void {
            if ($product->inventoryTransactions()->exists() || $product->variants()->exists() || OrderItem::where('product_id', $product->id)->exists()) {
                throw ValidationException::withMessages(['product' => 'স্টক বা অর্ডার ইতিহাস আছে। পণ্যটি মুছে না ফেলে নিষ্ক্রিয় করুন।']);
            }
        });
        static::saving(function (self $product): void {
            if ((float) $product->selling_price < 0 || ($product->buying_price !== null && (float) $product->buying_price < 0) || ($product->regular_price !== null && (float) $product->regular_price < 0)) {
                throw new InvalidArgumentException('Product prices cannot be negative.');
            }
            if ($product->regular_price !== null && (float) $product->regular_price < (float) $product->selling_price) {
                throw new InvalidArgumentException('Regular price cannot be lower than selling price.');
            }
        });
    }

    protected function casts(): array
    {
        return ['regular_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'buying_price' => 'decimal:2', 'is_featured' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->regular_price !== null && (float) $this->regular_price > (float) $this->selling_price;
    }

    public function getDisplayImageAttribute(): string
    {
        $image = $this->main_image;
        $host = $image ? parse_url($image, PHP_URL_HOST) : null;

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $image = parse_url($image, PHP_URL_PATH) ?: $image;
        }

        return $image ?: 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&auto=format&fit=crop';
    }

    public function getFormattedSellingPriceAttribute(): string
    {
        return '৳'.number_format((float) $this->selling_price, 0, '.', ',');
    }

    public function getFormattedRegularPriceAttribute(): ?string
    {
        return $this->regular_price === null ? null : '৳'.number_format((float) $this->regular_price, 0, '.', ',');
    }
}
