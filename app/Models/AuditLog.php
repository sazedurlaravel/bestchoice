<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'route_name', 'method', 'url', 'ip_address',
        'user_agent', 'subject_type', 'subject_id', 'metadata', 'status_code',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'status_code' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
