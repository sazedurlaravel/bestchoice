<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('admin_note')->nullable()->after('note');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['admin_note', 'assigned_to']);
        });
    }
};
