<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('courier', 40)->unique();
            $table->boolean('enabled')->default(false);
            $table->text('api_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->unsignedInteger('config_revision')->default(1);
            $table->string('last_connection_status', 30)->nullable();
            $table->string('last_connection_message', 500)->nullable();
            $table->timestamp('last_connection_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('courier', 40);
            $table->unsignedInteger('config_revision');
            $table->string('invoice', 100);
            $table->string('consignment_id', 100)->nullable();
            $table->string('tracking_code', 100)->nullable();
            $table->string('booking_state', 30)->default('pending');
            $table->string('provider_status', 80)->nullable();
            $table->string('local_status', 30)->nullable();
            $table->decimal('cod_amount', 10, 2)->default(0);
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['courier', 'invoice']);
            $table->unique(['courier', 'consignment_id']);
            $table->index(['order_id', 'booking_state']);
            $table->index(['courier', 'booking_state', 'last_synced_at']);
        });

        Schema::create('shipment_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('provider_status', 80)->nullable();
            $table->string('local_status', 30)->nullable();
            $table->string('source', 30);
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->index(['shipment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_histories');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('courier_settings');
    }
};
