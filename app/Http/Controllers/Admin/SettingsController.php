<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = StoreSetting::query()->pluck('value', 'key');

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_tagline' => ['nullable', 'string', 'max:100'],
            'store_description' => ['nullable', 'string', 'max:500'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'delivery_charge' => ['required', 'numeric', 'min:0'],
            'delivery_charge_inside_dhaka' => ['required', 'numeric', 'min:0'],
            'delivery_charge_outside_dhaka' => ['required', 'numeric', 'min:0'],
            'home_collection_subtitle' => ['required', 'string', 'max:255'],
            'home_collection_category_limit' => ['nullable', 'integer', 'min:1', 'max:24'],
            'home_featured_kicker' => ['required', 'string', 'max:100'],
            'home_featured_title' => ['required', 'string', 'max:255'],
            'home_product_columns_desktop' => ['nullable', 'integer', 'min:1', 'max:8'],
            'home_product_columns_mobile' => ['nullable', 'integer', 'min:1', 'max:4'],
            'theme_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach ($data as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return back()->with('success', 'স্টোর সেটিংস আপডেট হয়েছে।');
    }

    public function resetTheme(): RedirectResponse
    {
        StoreSetting::query()
            ->whereIn('key', [
                'theme_primary_color',
                'theme_accent_color',
                'theme_background_color',
                'header_background_color',
                'footer_background_color',
                'header_logo_text',
                'header_subtitle',
                'brand_logo_url',
                'header_logo_url',
                'footer_logo_url',
                'favicon_url',
                'footer_description',
                'footer_copyright_text',
            ])
            ->delete();

        return back()->with('success', 'Storefront theme and header/footer settings reset to default.');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'header_background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'header_logo_text' => ['required', 'string', 'max:100'],
            'header_subtitle' => ['nullable', 'string', 'max:100'],
            'footer_description' => ['nullable', 'string', 'max:500'],
            'footer_copyright_text' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return back()->with('success', 'Header and footer settings updated.');
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $request->validate([
            'brand_logo_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'header_logo_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'footer_logo_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'favicon_upload' => ['nullable', 'file', 'mimes:ico,png,jpg,jpeg,webp', 'max:2048'],
        ]);

        foreach ([
            'header_logo_upload' => 'header_logo_url',
            'footer_logo_upload' => 'footer_logo_url',
            'brand_logo_upload' => 'brand_logo_url',
        ] as $uploadField => $settingKey) {
            if (! $request->hasFile($uploadField)) {
                continue;
            }

            StoreSetting::updateOrCreate(
                ['key' => $settingKey],
                ['value' => Storage::disk('public')->url($request->file($uploadField)->store('branding', 'public'))],
            );
        }

        if ($request->hasFile('favicon_upload')) {
            $path = $request->file('favicon_upload')->store('branding', 'public');

            StoreSetting::updateOrCreate(
                ['key' => 'favicon_url'],
                ['value' => Storage::disk('public')->url($path)],
            );
        }

        return back()->with('success', 'Brand logo updated.');
    }

    public function updatePageContent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'about_kicker' => ['required', 'string', 'max:100'],
            'about_title' => ['required', 'string', 'max:255'],
            'about_description' => ['required', 'string', 'max:1000'],
            'about_card_1_title' => ['required', 'string', 'max:120'],
            'about_card_1_text' => ['required', 'string', 'max:500'],
            'about_card_2_title' => ['required', 'string', 'max:120'],
            'about_card_2_text' => ['required', 'string', 'max:500'],
            'about_card_3_title' => ['required', 'string', 'max:120'],
            'about_card_3_text' => ['required', 'string', 'max:500'],
            'about_callout_title' => ['required', 'string', 'max:255'],
            'about_callout_text' => ['required', 'string', 'max:500'],
            'contact_kicker' => ['required', 'string', 'max:100'],
            'contact_title' => ['required', 'string', 'max:255'],
            'contact_description' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'contact_help_title' => ['required', 'string', 'max:255'],
            'contact_help_text' => ['required', 'string', 'max:500'],
        ]);

        foreach ($data as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'About and Contact page settings updated.');
    }

    public function updateCodReview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cod_review_amount_threshold' => ['required', 'integer', 'min:0'],
            'cod_review_duplicate_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'cod_review_refusal_count' => ['required', 'integer', 'min:1'],
            'cod_review_refusal_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cod_review_active_orders_max' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($data as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return back()->with('success', 'COD review settings saved.');
    }

    public function updateHomepageLayout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'home_collection_category_limit' => ['required', 'integer', 'min:1', 'max:24'],
            'home_product_columns_desktop' => ['required', 'integer', 'min:1', 'max:8'],
            'home_product_columns_mobile' => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        foreach ($data as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return back()->with('success', 'Homepage layout settings saved.');
    }
}
