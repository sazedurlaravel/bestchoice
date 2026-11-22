<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminInventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_customers_cannot_manage_products_or_variants(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product);

        $this->post(route('admin.products.store'), [])->assertRedirect(route('login'));
        $this->post(route('admin.products.variants.store', $product), [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->post(route('admin.products.store'), [])->assertForbidden();
        $this->put(route('admin.products.update', $product), [])->assertForbidden();
        $this->delete(route('admin.products.destroy', $product))->assertForbidden();
        $this->post(route('admin.products.variants.store', $product), [])->assertForbidden();
        $this->put(route('admin.products.variants.update', [$product, $variant]), [])->assertForbidden();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_new_product_form_shows_exact_field_error_and_highlights_input(): void
    {
        $this->admin();
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'name' => 'পণ্যের নাম অবশ্যই দিতে হবে।',
        ]));
        $this->withSession(['errors' => $errors]);
        session()->save();

        $this->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('Product name is required.')
            ->assertSee('name="name"', false)
            ->assertSee('class="form-input is-invalid"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_product_opening_stock_is_recorded_in_the_ledger(): void
    {
        $admin = $this->admin();
        $data = $this->productData();

        $this->post(route('admin.products.store'), $data)->assertRedirect(route('admin.products'));

        $product = Product::query()->where('sku', $data['sku'])->firstOrFail();
        $this->assertSame(7, $product->stock_quantity);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id, 'variant_id' => null, 'stock_before' => 0, 'stock_after' => 7, 'quantity_change' => 7, 'operation_key' => $data['stock_operation_key'], 'user_id' => $admin->id]);
    }

    public function test_product_details_are_saved_as_safe_rich_text_and_empty_image_fields_are_optional(): void
    {
        $this->admin();
        $data = [
            ...$this->productData(),
            'full_description' => '<p><strong>নতুন পণ্য</strong></p><script>alert("x")</script>',
            'main_image' => '',
            'gallery_images' => [['url' => '', 'alt_text' => '']],
        ];

        $this->post(route('admin.products.store'), $data)
            ->assertRedirect(route('admin.products'))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('sku', $data['sku'])->firstOrFail();
        $this->assertSame('<p><strong>নতুন পণ্য</strong></p>', $product->full_description);
        $this->assertSame(0, $product->images()->count());
    }

    public function test_new_product_upload_saves_image_to_public_disk(): void
    {
        $this->admin();
        Storage::fake('public');

        $data = [
            ...$this->productData(),
            'main_image_upload' => UploadedFile::fake()->image('product.jpg'),
        ];

        $this->post(route('admin.products.store'), $data)
            ->assertRedirect(route('admin.products'))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('sku', $data['sku'])->firstOrFail();
        $this->assertStringStartsWith('/storage/products/', parse_url($product->main_image, PHP_URL_PATH));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($product->main_image, PHP_URL_PATH)));
    }

    public function test_new_product_upload_saves_multiple_gallery_images_to_public_disk(): void
    {
        $this->admin();
        Storage::fake('public');

        $data = [
            ...$this->productData(),
            'gallery_uploads' => [
                UploadedFile::fake()->image('gallery-one.jpg'),
                UploadedFile::fake()->image('gallery-two.png'),
            ],
        ];

        $this->post(route('admin.products.store'), $data)
            ->assertRedirect(route('admin.products'))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('sku', $data['sku'])->firstOrFail();
        $galleryImages = $product->images()->get();

        $this->assertCount(2, $galleryImages);
        foreach ($galleryImages as $image) {
            $path = str_replace('/storage/', '', parse_url($image->image_path, PHP_URL_PATH));
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_existing_gallery_images_can_be_removed_from_a_product(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $image = $product->images()->create(['image_path' => 'https://example.com/gallery.jpg', 'sort_order' => 0]);

        $this->put(route('admin.products.update', $product), [
            ...$this->productData($product),
            'remove_gallery_image_ids' => [$image->id],
        ])->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_existing_main_image_can_be_removed_from_a_product(): void
    {
        $this->admin();
        $product = Product::factory()->create([
            'stock_quantity' => 0,
            'main_image' => 'https://example.com/main.jpg',
        ]);

        $this->put(route('admin.products.update', $product), [
            ...$this->productData($product),
            'main_image' => '',
            'remove_main_image' => '1',
        ])->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        $this->assertNull($product->refresh()->main_image);
    }

    public function test_product_stock_update_is_idempotent_even_after_later_stock_movements(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $data = [...$this->productData($product), 'stock_quantity' => 15];
        $this->put(route('admin.products.update', $product), $data)->assertRedirect(route('admin.products'));
        app(InventoryService::class)->stockOut($product, 2, 'Sale');

        $this->put(route('admin.products.update', $product), $data)->assertRedirect(route('admin.products'));

        $this->assertSame(13, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseHas('inventory_transactions', ['operation_key' => $data['stock_operation_key'], 'stock_before' => 10, 'stock_after' => 15]);
    }

    public function test_maximum_length_product_name_keeps_generated_slug_within_column_limit(): void
    {
        $this->admin();

        $this->post(route('admin.products.store'), [...$this->productData(), 'name' => str_repeat('a', 255)])->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        $product = Product::query()->firstOrFail();
        $this->assertSame(255, strlen($product->slug));
        $this->assertSame(str_repeat('a', 255), $product->name);
    }

    public function test_stale_product_form_cannot_restore_stock_or_partially_save_metadata(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $name = $product->name;
        $data = [...$this->productData($product), 'name' => 'Changed name'];
        app(InventoryService::class)->stockOut($product, 2, 'Sale');

        $this->put(route('admin.products.update', $product), $data)->assertInvalid(['stock_quantity']);

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertSame($name, $product->name);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_metadata_save_rejects_stale_baseline_even_when_target_already_matches_stock(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 8]);

        $this->put(route('admin.products.update', $product), [...$this->productData($product), 'expected_stock' => 10, 'name' => 'Updated name'])->assertInvalid(['stock_quantity']);

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertNotSame('Updated name', $product->name);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('invalidInheritedPrices')]
    public function test_parent_price_edits_cannot_invalidate_variant_prices(array $variantPrices, array $parentPrices): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product, $variantPrices);
        $data = [...$this->productData($product), ...$parentPrices, 'name' => 'Must not save'];

        $this->put(route('admin.products.update', $product), $data)->assertInvalid(['regular_price']);

        $this->assertSame('800.00', $product->refresh()->selling_price);
        $this->assertSame('1000.00', $product->regular_price);
        $this->assertNotSame('Must not save', $product->name);
        $this->assertSame(0, $variant->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public static function invalidInheritedPrices(): array
    {
        return [
            'regular fallback below selling override' => [['price_override' => 900], ['regular_price' => 850]],
            'selling fallback above regular override' => [['regular_price' => 900], ['selling_price' => 950]],
        ];
    }

    public function test_product_validation_requires_stock_tokens_and_respects_pricing_and_column_limits(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        $data = [...$this->productData($product), 'regular_price' => 1, 'short_description' => str_repeat('x', 256), 'main_image' => 'https://example.com/'.str_repeat('x', 240)];
        unset($data['expected_stock'], $data['stock_operation_key']);

        $this->put(route('admin.products.update', $product), $data)->assertInvalid(['regular_price', 'short_description', 'main_image', 'expected_stock', 'stock_operation_key']);

        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_variant_opening_stock_is_independent_and_options_are_synchronized(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $data = $this->variantData();

        $this->post(route('admin.products.variants.store', $product), $data)->assertRedirect(route('admin.products.edit', $product))->assertSessionHasNoErrors();

        $variant = $product->variants()->firstOrFail();
        $this->assertSame(['size' => 'M', 'color' => 'Green'], $variant->options);
        $this->assertSame(4, $variant->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertSame(800.0, $variant->effectiveSellingPrice());
        $this->assertSame(1000.0, $variant->effectiveRegularPrice());
        $this->assertDatabaseHas('inventory_transactions', ['variant_id' => $variant->id, 'product_id' => $product->id, 'stock_before' => 0, 'stock_after' => 4, 'operation_key' => $data['stock_operation_key'], 'user_id' => $admin->id]);
    }

    public function test_main_product_form_updates_existing_variant_stock(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product, ['stock_quantity' => 4]);
        $variantData = [...$this->variantData($variant), 'id' => $variant->id, 'stock_quantity' => 6];

        $this->put(route('admin.products.update', $product), [
            ...$this->productData($product),
            'stock_quantity' => 0,
            'has_variants' => 1,
            'variants' => [$variantData],
        ])->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        $this->assertSame(6, $variant->refresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'stock_before' => 4,
            'stock_after' => 6,
            'user_id' => $admin->id,
        ]);
    }

    public function test_new_product_creates_each_comma_separated_size_and_color_combination(): void
    {
        $this->admin();
        $data = [
            ...$this->productData(),
            'stock_quantity' => 0,
            'variants' => [[
                'name' => 'T-Shirt',
                'sku' => 'TSHIRT',
                'size' => 'S, M',
                'color' => 'Red, Blue',
                'price_override' => null,
                'regular_price' => null,
                'stock_quantity' => 3,
            ]],
        ];

        $this->post(route('admin.products.store'), $data)->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        $product = Product::query()->where('sku', $data['sku'])->firstOrFail();
        $this->assertSame(4, $product->variants()->count());
        $this->assertEqualsCanonicalizing(
            ['S/Red', 'S/Blue', 'M/Red', 'M/Blue'],
            $product->variants->map(fn (ProductVariant $variant) => $variant->size.'/'.$variant->color)->all(),
        );
        $this->assertSame([3, 3, 3, 3], $product->variants->pluck('stock_quantity')->all());
    }

    public function test_existing_product_variant_form_creates_comma_separated_combinations(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);

        $this->post(route('admin.products.variants.store', $product), [
            ...$this->variantData(),
            'name' => 'Hoodie',
            'sku' => 'HOODIE',
            'size' => 'M, L',
            'color' => 'Black, White',
        ])->assertRedirect(route('admin.products.edit', $product))->assertSessionHasNoErrors();

        $this->assertSame(4, $product->variants()->count());
        $this->assertSame(4, $product->variants()->where('stock_quantity', 4)->count());
    }

    public function test_variant_rejects_duplicate_canonical_combinations_and_skus(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product);

        $this->post(route('admin.products.variants.store', $product), [...$this->variantData(), 'size' => ' m ', 'color' => ' green '])->assertSessionHasErrors(['size'], null, 'variant_new');
        $this->post(route('admin.products.variants.store', $product), [...$this->variantData(), 'sku' => $variant->sku, 'size' => 'L'])->assertSessionHasErrors(['sku'], null, 'variant_new');

        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_direct_factory_creation_cannot_bypass_canonical_uniqueness(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $this->variant($product);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->variant($product, ['size' => ' m ', 'color' => ' GREEN ']);
    }

    public function test_untyped_legacy_variant_is_not_backfilled_on_metadata_save(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product, ['size' => null, 'color' => null, 'options' => ['size' => 'Legacy M']]);

        $variant->update(['name' => 'Legacy renamed']);

        $this->assertNull($variant->refresh()->combination_key);
        $this->assertNull($variant->size);
        $this->assertSame(['size' => 'Legacy M'], $variant->options);
    }

    public function test_variant_creation_and_update_require_zero_parent_stock(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $variant = $this->variant($product);

        $this->post(route('admin.products.variants.store', $product), [...$this->variantData(), 'size' => 'L'])->assertSessionHasErrors(['stock_quantity'], null, 'variant_new');
        $this->put(route('admin.products.variants.update', [$product, $variant]), [...$this->variantData($variant), 'name' => 'Must not save'])->assertSessionHasErrors(['stock_quantity'], null, 'variant_'.$variant->id);

        $this->assertSame(3, $product->refresh()->stock_quantity);
        $this->assertSame('Medium green', $variant->refresh()->name);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_first_variant_is_rejected_after_simple_checkout_depletes_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 1]);
        $this->post(route('cart.add'), ['product_id' => $product->id, 'qty' => 1])->assertRedirect();
        $token = $this->get(route('checkout.index'))->assertOk()->viewData('checkoutToken');
        $this->post(route('checkout.store'), ['checkout_token' => $token, 'customer_name' => 'Test Customer', 'customer_phone' => '01700000000', 'customer_address' => 'Dhaka', 'customer_district' => 'ঢাকা', 'payment_method' => 'cash_on_delivery'])->assertRedirect(route('order.confirmation'));
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->admin();

        $this->post(route('admin.products.variants.store', $product), $this->variantData())->assertSessionHasErrors(['size'], null, 'variant_new');

        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'stock_restored_quantity' => 0]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_variant_validation_requires_typed_identity_and_valid_effective_prices(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);

        $this->post(route('admin.products.variants.store', $product), [...$this->variantData(), 'size' => '', 'color' => ''])->assertSessionHasErrors(['size', 'color'], null, 'variant_new');
        $this->post(route('admin.products.variants.store', $product), [...$this->variantData(), 'regular_price' => 700])->assertSessionHasErrors(['regular_price'], null, 'variant_new');
        $this->post(route('admin.products.variants.store', $product), [...$this->variantData(), 'price_override' => 1100])->assertSessionHasErrors(['regular_price'], null, 'variant_new');

        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_first_variant_is_allowed_when_simple_order_quantity_is_fully_restored(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $this->history($product, null, 'order');
        $item = OrderItem::query()->firstOrFail();
        $item->stock_restored_quantity = $item->quantity;
        $item->save();

        $this->post(route('admin.products.variants.store', $product), $this->variantData())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'variant_id' => null, 'stock_restored_quantity' => 1]);
    }

    public function test_variant_update_requires_baseline_and_rejects_stale_stock_atomically(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product, ['stock_quantity' => 4]);
        $data = [...$this->variantData($variant), 'name' => 'Stale edit'];
        app(InventoryService::class)->stockOut($product, 1, 'Sale', null, $variant);

        $this->put(route('admin.products.variants.update', [$product, $variant]), $data)->assertSessionHasErrors(['stock_quantity'], null, 'variant_'.$variant->id);
        unset($data['expected_stock']);
        $this->put(route('admin.products.variants.update', [$product, $variant]), $data)->assertSessionHasErrors(['expected_stock'], null, 'variant_'.$variant->id);

        $this->assertSame(3, $variant->refresh()->stock_quantity);
        $this->assertSame('Medium green', $variant->name);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_variant_update_is_idempotent_and_can_deactivate_without_losing_history(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product, ['stock_quantity' => 4]);
        $data = [...$this->variantData($variant), 'stock_quantity' => 6, 'is_active' => 0];

        $this->put(route('admin.products.variants.update', [$product, $variant]), $data)->assertSessionHasNoErrors();
        $this->put(route('admin.products.variants.update', [$product, $variant]), $data)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_quantity' => 6, 'is_active' => false]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_variant_cannot_be_updated_under_another_product(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $other = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($other);

        $this->put(route('admin.products.variants.update', [$product, $variant]), $this->variantData($variant))->assertNotFound();

        $this->assertSame($other->id, $variant->refresh()->product_id);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('historyTypes')]
    public function test_historical_variant_identity_and_deletion_are_protected(string $history): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product);
        $this->history($product, $variant, $history);

        $this->put(route('admin.products.variants.update', [$product, $variant]), [...$this->variantData($variant), 'size' => 'L'])->assertSessionHasErrors(['size'], null, 'variant_'.$variant->id);
        $this->assertSame('M', $variant->refresh()->size);
        $this->put(route('admin.products.variants.update', [$product, $variant]), [...$this->variantData($variant), 'size' => ' m ', 'color' => ' GREEN ', 'name' => 'Renamed', 'sku' => 'RENAMED-SKU'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'name' => 'Renamed', 'sku' => 'RENAMED-SKU']);
        if ($history === 'order') {
            $this->assertDatabaseHas('order_items', ['variant_id' => $variant->id, 'product_sku' => 'SNAPSHOT-SKU', 'product_name' => 'Historical product']);
        }

        $this->expectException(ValidationException::class);
        $variant->refresh()->delete();
    }

    public function test_historical_variant_cannot_be_reparented_directly(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product);
        $this->history($product, $variant, 'ledger');
        $other = Product::factory()->create(['stock_quantity' => 0]);

        $this->expectException(ValidationException::class);
        $variant->update(['product_id' => $other->id]);
    }

    #[DataProvider('archiveTypes')]
    public function test_product_destroy_permanently_deletes_products_with_history_or_variants(string $history): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        if ($history === 'variant') {
            $this->variant($product);
        } else {
            $this->history($product, null, $history);
        }

        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products'));

        $this->assertModelMissing($product);
        $this->assertDatabaseCount(match ($history) {
            'variant' => 'product_variants', 'ledger' => 'inventory_transactions', default => 'order_items'
        }, 0);
    }

    public function test_product_destroy_deletes_unused_untracked_product(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);

        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products'));

        $this->assertModelMissing($product);
    }

    public function test_product_destroy_preserves_positive_legacy_stock_without_a_ledger(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 7]);

        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products'));

        $this->assertModelMissing($product);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_inventory_endpoint_replays_duplicate_adjustment_without_a_second_ledger_entry(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $data = ['product_id' => $product->id, 'type' => 'adjustment', 'quantity' => 8, 'expected_stock' => 5, 'operation_key' => (string) Str::uuid(), 'reason' => 'Counted stock'];

        $this->post(route('admin.inventory.adjust'), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.inventory.adjust'), $data)->assertSessionHasNoErrors();

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_edit_renders_variant_forms_without_changing_parent_form_values(): void
    {
        $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        $variant = $this->variant($product);

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee($variant->sku)
            ->assertSee('name="expected_stock"', false)
            ->assertSee('name="stock_operation_key"', false)
            ->assertDontSee('Add New Variant');
    }

    public static function historyTypes(): array
    {
        return ['ledger' => ['ledger'], 'order' => ['order']];
    }

    public static function archiveTypes(): array
    {
        return [...self::historyTypes(), 'variant' => ['variant']];
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    private function productData(?Product $product = null): array
    {
        return [
            'category_id' => $product?->category_id ?? Category::factory()->create()->id,
            'name' => $product?->name ?? 'New product', 'sku' => $product?->sku ?? 'NEW-PRODUCT',
            'selling_price' => 800, 'regular_price' => 1000,
            'stock_quantity' => $product?->stock_quantity ?? 7, 'expected_stock' => $product?->stock_quantity ?? 0,
            'stock_operation_key' => (string) Str::uuid(), 'low_stock_threshold' => 5,
            'is_active' => 1, 'is_featured' => 0,
        ];
    }

    private function variantData(?ProductVariant $variant = null): array
    {
        return [
            'name' => $variant?->name ?? 'Medium green', 'sku' => $variant?->sku ?? 'NEW-VARIANT',
            'size' => $variant?->size ?? 'M', 'color' => $variant?->color ?? 'Green',
            'price_override' => null, 'regular_price' => null, 'stock_quantity' => $variant?->stock_quantity ?? 4,
            'expected_stock' => $variant?->stock_quantity ?? 0, 'stock_operation_key' => (string) Str::uuid(), 'is_active' => 1,
        ];
    }

    private function variant(Product $product, array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Medium green', 'sku' => (string) Str::uuid(),
            'size' => 'M', 'color' => 'Green', 'stock_quantity' => 0, 'is_active' => true, ...$attributes,
        ]);
    }

    private function history(Product $product, ?ProductVariant $variant, string $type): void
    {
        if ($type === 'ledger') {
            InventoryTransaction::create(['product_id' => $product->id, 'variant_id' => $variant?->id, 'type' => 'adjustment', 'quantity_change' => 1, 'stock_before' => 0, 'stock_after' => 1]);

            return;
        }

        $order = Order::create(['order_number' => (string) Str::uuid(), 'customer_name' => 'Customer', 'customer_phone' => '01700000000', 'customer_address' => 'Dhaka', 'subtotal' => 800, 'total' => 860]);
        $item = new OrderItem(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Historical product', 'product_sku' => 'SNAPSHOT-SKU', 'quantity' => 1, 'unit_price' => 800, 'subtotal' => 800]);
        $item->variant_id = $variant?->id;
        $item->save();
    }
}
