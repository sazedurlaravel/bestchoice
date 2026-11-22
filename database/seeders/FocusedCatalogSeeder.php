<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FocusedCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->removePreviousDemoCatalog();

            $groups = [
                'jeans-items' => [
                    'images' => [
                        'https://images.unsplash.com/photo-1542272604-787c3835535d?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1555689502-c4b22d76c56f?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1506629905607-d9d7d7e8f8b4?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1496217590455-aa63a8350eea?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=600&auto=format&fit=crop',
                    ],
                    'prefix' => 'জিন্স',
                    'types' => ['স্লিম ফিট', 'রিলাক্সড ফিট', 'স্ট্রেট কাট', 'কার্গো', 'স্ট্রেচ', 'স্কিনি', 'ওয়াইড লেগ', 'রিপড', 'ডেনিম', 'ক্যাজুয়াল'],
                    'styles' => ['প্যান্ট', 'জগার', 'শর্টস', 'জ্যাকেট', 'শার্ট'],
                ],
                'women-items' => [
                    'images' => [
                        'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1485968579580-b6d095142e6e?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1525507119028-ed4c629a60a3?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1509631179647-0177331693ae?w=600&auto=format&fit=crop',
                    ],
                    'prefix' => 'উইমেন',
                    'types' => ['কটন', 'ফ্লোরাল', 'এমব্রয়ডারি', 'প্রিমিয়াম', 'ডেইলি', 'পার্টি', 'অফিস', 'লং', 'মডার্ন', 'ঐতিহ্যবাহী'],
                    'styles' => ['কুর্তি', 'ড্রেস', 'থ্রি-পিস', 'শাড়ি', 'টপ'],
                ],
                'kids-items' => [
                    'images' => [
                        'https://images.unsplash.com/photo-1519238263530-99bdd11df2ea?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1503919545889-aef636e10ad4?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1516627145497-ae6968895b74?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1558060370-d644479cb6f7?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1602030028438-4cf7b92b9b5d?w=600&auto=format&fit=crop',
                        'https://images.unsplash.com/photo-1560785496-3c9d27877182?w=600&auto=format&fit=crop',
                    ],
                    'prefix' => 'বেবি ও কিডস',
                    'types' => ['কটন', 'সফট', 'ক্যাজুয়াল', 'পার্টি', 'প্রিন্টেড', 'কমফোর্ট', 'ডেইলি', 'উইন্টার', 'সামার', 'প্রিমিয়াম'],
                    'styles' => ['টি-শার্ট', 'ফ্রক', 'প্যান্ট', 'সেট', 'পোশাক'],
                ],
            ];

            foreach ($groups as $parentSlug => $group) {
                $parent = Category::query()->where('slug', $parentSlug)->firstOrFail();
                $number = 1;

                foreach ($group['types'] as $type) {
                    foreach ($group['styles'] as $style) {
                        Category::updateOrCreate(
                            ['slug' => $parentSlug.'-collection-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)],
                            [
                                'parent_id' => $parent->id,
                                'name' => $group['prefix'].' '.$type.' '.$style,
                                'image_path' => $group['images'][($number - 1) % count($group['images'])],
                                'description' => $group['prefix'].' '.$type.' '.$style.' এর কালেকশন',
                                'sort_order' => 100 + $number,
                                'is_active' => true,
                            ],
                        );
                        $number++;
                    }
                }
            }
        });

        $this->command?->info('Focused catalog ready: 50 subcategories each for Jeans, Women, and Kids/Baby. Previous demo data removed safely.');
    }

    private function removePreviousDemoCatalog(): void
    {
        Product::query()->where('sku', 'like', 'DEMO-%')->delete();

        Category::query()->whereNotNull('parent_id')->where('slug', 'like', 'demo-%')->delete();
        Category::query()->whereNull('parent_id')->where('slug', 'like', 'demo-%')->delete();
    }
}
