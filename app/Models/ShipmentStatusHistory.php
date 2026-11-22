<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentStatusHistory extends Model
{
    protected $fillable = ['shipment_id', 'provider_status', 'local_status', 'source', 'observed_at'];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
