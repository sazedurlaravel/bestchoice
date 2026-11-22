<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('wishlist_product_ids')->nullable();
        });
        Schema::create('order_cancellation_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('requested')->index();
            $table->timestamps();
            $table->unique(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cancellation_requests');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('wishlist_product_ids'));
    }
};
