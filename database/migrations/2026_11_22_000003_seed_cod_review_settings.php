<?php

use App\Models\StoreSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'cod_review_amount_threshold' => '3000',
            'cod_review_duplicate_minutes' => '15',
            'cod_review_refusal_count' => '2',
            'cod_review_refusal_days' => '90',
            'cod_review_active_orders_max' => '2',
        ];

        foreach ($defaults as $key => $value) {
            StoreSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    public function down(): void
    {
        StoreSetting::query()->whereIn('key', [
            'cod_review_amount_threshold',
            'cod_review_duplicate_minutes',
            'cod_review_refusal_count',
            'cod_review_refusal_days',
            'cod_review_active_orders_max',
        ])->delete();
    }
};
