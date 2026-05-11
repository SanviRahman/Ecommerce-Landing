<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function adminOrEmployeeOnly(): void
    {
        if (! auth()->check() || (! auth()->user()->isAdmin() && ! auth()->user()->isEmployee())) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function productQuery(bool $trash = false): Builder
    {
        $query = $trash
            ? Product::onlyTrashed()
            : Product::query();

        return $query
            ->with(['category', 'brand'])
            ->latest();
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if (! $baseSlug) {
            $baseSlug = 'product-' . time();
        }

        $slug = $baseSlug;
        $count = 1;

        while (
            Product::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    private function activeCategories()
    {
        return Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();
    }

    private function activeBrands()
    {
        return Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand_id') && $request->brand_id !== 'all') {
            if ($request->brand_id === 'no_brand') {
                $query->whereNull('brand_id');
            } else {
                $query->where('brand_id', $request->brand_id);
            }
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (bool) $request->status);
        }

        if ($request->filled('product_type') && $request->product_type !== 'all') {
            if ($request->product_type === 'top_sale') {
                $query->where('is_top_sale', true);
            }

            if ($request->product_type === 'featured') {
                $query->where('is_feature', true);
            }

            if ($request->product_type === 'flash_sale') {
                $query->where('is_flash_sale', true);
            }
        }

        if ($request->filled('stock_status') && $request->stock_status !== 'all') {
            if ($request->stock_status === 'in_stock') {
                $query->where('stock', '>', 0);
            }

            if ($request->stock_status === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            }
        }

        if ($request->filled('free_delivery') && $request->free_delivery !== 'all') {
            $query->where('is_free_delivery', (bool) $request->free_delivery);
        }

        return $query;
    }

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(15)->withQueryString();

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Products', 'url' => route('admin.products.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url' => route('admin.products.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.products.partials.table', [
                    'products' => $products,
                    'isTrash' => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.products.index', [
            'products' => $products,
            'categories' => $this->activeCategories(),
            'brands' => $this->activeBrands(),
            'title' => $title,
            'breadcrumb' => $breadcrumb,
            'isTrash' => $isTrash,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin + Employee: View Only
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse(
            $request,
            $this->productQuery(),
            'Product Manage'
        );
    }

    public function show(Product $product)
    {
        $this->adminOrEmployeeOnly();

        $product->load(['category', 'brand']);

        return view('admin.products.show', [
            'product' => $product,
            'title' => 'Product Details',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Products', 'url' => route('admin.products.index')],
                ['text' => 'Product Details', 'url' => route('admin.products.show', $product->id)],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Only: Create / Store / Edit / Update / Delete
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
    {
        $this->adminOnly();

        $data = [
            'product' => null,
            'categories' => $this->activeCategories(),
            'brands' => $this->activeBrands(),
            'isEdit' => false,
            'action' => route('admin.products.store'),
            'title' => 'Product Create',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Products', 'url' => route('admin.products.index')],
                ['text' => 'Create Product', 'url' => route('admin.products.create')],
            ],
        ];

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.products.partials.form', $data)->render(),
            ]);
        }

        return view('admin.products.create', $data);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],

            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'product_code' => ['nullable', 'string', 'max:255', 'unique:products,product_code'],

            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'old_price' => ['nullable', 'integer', 'min:0'],
            'new_price' => ['required', 'integer', 'min:0'],

            'stock' => ['nullable', 'integer', 'min:0'],
            'sold_quantity' => ['nullable', 'integer', 'min:0'],
            'weight_size' => ['nullable', 'string', 'max:255'],

            'short_description' => ['nullable', 'string'],
            'full_description' => ['nullable', 'string'],

            'is_top_sale' => ['nullable', 'boolean'],
            'is_feature' => ['nullable', 'boolean'],
            'is_flash_sale' => ['nullable', 'boolean'],
            'is_free_delivery' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],

            'product_thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'product_gallery' => ['nullable', 'array'],
            'product_gallery.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        return DB::transaction(function () use ($request) {
            $product = Product::create([
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,

                'name' => $request->name,
                'slug' => $request->slug
                    ? Str::slug($request->slug)
                    : $this->generateUniqueSlug($request->name),

                'product_code' => $request->product_code,

                'purchase_price' => $request->purchase_price ?: 0,
                'old_price' => $request->old_price ?: 0,
                'new_price' => $request->new_price,

                'stock' => $request->stock ?: 0,
                'sold_quantity' => $request->sold_quantity ?: 0,
                'weight_size' => $request->weight_size,

                'short_description' => $request->short_description,
                'full_description' => $request->full_description,

                'is_top_sale' => $request->boolean('is_top_sale'),
                'is_feature' => $request->boolean('is_feature'),
                'is_flash_sale' => $request->boolean('is_flash_sale'),
                'is_free_delivery' => $request->boolean('is_free_delivery'),
                'status' => $request->has('status') ? $request->boolean('status') : true,

                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
            ]);

            $this->uploadProductMedia($product, $request);

            if ($request->ajax()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Product created successfully.',
                ]);
            }

            return redirect()
                ->route('admin.products.index')
                ->with('success', 'Product created successfully.');
        });
    }

    public function edit(Request $request, Product $product)
    {
        $this->adminOnly();

        $product->load(['category', 'brand']);

        $data = [
            'product' => $product,
            'categories' => $this->activeCategories(),
            'brands' => $this->activeBrands(),
            'isEdit' => true,
            'action' => route('admin.products.update', $product->id),
            'title' => 'Product Edit',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Products', 'url' => route('admin.products.index')],
                ['text' => 'Edit Product', 'url' => route('admin.products.edit', $product->id)],
            ],
        ];

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.products.partials.form', $data)->render(),
            ]);
        }

        return view('admin.products.edit', $data);
    }

    public function update(Request $request, Product $product)
    {
        $this->adminOnly();

        $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],

            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'product_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'product_code')->ignore($product->id),
            ],

            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'old_price' => ['nullable', 'integer', 'min:0'],
            'new_price' => ['required', 'integer', 'min:0'],

            'stock' => ['nullable', 'integer', 'min:0'],
            'sold_quantity' => ['nullable', 'integer', 'min:0'],
            'weight_size' => ['nullable', 'string', 'max:255'],

            'short_description' => ['nullable', 'string'],
            'full_description' => ['nullable', 'string'],

            'is_top_sale' => ['nullable', 'boolean'],
            'is_feature' => ['nullable', 'boolean'],
            'is_flash_sale' => ['nullable', 'boolean'],
            'is_free_delivery' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],

            'product_thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'product_gallery' => ['nullable', 'array'],
            'product_gallery.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        return DB::transaction(function () use ($request, $product) {
            $product->update([
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,

                'name' => $request->name,
                'slug' => $request->slug
                    ? Str::slug($request->slug)
                    : $this->generateUniqueSlug($request->name, $product->id),

                'product_code' => $request->product_code,

                'purchase_price' => $request->purchase_price ?: 0,
                'old_price' => $request->old_price ?: 0,
                'new_price' => $request->new_price,

                'stock' => $request->stock ?: 0,
                'sold_quantity' => $request->sold_quantity ?: 0,
                'weight_size' => $request->weight_size,

                'short_description' => $request->short_description,
                'full_description' => $request->full_description,

                'is_top_sale' => $request->boolean('is_top_sale'),
                'is_feature' => $request->boolean('is_feature'),
                'is_flash_sale' => $request->boolean('is_flash_sale'),
                'is_free_delivery' => $request->boolean('is_free_delivery'),
                'status' => $request->has('status') ? $request->boolean('status') : false,

                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
            ]);

            $this->uploadProductMedia($product, $request);

            if ($request->ajax()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Product updated successfully.',
                ]);
            }

            return redirect()
                ->route('admin.products.index')
                ->with('success', 'Product updated successfully.');
        });
    }

    public function destroy(Product $product)
    {
        $this->adminOnly();

        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product moved to trash successfully.',
        ]);
    }

    public function trash(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->productQuery(true),
            'Product Trash Bin',
            true
        );
    }

    public function restore($id)
    {
        $this->adminOnly();

        Product::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status' => true,
            'message' => 'Product restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();

        $product = Product::onlyTrashed()->findOrFail($id);

        $product->clearMediaCollection('product_thumbnail');
        $product->clearMediaCollection('product_gallery');

        $product->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Product permanently deleted successfully.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'action' => [
                'required',
                'in:delete,restore,force_delete,active,inactive,top_sale,remove_top_sale,featured,remove_featured,flash_sale,remove_flash_sale,free_delivery,remove_free_delivery',
            ],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->action;
        $ids = $request->ids;

        if ($action === 'delete') {
            Product::whereIn('id', $ids)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Selected products moved to trash.',
            ]);
        }

        if ($action === 'restore') {
            Product::onlyTrashed()->whereIn('id', $ids)->restore();

            return response()->json([
                'status' => true,
                'message' => 'Selected products restored.',
            ]);
        }

        if ($action === 'force_delete') {
            $products = Product::onlyTrashed()->whereIn('id', $ids)->get();

            foreach ($products as $product) {
                $product->clearMediaCollection('product_thumbnail');
                $product->clearMediaCollection('product_gallery');
                $product->forceDelete();
            }

            return response()->json([
                'status' => true,
                'message' => 'Selected products permanently deleted.',
            ]);
        }

        $updateData = match ($action) {
            'active' => ['status' => true],
            'inactive' => ['status' => false],
            'top_sale' => ['is_top_sale' => true],
            'remove_top_sale' => ['is_top_sale' => false],
            'featured' => ['is_feature' => true],
            'remove_featured' => ['is_feature' => false],
            'flash_sale' => ['is_flash_sale' => true],
            'remove_flash_sale' => ['is_flash_sale' => false],
            'free_delivery' => ['is_free_delivery' => true],
            'remove_free_delivery' => ['is_free_delivery' => false],
            default => [],
        };

        if (empty($updateData)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid bulk action.',
            ], 422);
        }

        Product::whereIn('id', $ids)->update($updateData);

        return response()->json([
            'status' => true,
            'message' => 'Selected products updated successfully.',
        ]);
    }

    public function deleteMedia($id)
    {
        $this->adminOnly();

        $media = Media::findOrFail($id);
        $media->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product media deleted successfully.',
        ]);
    }

    private function uploadProductMedia(Product $product, Request $request): void
    {
        if ($request->hasFile('product_thumbnail')) {
            $product->clearMediaCollection('product_thumbnail');

            $product->addMediaFromRequest('product_thumbnail')
                ->toMediaCollection('product_thumbnail');
        }

        if ($request->hasFile('product_gallery')) {
            foreach ($request->file('product_gallery') as $galleryImage) {
                $product->addMedia($galleryImage)
                    ->toMediaCollection('product_gallery');
            }
        }
    }
}