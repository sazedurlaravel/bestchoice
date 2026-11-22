<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierSetting extends Model
{
    protected $fillable = [
        'courier', 'enabled', 'api_key', 'secret_key', 'config_revision',
        'last_connection_status', 'last_connection_message', 'last_connection_tested_at',
    ];

    protected $hidden = ['api_key', 'secret_key'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'api_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'last_connection_tested_at' => 'datetime',
        ];
    }

    public function configured(): bool
    {
        return filled($this->api_key) && filled($this->secret_key);
    }
}
