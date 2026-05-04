<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    /**
     * Reusable query for active and trashed items.
     */
    private function brandQuery(bool $trash = false): Builder
    {
        return $trash ? Brand::onlyTrashed()->latest() : Brand::latest();
    }

    /**
     * Apply Search and Filters.
     */
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

        return $query;
    }

    /**
     * Reusable list response for Index and Trash.
     */
    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);
        $brands = $query->paginate(10);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Brands', 'url' => route('admin.brands.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash Bin',
                'url' => route('admin.brands.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.brands.partials.table', [
                    'brands' => $brands,
                    'isTrash' => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.brands.index', [
            'brands' => $brands,
            'title' => $title,
            'breadcrumb' => $breadcrumb,
            'isTrash' => $isTrash,
        ]);
    }

    public function index(Request $request)
    {
        return $this->listResponse(
            $request,
            $this->brandQuery(),
            'Brand Management'
        );
    }

    public function create(Request $request)
    {
        if (! $request->ajax()) {
            return redirect()
                ->route('admin.brands.index')
                ->with('error', 'Please use the Add Brand button.');
        }

        $brand = null;
        $isEdit = false;
        $action = route('admin.brands.store');

        return response()->json([
            'status' => true,
            'html' => view('admin.brands.partials.form', compact('brand', 'isEdit', 'action'))->render(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:brands,slug'],
        ]);

        Brand::create([
            'name' => $request->name,
            'slug' => $request->slug ? Str::slug($request->slug) : Str::slug($request->name),
            'status' => $request->has('status'),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Brand created successfully.',
        ]);
    }

    public function show(Brand $brand)
    {
        // View single brand details (Optional view based on your need)
        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Brands', 'url' => route('admin.brands.index')],
            ['text' => 'Brand Details', 'url' => route('admin.brands.show', $brand->id)],
        ];

        return view('admin.brands.show', compact('brand', 'breadcrumb'));
    }

    public function edit(Request $request, Brand $brand)
    {
        if (! $request->ajax()) {
            return redirect()->route('admin.brands.index');
        }

        $isEdit = true;
        $action = route('admin.brands.update', $brand->id);

        return response()->json([
            'status' => true,
            'html' => view('admin.brands.partials.form', compact('brand', 'isEdit', 'action'))->render(),
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('brands', 'slug')->ignore($brand->id),
            ],
        ]);

        $brand->update([
            'name' => $request->name,
            'slug' => $request->slug ? Str::slug($request->slug) : Str::slug($request->name),
            'status' => $request->has('status'),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Brand updated successfully.',
        ]);
    }

    public function destroy(Brand $brand)
    {
        // Prevent deleting brands attached to active products (Optional but recommended)
        if ($brand->products()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete! Brand is attached to products.',
            ], 422);
        }

        $brand->delete();

        return response()->json([
            'status' => true,
            'message' => 'Brand moved to trash successfully.',
        ]);
    }

    public function trash(Request $request)
    {
        return $this->listResponse(
            $request,
            $this->brandQuery(true),
            'Brand Trash Bin',
            true
        );
    }

    public function restore($id)
    {
        Brand::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status' => true,
            'message' => 'Brand restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $brand->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Brand permanently deleted.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $request->validate([
            'action' => ['required', 'in:delete,restore,force_delete,active,inactive'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $request->ids;
        $action = $request->action;

        if ($action === 'delete') {
            // Optional: Filter out brands that have products before bulk deleting
            $validIds = Brand::whereIn('id', $ids)->doesntHave('products')->pluck('id');
            if ($validIds->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'Selected brands are attached to products and cannot be deleted.'], 422);
            }
            Brand::whereIn('id', $validIds)->delete();
            return response()->json(['status' => true, 'message' => count($validIds) . ' selected brands moved to trash.']);
        }

        if ($action === 'restore') {
            Brand::onlyTrashed()->whereIn('id', $ids)->restore();
            return response()->json(['status' => true, 'message' => 'Selected brands restored.']);
        }

        if ($action === 'force_delete') {
            Brand::onlyTrashed()->whereIn('id', $ids)->forceDelete();
            return response()->json(['status' => true, 'message' => 'Selected brands permanently deleted.']);
        }

        $updateData = match ($action) {
            'active' => ['status' => true],
            'inactive' => ['status' => false],
            default => [],
        };

        if (! empty($updateData)) {
            Brand::whereIn('id', $ids)->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Selected brands updated successfully.',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid bulk action selected.',
        ], 422);
    }
}