<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider', 40);
            $table->string('transaction_id', 40)->unique();
            $table->string('session_key', 80)->nullable();
            $table->string('gateway_url', 500)->nullable();
            $table->string('validation_id', 80)->nullable();
            $table->string('bank_transaction_id', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 20)->default('pending')->index();
            $table->string('payment_channel')->nullable();
            $table->string('risk_level', 10)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
