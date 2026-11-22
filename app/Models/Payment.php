<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'transaction_id', 'session_key', 'gateway_url', 'validation_id',
        'bank_transaction_id', 'amount', 'currency', 'status', 'payment_channel',
        'risk_level', 'gateway_response', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'gateway_response' => 'array', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
