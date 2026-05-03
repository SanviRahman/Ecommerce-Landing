<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    private function categoryQuery(bool $trash = false): Builder
    {
        $query = $trash
            ? Category::onlyTrashed()
            : Category::query();

        return $query->withCount(['products', 'activeProducts'])->latest();
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

        return response()->json([
            'status' => true,
            'html' => view('admin.categories.partials.form', compact(
                'category',
                'isEdit',
                'action'
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
        ]);

        return DB::transaction(function () use ($request) {
            $category = Category::create([
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

        return response()->json([
            'status' => true,
            'html' => view('admin.categories.partials.form', compact(
                'category',
                'isEdit',
                'action'
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
        ]);

        return DB::transaction(function () use ($request, $category) {
            $category->update([
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
                if ($category->products_count > 0) {
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