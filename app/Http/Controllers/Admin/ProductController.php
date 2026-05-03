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
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function productQuery(bool $trash = false): Builder
    {
        $query = $trash
            ? Product::onlyTrashed()
            : Product::query();

        return $query->with(['category', 'brand'])->latest();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('weight_size', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('full_description', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (bool) $request->status);
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

        return $query;
    }

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);

        $products = $query->paginate(10);

        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

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
            'categories' => $categories,
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
            $this->productQuery(),
            'Product Management'
        );
    }

    public function create(Request $request)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()
                ->route('admin.products.index')
                ->with('error', 'Please use the Add Product button.');
        }

        $product = null;
        $isEdit = false;
        $action = route('admin.products.store');

        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'html' => view('admin.products.partials.form', compact(
                'product',
                'isEdit',
                'action',
                'categories',
                'brands'
            ))->render(),
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'product_code' => ['required', 'string', 'max:255', 'unique:products,product_code'],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'old_price' => ['nullable', 'integer', 'min:0'],
            'new_price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'weight_size' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'full_description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        return DB::transaction(function () use ($request) {
            $product = Product::create([
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'name' => $request->name,
                'slug' => $request->slug ? Str::slug($request->slug) : Str::slug($request->name) . '-' . time(),
                'product_code' => $request->product_code,
                'purchase_price' => $request->purchase_price,
                'old_price' => $request->old_price,
                'new_price' => $request->new_price,
                'stock' => $request->stock,
                'sold_quantity' => 0,
                'weight_size' => $request->weight_size,
                'short_description' => $request->short_description,
                'full_description' => $request->full_description,
                'is_top_sale' => $request->has('is_top_sale'),
                'is_feature' => $request->has('is_feature'),
                'is_flash_sale' => $request->has('is_flash_sale'),
                'status' => $request->has('status'),
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
            ]);

            if ($request->hasFile('thumbnail')) {
                $product->addMediaFromRequest('thumbnail')->toMediaCollection('product_thumbnail');
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $galleryImage) {
                    $product->addMedia($galleryImage)->toMediaCollection('product_gallery');
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Product created successfully.',
            ]);
        });
    }

    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'campaigns']);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Products', 'url' => route('admin.products.index')],
            ['text' => 'Product Details', 'url' => route('admin.products.show', $product->id)],
        ];

        return view('admin.products.show', [
            'product' => $product,
            'title' => 'Product Details',
            'breadcrumb' => $breadcrumb,
        ]);
    }

    public function edit(Request $request, Product $product)
    {
        $this->adminOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.products.index');
        }

        $isEdit = true;
        $action = route('admin.products.update', $product->id);

        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'html' => view('admin.products.partials.form', compact(
                'product',
                'isEdit',
                'action',
                'categories',
                'brands'
            ))->render(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->adminOnly();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'product_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'product_code')->ignore($product->id),
            ],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'old_price' => ['nullable', 'integer', 'min:0'],
            'new_price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'weight_size' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'full_description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'gallery.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        return DB::transaction(function () use ($request, $product) {
            $product->update([
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'name' => $request->name,
                'slug' => $request->slug ? Str::slug($request->slug) : Str::slug($request->name) . '-' . $product->id,
                'product_code' => $request->product_code,
                'purchase_price' => $request->purchase_price,
                'old_price' => $request->old_price,
                'new_price' => $request->new_price,
                'stock' => $request->stock,
                'weight_size' => $request->weight_size,
                'short_description' => $request->short_description,
                'full_description' => $request->full_description,
                'is_top_sale' => $request->has('is_top_sale'),
                'is_feature' => $request->has('is_feature'),
                'is_flash_sale' => $request->has('is_flash_sale'),
                'status' => $request->has('status'),
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
            ]);

            if ($request->hasFile('thumbnail')) {
                $product->clearMediaCollection('product_thumbnail');
                $product->addMediaFromRequest('thumbnail')->toMediaCollection('product_thumbnail');
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $galleryImage) {
                    $product->addMedia($galleryImage)->toMediaCollection('product_gallery');
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully.',
            ]);
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
            'action' => ['required', 'in:delete,restore,force_delete,active,inactive,top_sale,remove_top_sale,featured,remove_featured,flash_sale,remove_flash_sale'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $request->ids;
        $action = $request->action;

        if ($action === 'delete') {
            Product::whereIn('id', $ids)->delete();
            return response()->json(['status' => true, 'message' => 'Selected products moved to trash.']);
        }

        if ($action === 'restore') {
            Product::onlyTrashed()->whereIn('id', $ids)->restore();
            return response()->json(['status' => true, 'message' => 'Selected products restored.']);
        }

        if ($action === 'force_delete') {
            $products = Product::onlyTrashed()->whereIn('id', $ids)->get();

            foreach ($products as $product) {
                $product->clearMediaCollection('product_thumbnail');
                $product->clearMediaCollection('product_gallery');
                $product->forceDelete();
            }

            return response()->json(['status' => true, 'message' => 'Selected products permanently deleted.']);
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
            default => [],
        };

        if (! empty($updateData)) {
            Product::whereIn('id', $ids)->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Selected products updated successfully.',
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
            'message' => 'Product media deleted successfully.',
        ]);
    }
}