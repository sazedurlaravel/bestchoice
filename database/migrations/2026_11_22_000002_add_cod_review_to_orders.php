<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('review_status', 30)->nullable()->after('status'); // ready|needs_confirmation|needs_correction
            $table->json('review_reasons')->nullable()->after('review_status');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete()->after('review_reasons');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            $table->text('confirmation_note')->nullable()->after('confirmed_at');
        });

        Schema::create('order_refusals', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_phone', 30)->index();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refusals');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['review_status', 'review_reasons', 'confirmed_by', 'confirmed_at', 'confirmation_note']);
        });
    }
};
