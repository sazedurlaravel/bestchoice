<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'customer_name', 'customer_phone', 'customer_additional_phone', 'customer_address',
        'customer_district', 'delivery_zone', 'subtotal', 'shipping_charge', 'discount_amount', 'coupon_code', 'total',
        'status', 'review_status', 'review_reasons', 'confirmed_by', 'confirmed_at', 'confirmation_note', 'payment_method', 'note', 'admin_note', 'assigned_to', 'user_id', 'checkout_token', 'cancellation_reason', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_charge' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'review_reasons' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(OrderCancellationRequest::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public static function generateOrderNumber(): string
    {
        return 'BC-'.strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'অপেক্ষমাণ',
            'processing' => 'প্রক্রিয়াকরণ',
            'shipped' => 'পাঠানো হয়েছে',
            'delivered' => 'ডেলিভারি হয়েছে',
            'cancelled' => 'বাতিল',
            default => $this->status,
        };
    }
}
