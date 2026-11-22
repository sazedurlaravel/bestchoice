<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'courier', 'config_revision', 'invoice', 'consignment_id', 'tracking_code',
        'booking_state', 'provider_status', 'local_status', 'cod_amount', 'booked_at',
        'last_synced_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return ['cod_amount' => 'decimal:2', 'booked_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    public function isActive(): bool
    {
        return in_array($this->booking_state, ['pending', 'submitting', 'booked', 'outcome_unknown'], true);
    }
}
