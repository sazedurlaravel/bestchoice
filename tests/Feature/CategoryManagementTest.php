<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_image_is_optional_and_can_be_uploaded(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Without Image',
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.categories'));

        $this->assertDatabaseHas('categories', ['name' => 'Without Image', 'image_path' => null]);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'With Image',
                'sort_order' => 2,
                'is_active' => 1,
                'image_upload' => UploadedFile::fake()->image('category.jpg'),
            ])
            ->assertRedirect(route('admin.categories'));

        $category = Category::query()->where('name', 'With Image')->firstOrFail();
        $this->assertStringStartsWith('/storage/categories/', $category->image_path);
        $this->assertCount(1, Storage::disk('public')->allFiles('categories'));
    }
}
