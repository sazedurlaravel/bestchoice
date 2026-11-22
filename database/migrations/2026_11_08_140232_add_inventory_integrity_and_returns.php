<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('size', 40)->nullable();
            $table->string('color', 80)->nullable();
            $table->decimal('regular_price', 12, 2)->nullable();
            $table->string('combination_key', 64)->nullable();
            $table->unique(['product_id', 'combination_key']);
        });
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->string('operation_key', 100)->nullable()->unique();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('stock_deducted_quantity')->default(0);
            $table->unsignedInteger('stock_restored_quantity')->default(0);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('requested');
            $table->text('reason');
            $table->text('disposition_note')->nullable();
            $table->unsignedInteger('restocked_quantity')->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('operation_key')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('order_status_histories');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['cancellation_reason', 'cancelled_at']));
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('variant_id');
            $table->dropColumn(['variant_name', 'stock_deducted_quantity', 'stock_restored_quantity']);
        });
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique(['operation_key']);
            $table->dropColumn('operation_key');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique(['product_id', 'combination_key']);
            $table->dropColumn(['size', 'color', 'regular_price', 'combination_key']);
        });
    }
};
