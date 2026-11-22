<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('buying_price', 12, 2)->nullable()->after('selling_price');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('buying_price', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('buying_price');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('buying_price');
        });
    }
};
