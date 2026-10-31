<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\P33CatalogAdminAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CategoryController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function hierarchyAvailable(): bool
    {
        return Schema::hasColumn('categories', 'parent_id')
            && Schema::hasColumn('categories', 'sort_order');
    }

    private function parentChoices(?int $excludeId = null)
    {
        if (! $this->hierarchyAvailable()) {
            return collect();
        }
        return Category::query()->where('status', true)->whereNull('parent_id')
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    private function assertNoParentCycle(int $categoryId, ?int $parentId): void
    {
        $seen = [$categoryId => true];
        $steps = 0;
        while ($parentId !== null) {
            if (isset($seen[$parentId]) || ++$steps > 100) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own ancestor.']);
            }
            $seen[$parentId] = true;
            $row = Category::query()->whereKey($parentId)->lockForUpdate()->first();
            if ($row === null || $row->trashed() || ! $row->status) {
                throw ValidationException::withMessages(['parent_id' => 'Select an active parent category.']);
            }
            $parentId = $row->parent_id === null ? null : (int) $row->parent_id;
        }
    }

    private function categoryQuery(bool $trash = false): Builder
    {
        $query = $trash
            ? Category::onlyTrashed()
            : Category::query();

        $query->withCount(['products', 'activeProducts']);
        if ($this->hierarchyAvailable()) {
            $query->with('parent')->withCount('children');
        }
        return $query->latest();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (bool) $request->status);
        }

        if ($request->filled('front_view') && $request->front_view !== 'all') {
            $query->where('is_front_view', (bool) $request->front_view);
        }

        return $query;
    }

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);

        $categories = $query->paginate(10);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Categories', 'url' => route('admin.categories.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url' => route('admin.categories.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.categories.partials.table', [
                    'categories' => $categories,
                    'isTrash' => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.categories.index', [
            'categories' => $categories,
            'title' => $title,
            'breadcrumb' => $breadcrumb,
            'isTrash' => $isTrash,
        ]);
    }

    public function index(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->categoryQuery(),
            'Category Management'
        );
    }

    public function create(Request $request)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Please use the Add Category button.');
        }

        $category = null;
        $isEdit = false;
        $action = route('admin.categories.store');
        $parents = $this->parentChoices();
        $hierarchyAvailable = $this->hierarchyAvailable();

        return response()->json([
            'status' => true,
            'html' => view('admin.categories.partials.form', compact(
                'category',
                'isEdit',
                'action',
                'parents',
                'hierarchyAvailable'
            ))->render(),
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'parent_id' => $this->hierarchyAvailable() ? ['nullable', 'integer', 'exists:categories,id'] : ['prohibited'],
            'sort_order' => $this->hierarchyAvailable() ? ['nullable', 'integer', 'min:0', 'max:1000000'] : ['prohibited'],
        ]);

        return DB::transaction(function () use ($request) {
            $hierarchyData = [];
            if ($this->hierarchyAvailable()) {
                P33CatalogAdminAccess::assertAllowed();
                $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;
                if ($parentId !== null) {
                    $parent = Category::query()->whereKey($parentId)->lockForUpdate()->first();
                    if ($parent === null || $parent->trashed() || ! $parent->status || $parent->parent_id !== null) {
                        throw ValidationException::withMessages(['parent_id' => 'Invalid parent category.']);
                    }
                }
                $hierarchyData = ['parent_id' => $parentId, 'sort_order' => (int) $request->input('sort_order', 0)];
            }
            $category = Category::create([
                ...$hierarchyData,
                'name' => $request->name,
                'slug' => $request->slug
                    ? Str::slug($request->slug)
                    : Str::slug($request->name) . '-' . time(),
                'is_front_view' => $request->has('is_front_view'),
                'status' => $request->has('status'),
            ]);

            if ($request->hasFile('image')) {
                $category->addMediaFromRequest('image')->toMediaCollection('category_image');
            }

            return response()->json([
                'status' => true,
                'message' => 'Category created successfully.',
            ]);
        });
    }

    public function show(Category $category)
    {
        $this->adminOnly();

        $category->loadCount(['products', 'activeProducts']);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Categories', 'url' => route('admin.categories.index')],
            ['text' => 'Category Details', 'url' => route('admin.categories.show', $category->id)],
        ];

        return view('admin.categories.show', [
            'category' => $category,
            'title' => 'Category Details',
            'breadcrumb' => $breadcrumb,
        ]);
    }

    public function edit(Request $request, Category $category)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.categories.index');
        }

        $isEdit = true;
        $action = route('admin.categories.update', $category->id);
        $parents = $this->parentChoices($category->id);
        $hierarchyAvailable = $this->hierarchyAvailable();

        return response()->json([
            'status' => true,
            'html' => view('admin.categories.partials.form', compact(
                'category',
                'isEdit',
                'action',
                'parents',
                'hierarchyAvailable'
            ))->render(),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $this->adminOnly();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'parent_id' => $this->hierarchyAvailable() ? ['nullable', 'integer', 'exists:categories,id'] : ['prohibited'],
            'sort_order' => $this->hierarchyAvailable() ? ['nullable', 'integer', 'min:0', 'max:1000000'] : ['prohibited'],
        ]);

        return DB::transaction(function () use ($request, $category) {
            $hierarchyData = [];
            if ($this->hierarchyAvailable()) {
                P33CatalogAdminAccess::assertAllowed();
                // Lock the changed category and follow the parent chain under transaction.
                Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
                $parentId = $request->exists('parent_id')
                    ? ($request->filled('parent_id') ? (int) $request->input('parent_id') : null)
                    : ($category->parent_id === null ? null : (int) $category->parent_id);
                $this->assertNoParentCycle((int) $category->id, $parentId);
                if ($parentId !== null) {
                    // Exactly two levels (root -> subcategory), never hide deeper descendants.
                    $parent = Category::query()->whereKey($parentId)->lockForUpdate()->firstOrFail();
                    if ($parent->parent_id !== null) {
                        throw ValidationException::withMessages(['parent_id' => 'Choose a top-level parent category.']);
                    }
                    if (Category::query()->where('parent_id', $category->id)->exists()) {
                        throw ValidationException::withMessages(['parent_id' => 'A category with sub-categories cannot be nested below another category.']);
                    }
                }
                $hierarchyData = [
                    'parent_id' => $parentId,
                    'sort_order' => $request->exists('sort_order')
                        ? (int) $request->input('sort_order', 0) : (int) $category->sort_order,
                ];
            }
            $category->update([
                ...$hierarchyData,
                'name' => $request->name,
                'slug' => $request->slug
                    ? Str::slug($request->slug)
                    : Str::slug($request->name) . '-' . $category->id,
                'is_front_view' => $request->has('is_front_view'),
                'status' => $request->has('status'),
            ]);

            if ($request->hasFile('image')) {
                $category->clearMediaCollection('category_image');
                $category->addMediaFromRequest('image')->toMediaCollection('category_image');
            }

            return response()->json([
                'status' => true,
                'message' => 'Category updated successfully.',
            ]);
        });
    }

    public function destroy(Category $category)
    {
        $this->adminOnly();

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category moved to trash successfully.',
        ]);
    }

    public function trash(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->categoryQuery(true),
            'Category Trash Bin',
            true
        );
    }

    public function restore($id)
    {
        $this->adminOnly();

        Category::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status' => true,
            'message' => 'Category restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();

        $category = Category::onlyTrashed()->findOrFail($id);

        if ($this->hierarchyAvailable()
            && Category::withTrashed()->where('parent_id', $category->id)->exists()) {
            return response()->json(['status' => false,
                'message' => 'Move child categories before permanently deleting this parent.'], 422);
        }

        if ($category->products()->withTrashed()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'This category has products. Please move products to another category before permanent delete.',
            ], 422);
        }

        $category->clearMediaCollection('category_image');
        $category->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Category permanently deleted successfully.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'action' => ['required', 'in:delete,restore,force_delete,active,inactive,front_view,remove_front_view'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $request->ids;
        $action = $request->action;

        if ($action === 'delete') {
            Category::whereIn('id', $ids)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Selected categories moved to trash.',
            ]);
        }

        if ($action === 'restore') {
            Category::onlyTrashed()->whereIn('id', $ids)->restore();

            return response()->json([
                'status' => true,
                'message' => 'Selected categories restored.',
            ]);
        }

        if ($action === 'force_delete') {
            $categories = Category::onlyTrashed()
                ->whereIn('id', $ids)
                ->withCount(['products'])
                ->get();

            foreach ($categories as $category) {
                if ($category->products_count > 0
                    || ($this->hierarchyAvailable()
                        && Category::withTrashed()->where('parent_id', $category->id)->exists())) {
                    continue;
                }

                $category->clearMediaCollection('category_image');
                $category->forceDelete();
            }

            return response()->json([
                'status' => true,
                'message' => 'Selected categories permanently deleted. Categories with products were skipped.',
            ]);
        }

        $updateData = match ($action) {
            'active' => ['status' => true],
            'inactive' => ['status' => false],
            'front_view' => ['is_front_view' => true],
            'remove_front_view' => ['is_front_view' => false],
            default => [],
        };

        if (! empty($updateData)) {
            Category::whereIn('id', $ids)->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Selected categories updated successfully.',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid bulk action selected.',
        ], 422);
    }

    public function deleteMedia($id)
    {
        $this->adminOnly();

        $media = Media::findOrFail($id);
        $media->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category image deleted successfully.',
        ]);
    }
}