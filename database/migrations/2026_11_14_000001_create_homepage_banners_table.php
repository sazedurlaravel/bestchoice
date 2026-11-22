<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('kicker', 100)->nullable();
            $table->string('headline', 120);
            $table->string('highlight', 120);
            $table->string('description', 255)->nullable();
            $table->string('button_text', 80);
            $table->string('button_url', 255);
            $table->string('image_url', 2048);
            $table->string('background_color', 7)->default('#e8f1e9');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        DB::table('homepage_banners')->insert([
            [
                'slug' => 'new-arrivals', 'kicker' => 'BEST CHOICE · NEW ARRIVALS', 'headline' => 'আপনার স্টাইল,', 'highlight' => 'আপনার পছন্দ',
                'description' => 'ফ্যাশন ও লাইফস্টাইলের নতুন কালেকশন', 'button_text' => 'কালেকশন দেখুন ↗', 'button_url' => '/shop',
                'image_url' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1300&auto=format&fit=crop', 'background_color' => '#e8f1e9', 'sort_order' => 0, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'smart-shopping', 'kicker' => 'SMART SHOPPING', 'headline' => 'স্মার্ট গ্যাজেটে', 'highlight' => 'স্মার্ট জীবন',
                'description' => 'আপনার দৈনন্দিন জীবনের প্রয়োজনীয় টেক পণ্য', 'button_text' => 'গ্যাজেট দেখুন ↗', 'button_url' => '/shop',
                'image_url' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=1300&auto=format&fit=crop', 'background_color' => '#e8eef6', 'sort_order' => 1, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'home-essentials', 'kicker' => 'HOME ESSENTIALS', 'headline' => 'ঘর সাজুক', 'highlight' => 'নতুনভাবে',
                'description' => 'হোম ও কিচেনের বাছাই করা পণ্য', 'button_text' => 'এখনই দেখুন ↗', 'button_url' => '/shop',
                'image_url' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=1300&auto=format&fit=crop', 'background_color' => '#f4e9df', 'sort_order' => 2, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_banners');
    }
};
