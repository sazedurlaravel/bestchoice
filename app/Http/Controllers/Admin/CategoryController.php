<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::with('parent')
            ->withCount('products')
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderBy('sort_order')
            ->paginate(30);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $parents = Category::topLevel()->active()->orderBy('name')->get();

        return view('admin.categories.create', compact('parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image_upload')) {
            $data['image_path'] = '/storage/'.$request->file('image_upload')->store('categories', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image_path'] = $data['image_url'];
        }
        unset($data['image_upload'], $data['image_url']);

        Category::create($data);

        return redirect()->route('admin.categories')->with('success', 'ক্যাটাগরি সফলভাবে যোগ করা হয়েছে।');
    }

    public function edit(Category $category): View
    {
        $parents = Category::topLevel()->active()->where('id', '!=', $category->id)->orderBy('name')->get();

        return view('admin.categories.edit', compact('category', 'parents'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image_upload')) {
            $data['image_path'] = '/storage/'.$request->file('image_upload')->store('categories', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image_path'] = $data['image_url'];
        }
        unset($data['image_upload'], $data['image_url']);

        $category->update($data);

        return redirect()->route('admin.categories')->with('success', 'ক্যাটাগরি সফলভাবে আপডেট করা হয়েছে।');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return redirect()->route('admin.categories')->with('error', 'এই ক্যাটাগরিতে পণ্য আছে। প্রথমে পণ্যগুলো সরান।');
        }

        $category->delete();

        return redirect()->route('admin.categories')->with('success', 'ক্যাটাগরি মুছে ফেলা হয়েছে।');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        $deleted = 0;
        $failed = [];

        foreach (Category::query()->whereIn('id', $data['category_ids'])->get() as $category) {
            if ($category->products()->exists()) {
                $failed[] = $category->name.': এই ক্যাটাগরিতে পণ্য আছে।';

                continue;
            }

            $category->delete();
            $deleted++;
        }

        $message = $deleted.'টি ক্যাটাগরি মুছে ফেলা হয়েছে।';
        if ($failed) {
            $message .= ' বাদ গেছে: '.implode(' | ', $failed);
        }

        return redirect()->route('admin.categories')->with($deleted > 0 ? 'success' : 'error', $message);
    }
}
