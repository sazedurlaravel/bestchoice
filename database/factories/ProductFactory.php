<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['category_id' => Category::factory(), 'name' => fake()->unique()->words(3, true), 'slug' => fake()->unique()->slug(), 'sku' => fake()->unique()->bothify('BC-####'), 'short_description' => fake()->sentence(), 'full_description' => fake()->paragraph(), 'regular_price' => 1000, 'selling_price' => 800, 'buying_price' => 0, 'stock_quantity' => 10, 'low_stock_threshold' => 5, 'main_image' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&auto=format&fit=crop', 'is_featured' => true, 'is_active' => true];
    }
}
