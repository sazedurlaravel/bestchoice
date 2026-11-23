<?php

namespace Tests\Feature;

use App\Models\HomepageBanner;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_homepage_banner_and_storefront_uses_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.homepage.banners.store'), [
            'kicker' => 'LIMITED OFFER',
            'headline' => 'নতুন কালেকশন',
            'highlight' => 'আজই দেখুন',
            'description' => 'বিশেষ অফারের পণ্যগুলো দেখুন',
            'button_text' => 'কেনাকাটা করুন',
            'button_url' => '/shop',
            'image_url' => 'https://example.com/banner.jpg',
            'background_color' => '#ffffff',
            'sort_order' => 5,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.homepage'));
        $banner = HomepageBanner::query()->where('headline', 'নতুন কালেকশন')->firstOrFail();
        $this->assertDatabaseHas('homepage_banners', ['id' => $banner->id, 'is_active' => 1]);

        $this->get(route('home'))->assertOk()->assertSee('নতুন কালেকশন')->assertSee('আজই দেখুন');
    }

    public function test_admin_can_create_homepage_banner_with_uploaded_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.homepage.banners.store'), [
            'headline' => 'Uploaded banner',
            'highlight' => 'Today',
            'button_text' => 'Shop now',
            'button_url' => '/shop',
            'image_upload' => UploadedFile::fake()->image('banner.jpg'),
            'background_color' => '#ffffff',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('admin.homepage'));

        $banner = HomepageBanner::query()->where('headline', 'Uploaded banner')->firstOrFail();
        $this->assertStringContainsString('/storage/homepage-banners/', $banner->image_url);
        $this->assertCount(1, Storage::disk('public')->allFiles('homepage-banners'));
    }

    public function test_admin_can_update_homepage_section_content_from_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::factory()->create();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'store_name' => 'My Dynamic Store',
            'phone' => '01700000000',
            'store_email' => 'hello@example.com',
            'store_address' => 'Dhaka, Bangladesh',
            'store_tagline' => 'SHOP SMART',
            'store_description' => 'দৈনন্দিন কেনাকাটার নির্ভরযোগ্য ঠিকানা।',
            'facebook_url' => 'https://facebook.com/example',
            'instagram_url' => 'https://instagram.com/example',
            'delivery_charge' => 60,
            'delivery_charge_inside_dhaka' => 60,
            'delivery_charge_outside_dhaka' => 120,
            'home_collection_subtitle' => 'নতুন ক্যাটাগরি আবিষ্কার করুন',
            'home_featured_kicker' => 'EDITOR PICKS',
            'home_featured_title' => 'সম্পাদকের পছন্দ',
            'theme_primary_color' => '#245c45',
            'theme_accent_color' => '#c47b2c',
            'theme_background_color' => '#f4f1e8',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('store_settings', ['key' => 'home_featured_title', 'value' => 'সম্পাদকের পছন্দ']);
        $this->assertDatabaseHas('store_settings', ['key' => 'home_collection_subtitle', 'value' => 'নতুন ক্যাটাগরি আবিষ্কার করুন']);
        $this->assertDatabaseHas('store_settings', ['key' => 'theme_primary_color', 'value' => '#245c45']);
        $this->get(route('home'))->assertOk()->assertSee('সম্পাদকের পছন্দ')->assertSee('My Dynamic Store')->assertSee('দৈনন্দিন কেনাকাটার নির্ভরযোগ্য ঠিকানা।')->assertSee('--theme-primary:#245c45', false)->assertSee('--theme-background:#f4f1e8', false);
    }

    public function test_admin_can_reset_storefront_theme_colors_without_changing_store_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        StoreSetting::create(['key' => 'theme_primary_color', 'value' => '#245c45']);
        StoreSetting::create(['key' => 'theme_accent_color', 'value' => '#c47b2c']);
        StoreSetting::create(['key' => 'theme_background_color', 'value' => '#f4f1e8']);
        StoreSetting::create(['key' => 'store_name', 'value' => 'Keep This Store']);

        $this->actingAs($admin)
            ->delete(route('admin.settings.theme.reset'))
            ->assertRedirect();

        $this->assertDatabaseMissing('store_settings', ['key' => 'theme_primary_color']);
        $this->assertDatabaseMissing('store_settings', ['key' => 'theme_accent_color']);
        $this->assertDatabaseMissing('store_settings', ['key' => 'theme_background_color']);
        $this->assertDatabaseHas('store_settings', ['key' => 'store_name', 'value' => 'Keep This Store']);
    }

    public function test_admin_can_control_homepage_category_limit_and_product_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.homepage.layout.update'), [
                'home_collection_category_limit' => 6,
                'home_product_columns_desktop' => 5,
                'home_product_columns_mobile' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Homepage layout settings saved.');

        $this->assertDatabaseHas('store_settings', ['key' => 'home_collection_category_limit', 'value' => '6']);
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('--home-products-desktop:5', false)
            ->assertSee('--home-products-mobile:3', false);
    }

    public function test_admin_can_update_storefront_header_and_footer_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.settings.theme.update'), [
                'header_background_color' => '#f0eadf',
                'footer_background_color' => '#102f25',
                'header_logo_text' => 'Custom Store',
                'header_subtitle' => 'SHOP WITH CONFIDENCE',
                'footer_description' => 'A custom footer description.',
                'footer_copyright_text' => 'Custom copyright text',
            ])
            ->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Custom Store')
            ->assertSee('A custom footer description.')
            ->assertSee('--theme-header-background:#f0eadf', false)
            ->assertSee('--theme-footer-background:#102f25', false);
    }

    public function test_admin_can_manage_about_and_contact_page_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.settings.pages.update'), [
                'about_kicker' => 'ABOUT OUR STORE',
                'about_title' => 'Shopping made simple',
                'about_description' => 'A better way to shop online.',
                'about_card_1_title' => 'Easy shopping',
                'about_card_1_text' => 'Find what you need quickly.',
                'about_card_2_title' => 'Trusted service',
                'about_card_2_text' => 'We support every order.',
                'about_card_3_title' => 'Fast delivery',
                'about_card_3_text' => 'We deliver across Bangladesh.',
                'about_callout_title' => 'Need help?',
                'about_callout_text' => 'Our team is ready to help.',
                'contact_kicker' => 'CONTACT US',
                'contact_title' => 'Talk to our team',
                'contact_description' => 'We are here for your questions.',
                'phone' => '01700000000',
                'store_email' => 'support@example.com',
                'store_address' => 'Dhaka, Bangladesh',
                'contact_help_title' => 'Order support',
                'contact_help_text' => 'Keep your order number ready.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'About and Contact page settings updated.');

        $this->get(route('about'))->assertOk()->assertSee('Shopping made simple')->assertSee('Trusted service');
        $this->get(route('contact'))->assertOk()->assertSee('Talk to our team')->assertSee('Order support')->assertSee('01700000000')->assertSee('support@example.com')->assertSee('Dhaka, Bangladesh');
    }
}
