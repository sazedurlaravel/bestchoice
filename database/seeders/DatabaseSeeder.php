<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedCategories();
        $this->seedProducts();
        $this->call(OrderSeeder::class);
        $this->seedAdminUser();
    }

    private function seedCategories(): void
    {
        $groups = [
            ['name' => 'জিন্স আইটেম', 'slug' => 'jeans-items', 'description' => 'জিন্স ও ক্যাজুয়াল পোশাকের সেরা কালেকশন', 'image_path' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=600&auto=format&fit=crop'],
            ['name' => 'উইমেন আইটেম', 'slug' => 'women-items', 'description' => 'মহিলাদের পোশাক, ব্যাগ ও অ্যাকসেসরিজ', 'image_path' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=600&auto=format&fit=crop'],
            ['name' => 'কিডস আইটেম', 'slug' => 'kids-items', 'description' => 'শিশুদের পোশাক ও খেলনা', 'image_path' => 'https://images.unsplash.com/photo-1503919545889-aef636e10ad4?w=600&auto=format&fit=crop'],
        ];

        foreach ($groups as $index => $group) {
            Category::updateOrCreate(['slug' => $group['slug']], array_merge($group, ['sort_order' => $index, 'is_active' => true]));
        }

        $subcategories = [
            ['name' => 'জিন্স প্যান্ট', 'slug' => 'jeans-pant', 'parent_slug' => 'jeans-items', 'sort_order' => 0, 'image_path' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=400&auto=format&fit=crop'],
            ['name' => 'টি-শার্ট ও পোলো', 'slug' => 'tshirt-polo', 'parent_slug' => 'jeans-items', 'sort_order' => 1, 'image_path' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&auto=format&fit=crop'],
            ['name' => 'জ্যাকেট ও হুডি', 'slug' => 'jacket-hoodie', 'parent_slug' => 'jeans-items', 'sort_order' => 2, 'image_path' => 'https://images.unsplash.com/photo-1544923246-77307dd654cb?w=400&auto=format&fit=crop'],
            ['name' => 'ড্রেস ও থ্রি-পিস', 'slug' => 'dress-three-piece', 'parent_slug' => 'women-items', 'sort_order' => 0, 'image_path' => 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=400&auto=format&fit=crop'],
            ['name' => 'ব্যাগ ও অ্যাকসেসরিজ', 'slug' => 'bag-accessories', 'parent_slug' => 'women-items', 'sort_order' => 1, 'image_path' => 'https://images.unsplash.com/photo-1547949003-9792a18a2601?w=400&auto=format&fit=crop'],
            ['name' => 'জুয়েলারি ও ঘড়ি', 'slug' => 'jewellery-watch', 'parent_slug' => 'women-items', 'sort_order' => 2, 'image_path' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=400&auto=format&fit=crop'],
            ['name' => 'বাচ্চাদের পোশাক', 'slug' => 'kids-clothes', 'parent_slug' => 'kids-items', 'sort_order' => 0, 'image_path' => 'https://images.unsplash.com/photo-1519238263530-99bdd11df2ea?w=400&auto=format&fit=crop'],
            ['name' => 'খেলনা ও শিক্ষামূলক', 'slug' => 'toys-educational', 'parent_slug' => 'kids-items', 'sort_order' => 1, 'image_path' => 'https://images.unsplash.com/photo-1558060370-d644479cb6f7?w=400&auto=format&fit=crop'],
        ];

        foreach ($subcategories as $sub) {
            $parent = Category::where('slug', $sub['parent_slug'])->first();
            if ($parent === null) {
                continue;
            }
            Category::updateOrCreate(['slug' => $sub['slug']], ['parent_id' => $parent->id, 'name' => $sub['name'], 'image_path' => $sub['image_path'], 'sort_order' => $sub['sort_order'], 'is_active' => true]);
        }
    }

    private function seedProducts(): void
    {
        $products = [
            ['sku' => 'BC-JP-001', 'cat' => 'jeans-pant', 'name' => 'ক্লাসিক স্লিম ফিট জিন্স', 'desc' => 'উন্নত মানের ডেনিম কাপড়ে তৈরি স্লিম ফিট জিন্স। দীর্ঘস্থায়ী ও আরামদায়ক।', 'sell' => 1290, 'reg' => 1590, 'stock' => 30, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-JP-002', 'cat' => 'jeans-pant', 'name' => 'রিলাক্সড ফিট ডেনিম', 'desc' => 'ঢিলেঢালা কাটের আরামদায়ক জিন্স, দৈনন্দিন ব্যবহারের জন্য আদর্শ।', 'sell' => 1150, 'reg' => 1150, 'stock' => 25, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1555689502-c4b22d76c56f?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-JP-003', 'cat' => 'jeans-pant', 'name' => 'স্ট্রেচ কার্গো জিন্স', 'desc' => 'একাধিক পকেটসহ স্ট্রেচ ফ্যাব্রিকের কার্গো জিন্স।', 'sell' => 1450, 'reg' => 1750, 'stock' => 18, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-TS-001', 'cat' => 'tshirt-polo', 'name' => 'প্রিমিয়াম কটন টি-শার্ট', 'desc' => '১০০% কটন সফট টি-শার্ট। গরমে আরামদায়ক।', 'sell' => 490, 'reg' => 650, 'stock' => 50, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-TS-002', 'cat' => 'tshirt-polo', 'name' => 'পোলো শার্ট – ফরেস্ট গ্রিন', 'desc' => 'পিকে কটন পোলো শার্ট। অফিস ও আউটিং উভয়ের জন্যই মানানসই।', 'sell' => 790, 'reg' => 950, 'stock' => 22, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1598032895397-b9472444bf93?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-TS-003', 'cat' => 'tshirt-polo', 'name' => 'গ্রাফিক প্রিন্ট ওভারসাইজড টি-শার্ট', 'desc' => 'ট্রেন্ডি গ্রাফিক প্রিন্ট সহ ওভারসাইজড ফিটের টি-শার্ট।', 'sell' => 550, 'reg' => 550, 'stock' => 35, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1503341338985-95dbe52f9e78?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-JK-001', 'cat' => 'jacket-hoodie', 'name' => 'জেন্টস ডেনিম জ্যাকেট', 'desc' => 'ভারী ডেনিম কাপড়ের টেকসই জ্যাকেট।', 'sell' => 1890, 'reg' => 2290, 'stock' => 15, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1544923246-77307dd654cb?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-JK-002', 'cat' => 'jacket-hoodie', 'name' => 'ফ্লিস হুডি – নেভি ব্লু', 'desc' => 'নরম ফ্লিস ফ্যাব্রিকের আরামদায়ক হুডি।', 'sell' => 1290, 'reg' => 1550, 'stock' => 20, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-DR-001', 'cat' => 'dress-three-piece', 'name' => 'ফ্লোরাল প্রিন্ট ম্যাক্সি ড্রেস', 'desc' => 'হালকা শিফন কাপড়ের ফ্লোরাল প্রিন্ট ম্যাক্সি ড্রেস।', 'sell' => 1490, 'reg' => 1850, 'stock' => 20, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-DR-002', 'cat' => 'dress-three-piece', 'name' => 'সালোয়ার কামিজ থ্রি-পিস', 'desc' => 'ঐতিহ্যবাহী ডিজাইনে আধুনিক থ্রি-পিস সেট।', 'sell' => 2490, 'reg' => 2990, 'stock' => 12, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-DR-003', 'cat' => 'dress-three-piece', 'name' => 'কার্জি কটন কুর্তি', 'desc' => 'খাঁটি কটন কাপড়ে তৈরি আরামদায়ক কুর্তি।', 'sell' => 890, 'reg' => 890, 'stock' => 30, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-BA-001', 'cat' => 'bag-accessories', 'name' => 'মিনিমাল লেদার হ্যান্ডব্যাগ', 'desc' => 'উন্নত মানের পিইউ লেদারের হ্যান্ডব্যাগ।', 'sell' => 1290, 'reg' => 1590, 'stock' => 18, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1547949003-9792a18a2601?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-BA-002', 'cat' => 'bag-accessories', 'name' => 'ট্রেন্ডি ক্রসবডি ব্যাগ', 'desc' => 'ছোট ও স্টাইলিশ ক্রসবডি ব্যাগ।', 'sell' => 790, 'reg' => 990, 'stock' => 25, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-JW-001', 'cat' => 'jewellery-watch', 'name' => 'গোল্ড প্লেটেড নেকলেস সেট', 'desc' => 'উচ্চমানের গোল্ড প্লেটেড নেকলেস সেট।', 'sell' => 1890, 'reg' => 2490, 'stock' => 10, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-KC-001', 'cat' => 'kids-clothes', 'name' => 'কিডস ক্যাজুয়াল টি-শার্ট সেট', 'desc' => 'নরম কটন কাপড়ের বাচ্চাদের টি-শার্ট।', 'sell' => 490, 'reg' => 650, 'stock' => 40, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1503919005314-30d93d07d823?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-KC-002', 'cat' => 'kids-clothes', 'name' => 'বয়েজ ডেনিম জিন্স সেট', 'desc' => 'ছেলেদের জন্য টেকসই ডেনিম জিন্স সেট।', 'sell' => 790, 'reg' => 990, 'stock' => 22, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1519238263530-99bdd11df2ea?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-KC-003', 'cat' => 'kids-clothes', 'name' => 'গার্লস ফ্রক ড্রেস', 'desc' => 'মেয়েদের জন্য রঙিন ও সুন্দর ফ্রক ড্রেস।', 'sell' => 690, 'reg' => 890, 'stock' => 28, 'featured' => true, 'img' => 'https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-TY-001', 'cat' => 'toys-educational', 'name' => 'বাংলা বর্ণমালা শেখার কার্ড', 'desc' => 'রঙিন ছবিসহ বাংলা বর্ণমালা শেখার ফ্ল্যাশকার্ড সেট।', 'sell' => 290, 'reg' => 390, 'stock' => 50, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1558060370-d644479cb6f7?w=600&auto=format&fit=crop'],
            ['sku' => 'BC-TY-002', 'cat' => 'toys-educational', 'name' => 'রঙিন লেগো ব্লক সেট', 'desc' => 'সৃজনশীলতা বাড়ানোর জন্য রঙিন লেগো ব্লক সেট। ৫০ পিস সহ আসে।', 'sell' => 590, 'reg' => 750, 'stock' => 35, 'featured' => false, 'img' => 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=600&auto=format&fit=crop'],
        ];

        foreach ($products as $data) {
            $category = Category::where('slug', $data['cat'])->first();
            if ($category === null) {
                continue;
            }
            Product::updateOrCreate(['sku' => $data['sku']], ['category_id' => $category->id, 'name' => $data['name'], 'slug' => str($data['name'])->slug(), 'short_description' => $data['desc'], 'selling_price' => $data['sell'], 'regular_price' => $data['reg'], 'stock_quantity' => $data['stock'], 'low_stock_threshold' => 5, 'main_image' => $data['img'], 'is_featured' => $data['featured'], 'is_active' => true]);
        }
    }

    private function seedAdminUser(): void
    {
        $email = env('DEMO_ADMIN_EMAIL', 'admin@bestchoice.test');
        $password = env('DEMO_ADMIN_PASSWORD');
        $generated = false;

        if (! $password) {
            if (User::where('email', $email)->exists()) {
                $this->command?->info("Admin {$email} already exists; password unchanged.");

                return;
            }

            $password = Str::password(16, symbols: false);
            $generated = true;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Best Choice Admin', 'password' => Hash::make($password), 'role' => 'admin'],
        );

        if ($generated) {
            $this->command?->warn("Demo admin created: {$email} / {$password}  (shown once — set DEMO_ADMIN_PASSWORD in .env to choose your own)");
        }
    }
}
