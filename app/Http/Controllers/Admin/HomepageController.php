<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageBanner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomepageController extends Controller
{
    public function index(): View
    {
        return view('admin.homepage.index', [
            'banners' => HomepageBanner::query()->orderBy('sort_order')->orderBy('id')->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image_upload')) {
            $data['image_url'] = Storage::disk('public')->url($request->file('image_upload')->store('homepage-banners', 'public'));
        }
        $data['slug'] = Str::slug($data['headline'].'-'.$data['highlight']).'-'.Str::lower(Str::random(5));
        $data['is_active'] = $request->boolean('is_active');

        HomepageBanner::create($data);

        return redirect()->route('admin.homepage')->with('success', 'হোমপেজ ব্যানার যোগ করা হয়েছে।');
    }

    public function update(Request $request, HomepageBanner $banner): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image_upload')) {
            $data['image_url'] = Storage::disk('public')->url($request->file('image_upload')->store('homepage-banners', 'public'));
        }
        $data['is_active'] = $request->boolean('is_active');
        $banner->update($data);

        return redirect()->route('admin.homepage')->with('success', 'হোমপেজ ব্যানার আপডেট করা হয়েছে।');
    }

    public function destroy(HomepageBanner $banner): RedirectResponse
    {
        $banner->delete();

        return redirect()->route('admin.homepage')->with('success', 'হোমপেজ ব্যানার মুছে ফেলা হয়েছে।');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kicker' => ['nullable', 'string', 'max:100'],
            'headline' => ['required', 'string', 'max:120'],
            'highlight' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'button_text' => ['required', 'string', 'max:80'],
            'button_url' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#)/'],
            'image_url' => ['nullable', 'url', 'max:2048', 'required_without:image_upload'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120', 'required_without:image_url'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
