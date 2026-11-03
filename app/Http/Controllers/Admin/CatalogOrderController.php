<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** One admin-owned ordering authority for public categories and category products. */
final class CatalogOrderController extends Controller
{
    private function adminOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin() && auth()->user()->is_active, 403);
        abort_unless(Schema::hasColumn('products', 'sort_order') && Schema::hasColumn('categories', 'sort_order'),
            503, 'Run the approved catalog ordering migration before using this screen.');
    }

    public function index(Request $request): View
    {
        $this->adminOnly();
        $data = $request->validate(['category_id' => ['nullable', 'integer', 'min:1']]);

        // Include inactive rows so their future reactivation retains the chosen order.
        $categories = Category::query()->withCount('products')
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
        $parents = $categories->whereNull('parent_id')->values();
        // A previously deleted parent can leave a child row; keep it orderable.
        $orphans = $categories->filter(static fn (Category $category): bool =>
            $category->parent_id !== null && ! $categories->contains('id', $category->parent_id))->values();
        $selectedId = (int) ($data['category_id'] ?? $parents->first()?->id ?? $categories->first()?->id ?? 0);
        $selectedCategory = $selectedId ? $categories->firstWhere('id', $selectedId) : null;
        if ($selectedId && ! $selectedCategory) {
            abort(404, 'Category not found.');
        }
        $products = $selectedCategory
            ? Product::query()->where('category_id', $selectedId)
                ->orderBy('sort_order')->orderByDesc('id')
                ->get(['id', 'category_id', 'name', 'status', 'sort_order'])
            : collect();

        return view('admin.categories.order', [
            'title' => 'Category & Product Order',
            'categories' => $categories,
            'parents' => $parents,
            'orphans' => $orphans,
            'selectedCategory' => $selectedCategory,
            'products' => $products,
        ]);
    }

    public function categories(Request $request): RedirectResponse
    {
        $this->adminOnly();
        $data = $request->validate([
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $ids = array_map('intval', $data['category_ids']);

        DB::transaction(function () use ($ids): void {
            $existing = Category::query()->orderBy('id')->lockForUpdate()->get(['id', 'parent_id']);
            $actualIds = $existing->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $submitted = $ids;
            sort($actualIds);
            sort($submitted);
            if ($actualIds !== $submitted) {
                throw ValidationException::withMessages([
                    'category_ids' => 'The category list changed. Reload this page and try again.',
                ]);
            }

            $parentById = $existing->keyBy('id');
            $positions = [];
            foreach ($ids as $id) {
                // A child only moves among its own siblings; never change parent_id.
                $group = (int) ($parentById[$id]->parent_id ?? 0);
                $positions[$group] = ($positions[$group] ?? 0) + 1;
                Category::query()->whereKey($id)->update(['sort_order' => $positions[$group]]);
            }
        });

        return redirect()->route('admin.catalog-order.index', $request->only('category_id'))
            ->with('success', 'Category order saved. Public category lists use this order.');
    }

    public function products(Request $request): RedirectResponse
    {
        $this->adminOnly();
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'min:1', 'exists:categories,id'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $categoryId = (int) $data['category_id'];
        $ids = array_map('intval', $data['product_ids']);

        DB::transaction(function () use ($categoryId, $ids): void {
            // This protects against cross-category writes and stale/incomplete submissions.
            $category = Category::query()->whereKey($categoryId)->lockForUpdate()->firstOrFail();
            $existing = Product::query()->where('category_id', $category->id)
                ->orderBy('id')->lockForUpdate()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $submitted = $ids;
            sort($existing);
            sort($submitted);
            if ($existing !== $submitted) {
                throw ValidationException::withMessages([
                    'product_ids' => 'Products changed or belong to another category. Reload and try again.',
                ]);
            }

            foreach ($ids as $position => $id) {
                Product::query()->whereKey($id)->where('category_id', $categoryId)
                    ->update(['sort_order' => $position + 1]);
            }
        });

        return redirect()->route('admin.catalog-order.index', ['category_id' => $categoryId])
            ->with('success', 'Product order saved for category pages and homepage category-wise sections.');
    }
}
