<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        $category->loadMissing(['parent', 'children' => fn ($q) => $q->active()->orderBy('sort_order')]);

        $products = Product::query()
            ->whereIn('category_id', $this->categoryTreeIds($category))
            ->active()
            ->with('category')
            ->withCount('variants')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('storefront.categories.show', compact('category', 'products'));
    }

    /**
     * Collects the category and every descendant so parent pages list subcategory products too.
     *
     * @return list<int>
     */
    private function categoryTreeIds(Category $category): array
    {
        $ids = [$category->id];
        $frontier = [$category->id];

        while ($frontier !== []) {
            $childIds = Category::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($childIds, $ids));
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
