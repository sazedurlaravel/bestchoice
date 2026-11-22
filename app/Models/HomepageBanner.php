<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomepageBanner extends Model
{
    protected $fillable = [
        'slug', 'kicker', 'headline', 'highlight', 'description', 'button_text', 'button_url',
        'image_url', 'background_color', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
