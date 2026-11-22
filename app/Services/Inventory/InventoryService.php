<?php

namespace App\Services\Inventory;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    private const MAX_STOCK = 2147483647;

    public function __construct(private DatabaseManager $database) {}

    public function adjust(Product $product, int $quantityChange, string $type, ?string $reason = null, ?int $userId = null, ?ProductVariant $variant = null, ?string $operationKey = null, ?string $referenceType = null, ?int $referenceId = null): InventoryTransaction
    {
        if ($quantityChange === 0) {
            throw ValidationException::withMessages(['quantity' => 'স্টক পরিবর্তনের পরিমাণ শূন্য হতে পারবে না।']);
        }

        $this->validateDelta($quantityChange);

        if (! in_array($type, ['stock_in', 'stock_out', 'adjustment'], true)) {
            throw ValidationException::withMessages(['type' => 'স্টক পরিবর্তনের ধরন সঠিক নয়।']);
        }

        if (($type === 'stock_in' && $quantityChange < 0) || ($type === 'stock_out' && $quantityChange > 0)) {
            throw ValidationException::withMessages(['quantity' => 'স্টক পরিবর্তনের পরিমাণ নির্বাচিত ধরনের সঙ্গে মিলতে হবে।']);
        }

        return $this->database->transaction(function () use ($product, $quantityChange, $type, $reason, $userId, $variant, $operationKey, $referenceType, $referenceId): InventoryTransaction {
            [$lockedProduct, $lockedVariant] = $this->lockStock($product, $variant);

            if ($existing = $this->replay($operationKey, $lockedProduct, $lockedVariant, $quantityChange, $type)) {
                return $existing;
            }

            $this->validateStockOwner($lockedProduct, $lockedVariant);

            return $this->record($lockedProduct, $lockedVariant, $quantityChange, $type, $reason, $userId, $operationKey, $referenceType, $referenceId);
        }, 3);
    }

    public function stockIn(Product $product, int $quantity, ?string $reason = null, ?int $userId = null, ?ProductVariant $variant = null, ?string $operationKey = null, ?string $referenceType = null, ?int $referenceId = null): InventoryTransaction
    {
        $this->validatePositiveQuantity($quantity);

        return $this->adjust($product, $quantity, 'stock_in', $reason, $userId, $variant, $operationKey, $referenceType, $referenceId);
    }

    public function stockOut(Product $product, int $quantity, ?string $reason = null, ?int $userId = null, ?ProductVariant $variant = null, ?string $operationKey = null, ?string $referenceType = null, ?int $referenceId = null): InventoryTransaction
    {
        $this->validatePositiveQuantity($quantity);

        return $this->adjust($product, -$quantity, 'stock_out', $reason, $userId, $variant, $operationKey, $referenceType, $referenceId);
    }

    /**
     * Keyed requests require the original expectedStock on every call, including
     * no-ops and replays, so a no-op retry cannot overwrite later stock changes.
     */
    public function setStock(Product $product, int $quantity, ?string $reason = null, ?int $userId = null, ?ProductVariant $variant = null, ?string $operationKey = null, ?int $expectedStock = null): ?InventoryTransaction
    {
        if ($operationKey !== null && $expectedStock === null) {
            throw ValidationException::withMessages(['expected_stock' => 'অপারেশন কী ব্যবহার করলে প্রারম্ভিক স্টক দিতে হবে।']);
        }

        $this->validateStock($quantity);

        return $this->database->transaction(function () use ($product, $quantity, $reason, $userId, $variant, $operationKey, $expectedStock): ?InventoryTransaction {
            [$lockedProduct, $lockedVariant] = $this->lockStock($product, $variant);

            // A replay uses the original baseline, even after later stock movements.
            if ($existing = $this->replay($operationKey, $lockedProduct, $lockedVariant, null, 'adjustment', $quantity, $expectedStock)) {
                return $existing;
            }

            $stockBefore = (int) ($lockedVariant ?? $lockedProduct)->stock_quantity;
            $this->validateStockOwner($lockedProduct, $lockedVariant, $stockBefore === 0 && $quantity === 0);

            if ($expectedStock !== null && $stockBefore !== $expectedStock) {
                throw ValidationException::withMessages(['stock_quantity' => 'স্টক পরিবর্তিত হয়েছে। বর্তমান স্টক দেখে আবার চেষ্টা করুন।']);
            }

            if ($stockBefore === $quantity) {
                return null;
            }

            return $this->record($lockedProduct, $lockedVariant, $quantity - $stockBefore, 'adjustment', $reason, $userId, $operationKey);
        }, 3);
    }

    /** @return array{Product, ?ProductVariant} */
    private function lockStock(Product $product, ?ProductVariant $variant): array
    {
        $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
        $lockedVariant = null;

        if ($variant !== null) {
            $lockedVariant = $lockedProduct->variants()->whereKey($variant->getKey())->lockForUpdate()->first();

            if ($lockedVariant === null) {
                throw ValidationException::withMessages(['variant_id' => 'এই ভ্যারিয়েন্টটি পণ্যের অন্তর্ভুক্ত নয়।']);
            }
        }

        return [$lockedProduct, $lockedVariant];
    }

    private function validateStockOwner(Product $product, ?ProductVariant $variant, bool $unchangedZero = false): void
    {
        if ($variant !== null && (int) $product->stock_quantity !== 0) {
            throw ValidationException::withMessages(['stock_quantity' => 'ভ্যারিয়েন্টযুক্ত পণ্যের মূল স্টক শূন্য হতে হবে।']);
        }

        if ($variant === null && ! $unchangedZero && $product->variants()->exists()) {
            throw ValidationException::withMessages(['variant_id' => 'স্টক পরিবর্তনের জন্য নির্দিষ্ট ভ্যারিয়েন্ট নির্বাচন করুন।']);
        }
    }

    private function replay(?string $operationKey, Product $product, ?ProductVariant $variant, ?int $delta, string $type, ?int $target = null, ?int $expectedStock = null): ?InventoryTransaction
    {
        if ($operationKey === null) {
            return null;
        }

        $existing = InventoryTransaction::query()->where('operation_key', $operationKey)->lockForUpdate()->first();

        if ($existing === null) {
            return null;
        }

        $delta ??= $target - ($expectedStock ?? (int) $existing->stock_before);

        if ((int) $existing->product_id !== (int) $product->id
            || ($existing->variant_id === null ? null : (int) $existing->variant_id) !== ($variant === null ? null : (int) $variant->id)
            || (int) $existing->quantity_change !== $delta
            || $existing->type !== $type
            || ($target !== null && (int) $existing->stock_after !== $target)) {
            throw ValidationException::withMessages(['operation_key' => 'এই অপারেশন কী অন্য স্টক পরিবর্তনে ব্যবহৃত হয়েছে।']);
        }

        return $existing;
    }

    private function record(Product $product, ?ProductVariant $variant, int $delta, string $type, ?string $reason, ?int $userId, ?string $operationKey, ?string $referenceType = null, ?int $referenceId = null): InventoryTransaction
    {
        $this->validateDelta($delta);
        $owner = $variant ?? $product;
        $stockBefore = (int) $owner->stock_quantity;
        $this->validateStock($stockBefore);
        $stockAfter = $stockBefore + $delta;
        $this->validateStock($stockAfter);
        $owner->update(['stock_quantity' => $stockAfter]);

        $transaction = new InventoryTransaction;
        $transaction->forceFill([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'type' => $type,
            'quantity_change' => $delta,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $reason,
            'user_id' => $userId,
            'operation_key' => $operationKey,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ])->save();

        return $transaction;
    }

    private function validatePositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0 || $quantity > self::MAX_STOCK) {
            throw ValidationException::withMessages(['quantity' => 'স্টকের পরিমাণ ১ থেকে ২১৪৭৪৮৩৬৪৭ এর মধ্যে হতে হবে।']);
        }
    }

    private function validateDelta(int $delta): void
    {
        if ($delta < -2147483648 || $delta > self::MAX_STOCK) {
            throw ValidationException::withMessages(['quantity' => 'স্টক পরিবর্তনের পরিমাণ অনুমোদিত সীমার বাইরে।']);
        }
    }

    private function validateStock(int $quantity): void
    {
        if ($quantity < 0 || $quantity > self::MAX_STOCK) {
            throw ValidationException::withMessages(['quantity' => 'স্টক ০ থেকে ২১৪৭৪৮৩৬৪৭ এর মধ্যে হতে হবে।']);
        }
    }

    public function lowStockProducts(): Collection
    {
        return Product::query()->active()->whereDoesntHave('variants')->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->get();
    }

    public function lowStockVariants(): Collection
    {
        return ProductVariant::query()
            ->with('product')
            ->where('is_active', true)
            ->whereHas('product', function (Builder $query): void {
                $query->active()->whereColumn('product_variants.stock_quantity', '<=', 'products.low_stock_threshold');
            })
            ->get();
    }
}
