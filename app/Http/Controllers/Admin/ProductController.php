<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Services\Catalog\VariantCatalogManager;
use App\Services\Catalog\VariableStorefrontActivation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
        if (
            ! auth()->check()
            || (! auth()->user()->isAdmin() && ! auth()->user()->isEmployee())
        ) {
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

        $slug  = $baseSlug;
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
        return Category::query()->where('status', true)
            ->where(function ($query): void {
                $query->whereNull('parent_id')->orWhereHas('parent', static function ($parent): void {
                    $parent->where('status', true)->whereNull('parent_id');
                });
            })->orderBy('name')->get();
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
        $variantAdminAvailable = $this->variantAdminAvailable();

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Products', 'url' => route('admin.products.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url'  => route('admin.products.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html'   => view('admin.products.partials.table', [
                    'products' => $products,
                    'isTrash'  => $isTrash,
                    'variantAdminAvailable' => $variantAdminAvailable,
                ])->render(),
            ]);
        }

        return view('admin.products.index', [
            'products'   => $products,
            'categories' => $this->activeCategories(),
            'brands'     => $this->activeBrands(),
            'title'      => $title,
            'breadcrumb' => $breadcrumb,
            'isTrash'    => $isTrash,
            'variantAdminAvailable' => $variantAdminAvailable,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin + Employee: View Product List / Details
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

    /** The list and product details use the same verified variant-admin gate. */
    private function variantAdminAvailable(): bool
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            return false;
        }
        try {
            \App\Support\P33CatalogAdminAccess::assertAllowed();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function show(Product $product)
    {
        $this->adminOrEmployeeOnly();

        $product->load(['category', 'brand']);

        return view('admin.products.show', [
            'product'    => $product,
            'variantAdminAvailable' => $this->variantAdminAvailable(),
            'title'      => 'Product Details',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Products', 'url' => route('admin.products.index')],
                ['text' => 'Product Details', 'url' => route('admin.products.show', $product->id)],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Only: Create / Store / Delete / Trash / Restore / Force Delete / Bulk
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
    {
        $this->adminOnly();

        $data = [
            'product'    => null,
            'categories' => $this->activeCategories(),
            'brands'     => $this->activeBrands(),
            'isEdit'     => false,
            'initialVariationOptions' => $this->initialVariationOptions(),
            'initialVariationEnabled' => $this->variantAdminAvailable(),
            'action'     => route('admin.products.store'),
            'title'      => 'Product Create',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Products', 'url' => route('admin.products.index')],
                ['text' => 'Create Product', 'url' => route('admin.products.create')],
            ],
        ];

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html'   => view('admin.products.partials.form', $data)->render(),
            ]);
        }

        return view('admin.products.create', $data);
    }

    /** Active Color, Size, Weight and Piece Count options share one source of truth. */
    private function initialVariationOptions(): array
    {
        try {
            \App\Support\P33CatalogAdminAccess::assertAllowed();

            return ProductOption::query()
                ->whereIn('code', array_keys(VariantCatalogManager::OPTIONS))
                ->where('is_active', true)
                ->with(['values' => static fn ($query) => $query->where('is_active', true)])
                ->get()->keyBy('code')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function store(Request $request, VariantCatalogManager $catalog, VariableStorefrontActivation $release)
    {
        $this->adminOnly();

        $request->validate([
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'brand_id'          => ['nullable', 'integer', 'exists:brands,id'],

            'name'              => ['required', 'string', 'max:255'],
            'slug'              => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'product_code'      => ['nullable', 'string', 'max:255', 'unique:products,product_code'],

            'purchase_price'    => ['nullable', 'integer', 'min:0'],
            'old_price'         => ['nullable', 'integer', 'min:0'],
            'new_price'         => ['required', 'integer', 'min:0', 'max:4294967295'],

            // One Stock input always means one sellable SKU (not a conversion of packs).
            'stock'             => ['required', 'integer', 'min:0', 'max:1000000'],
            'sold_quantity'     => ['nullable', 'integer', 'min:0'],
            'weight_size'       => ['nullable', 'string', 'max:255'],
            'initial_variation' => ['nullable', 'array:color,size,weight,piece_count'],
            'initial_variation.color' => ['nullable', 'integer', 'min:1'],
            'initial_variation.size' => ['nullable', 'integer', 'min:1'],
            'initial_variation.weight' => ['nullable', 'integer', 'min:1'],
            'initial_variation.piece_count' => ['nullable', 'integer', 'min:1'],
            // Do not silently accept the former hard-coded two-pack stock contract.
            'initial_piece_variations' => ['prohibited'],
            'pack_prices' => ['prohibited'],
            'pack_stocks' => ['prohibited'],

            'short_description' => ['nullable', 'string'],
            'full_description'  => ['nullable', 'string'],

            'is_top_sale'       => ['nullable', 'boolean'],
            'is_feature'        => ['nullable', 'boolean'],
            'is_flash_sale'     => ['nullable', 'boolean'],
            'is_free_delivery'  => ['nullable', 'boolean'],
            'status'            => ['nullable', 'boolean'],

            'meta_title'        => ['nullable', 'string', 'max:255'],
            'meta_description'  => ['nullable', 'string'],

            'product_thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'product_gallery'   => ['nullable', 'array'],
            'product_gallery.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $selected = array_filter([
            'color' => $request->input('initial_variation.color'),
            'size' => $request->input('initial_variation.size'),
            'weight' => $request->input('initial_variation.weight'),
            'piece_count' => $request->input('initial_variation.piece_count'),
        ], static fn ($value) => $value !== null && $value !== '');
        $hasNewVariation = $selected !== [];
        // The form always sends 0/1. Preserve legacy API defaults when omitted:
        // simple products default active; new variable products default inactive.
        $requestedActive = $request->exists('status')
            ? $request->boolean('status')
            : ! $hasNewVariation;

        if ($hasNewVariation) {
            $catalog->assertTestSchema();
        }

        return DB::transaction(function () use ($request, $catalog, $release, $selected, $hasNewVariation, $requestedActive) {
            // The legacy Weight / Size string is populated from the selected
            // Weight (or Size) option; no second input is necessary in the UI.
            $weightOrSizeValueId = $selected['weight'] ?? $selected['size'] ?? null;
            $displayWeightOrSize = $weightOrSizeValueId !== null
                ? ProductOptionValue::query()->whereKey((int) $weightOrSizeValueId)->value('label')
                : $request->input('weight_size');
            $product = Product::create([
                'category_id'       => $request->category_id,
                'brand_id'          => $request->brand_id,

                'name'              => $request->name,
                'slug'              => $request->slug
                    ? Str::slug($request->slug)
                    : $this->generateUniqueSlug($request->name),

                'product_code'      => $request->product_code,

                'purchase_price'    => $request->purchase_price ?: 0,
                'old_price'         => $request->old_price ?: 0,
                'new_price'         => $request->new_price,

                'stock'             => $hasNewVariation ? 0 : ($request->stock ?: 0),
                'sold_quantity'     => $request->sold_quantity ?: 0,
                'weight_size'       => $displayWeightOrSize,

                'short_description' => $request->short_description,
                'full_description'  => $request->full_description,

                'is_top_sale'       => $request->boolean('is_top_sale'),
                'is_feature'        => $request->boolean('is_feature'),
                'is_flash_sale'     => $request->boolean('is_flash_sale'),
                'is_free_delivery'  => $request->boolean('is_free_delivery'),
                // A variable product is released only after its audited SKU,
                // price and checkout readiness have all been validated.
                'status'            => $hasNewVariation ? false : $requestedActive,

                'meta_title'        => $request->meta_title,
                'meta_description'  => $request->meta_description,
            ]);

            // All SKU opening movements and stock entries stay in the SAME
            // transaction as product creation; no order or checkout writer changes.
            if ($hasNewVariation) {
                $catalog->prepareSimple($product);
                $catalog->convertToVariable($product);
                $variant = $catalog->createVariant(
                    $product,
                    'P' . $product->id . '-V' . implode('-', array_map('intval', array_values($selected))),
                    array_map('intval', $selected),
                    null, null, true
                );
                $catalog->setStockQuantity(
                    $product, $variant, (int) $request->input('stock'), 0,
                    (int) auth()->id(), 'Opening SKU stock entered during product creation'
                );
                if ($requestedActive) {
                    // Atomic: if activation fails, do not save a product whose
                    // form said Active. No untracked stock or half-created SKUs.
                    $release->activate($product);
                }
            }
            $this->uploadProductMedia($product, $request);

            if ($request->ajax()) {
                return response()->json([
                    'status'  => true,
                    'message' => $hasNewVariation
                        ? ($requestedActive ? 'Product created and activated.' : 'Product created as inactive.')
                        : 'Product created successfully.',
                ]);
            }

            return redirect()->route('admin.products.index')
                ->with('success', $hasNewVariation
                    ? ($requestedActive ? 'Product created and activated.' : 'Product created as inactive.')
                    : 'Product created successfully.');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Admin + Employee: Edit / Update Product
    |--------------------------------------------------------------------------
    | Employee can edit product information and media.
    | Employee cannot create, delete, trash, restore, force delete, or bulk delete.
    |--------------------------------------------------------------------------
    */
    public function edit(Request $request, Product $product)
    {
        /*
         * Product modification is administrator-only.
         */
        $this->adminOnly();

        $product->load(['category', 'brand']);

        $managedVariants = $this->variantAdminAvailable() && (int) $product->inventory_version === 1
            ? ProductVariant::query()->where('product_id', $product->id)
                ->when($product->has_variants, fn ($query) => $query->where('option_signature', '!=', 'default'))
                ->with('optionValues.option')->orderBy('id')->get()
            : collect();

        $data = [
            'product'    => $product,
            'categories' => $this->activeCategories(),
            'brands'     => $this->activeBrands(),
            'isEdit'     => true,
            'managedVariants' => $managedVariants,
            // Create and Edit use the same active values from Variant Options.
            'initialVariationOptions' => $this->initialVariationOptions(),
            'initialVariationEnabled' => $this->variantAdminAvailable(),
            // Preserve an inactive value's label if an older SKU still references it.
            'referencedVariationValues' => $managedVariants->flatMap(
                fn ($variant) => $variant->optionValues
            )->filter(fn ($value) => $value->option !== null)
                ->unique('id')->groupBy(fn ($value) => $value->option->code),
            'action'     => route('admin.products.update', $product->id),
            'title'      => 'Product Edit',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Products', 'url' => route('admin.products.index')],
                ['text' => 'Edit Product', 'url' => route('admin.products.edit', $product->id)],
            ],
        ];

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html'   => view('admin.products.partials.form', $data)->render(),
            ]);
        }

        return view('admin.products.edit', $data);
    }

    public function update(Request $request, Product $product, VariantCatalogManager $catalog, VariableStorefrontActivation $release)
    {
        /*
         * Blocks direct or manipulated employee update requests.
         */
        $this->adminOnly();

        $request->validate([
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'brand_id'          => ['nullable', 'integer', 'exists:brands,id'],

            'name'              => ['required', 'string', 'max:255'],
            'slug'              => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product->id),
            ],
            'product_code'      => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'product_code')->ignore($product->id),
            ],

            'purchase_price'    => ['nullable', 'integer', 'min:0'],
            'old_price'         => ['nullable', 'integer', 'min:0'],
            'new_price'         => ['required', 'integer', 'min:0'],

            'stock'             => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'stock_variant_id' => ['nullable', 'integer', 'min:1'],
            'expected_variant_stock' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'edit_variation' => ['nullable', 'array:color,size,weight,piece_count'],
            'edit_variation.color' => ['nullable', 'integer', 'min:1'],
            'edit_variation.size' => ['nullable', 'integer', 'min:1'],
            'edit_variation.weight' => ['nullable', 'integer', 'min:1'],
            'edit_variation.piece_count' => ['nullable', 'integer', 'min:1'],
            'expected_variation_signature' => ['nullable', 'string', 'max:191'],
            'sold_quantity'     => ['nullable', 'integer', 'min:0'],
            'weight_size'       => ['nullable', 'string', 'max:255'],
            'variant_stocks' => ['nullable', 'array'],
            'variant_stocks.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'expected_variant_stocks' => ['nullable', 'array'],
            'expected_variant_stocks.*' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'variant_prices' => ['nullable', 'array'],
            'variant_prices.*' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'expected_variant_prices' => ['nullable', 'array'],
            'expected_variant_prices.*' => ['required', 'integer', 'min:0', 'max:4294967295'],

            'short_description' => ['nullable', 'string'],
            'full_description'  => ['nullable', 'string'],

            'is_top_sale'       => ['nullable', 'boolean'],
            'is_feature'        => ['nullable', 'boolean'],
            'is_flash_sale'     => ['nullable', 'boolean'],
            'is_free_delivery'  => ['nullable', 'boolean'],
            'status'            => ['nullable', 'boolean'],

            'meta_title'        => ['nullable', 'string', 'max:255'],
            'meta_description'  => ['nullable', 'string'],

            'product_thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'product_gallery'   => ['nullable', 'array'],
            'product_gallery.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        return DB::transaction(function () use ($request, $product, $catalog, $release) {
            // Managed stock is variant-backed. The generic Product Edit form must
            // never overwrite a newer stock balance from a checkout or adjustment.
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $managed = (int) ($locked->inventory_version ?? 0) === 1;
            $originalBasePrice = (int) $locked->new_price;
            $wasActive = (bool) $locked->status;
            $requestedActive = $request->boolean('status');
            // Parent stock is a sum of SKUs. Never overwrite that sum directly.
            // Form submits exact per-SKU stock and an expected snapshot instead.
            $variantStocks = (array) $request->input('variant_stocks', []);
            $expectedStocks = (array) $request->input('expected_variant_stocks', []);
            $variantPrices = (array) $request->input('variant_prices', []);
            $expectedPrices = (array) $request->input('expected_variant_prices', []);
            $stockVariantId = $request->input('stock_variant_id');
            if ($request->has('edit_variation') && (! $managed || ! $locked->has_variants
                || ! $stockVariantId || ! $request->filled('expected_variation_signature'))) {
                throw ValidationException::withMessages([
                    'edit_variation' => 'Choose an existing managed SKU before editing its Color, Size, Weight or Piece Count.',
                ]);
            }
            if ($stockVariantId !== null && $stockVariantId !== '') {
                if (! $managed || $request->input('stock') === null
                    || $request->input('expected_variant_stock') === null
                    || $variantStocks !== []) {
                    throw ValidationException::withMessages([
                        'stock' => 'Select a managed SKU and enter its current Stock quantity. Reload the product if needed.',
                    ]);
                }
            }
            if ($variantStocks !== [] || $variantPrices !== []) {
                if (! $managed) {
                    throw ValidationException::withMessages(['variant_stocks' => 'This product has no managed SKU inventory.']);
                }
                $catalog->assertTestSchema();
                // Price rows include a price snapshot for every SKU; the form only
                // sends the stock snapshot for the SELECTED stock SKU. Requiring
                // expected_variant_stocks[] for price-only rows prevents every
                // managed multi-SKU product from being edited.
                foreach (['variant_stocks' => [$variantStocks, $expectedStocks],
                          'variant_prices' => [$variantPrices, $expectedPrices]] as $field => [$values, $expected]) {
                    foreach (array_keys($values) as $id) {
                        if (! ctype_digit((string) $id) || (int) $id < 1) {
                            throw ValidationException::withMessages([$field => 'Invalid SKU identifier.']);
                        }
                        if (! array_key_exists($id, $expected)) {
                            throw ValidationException::withMessages([$field => 'Reload the product before editing SKU stock and prices.']);
                        }
                    }
                }
            }
            $locked->update([
                'category_id'       => $request->category_id,
                'brand_id'          => $request->brand_id,

                'name'              => $request->name,
                'slug'              => $request->slug
                    ? Str::slug($request->slug)
                    : $this->generateUniqueSlug($request->name, $product->id),

                'product_code'      => $request->product_code,

                'purchase_price'    => $request->purchase_price ?: 0,
                'old_price'         => $request->old_price ?: 0,
                'new_price'         => $request->new_price,

                'stock'             => $managed ? $locked->stock : ($request->stock ?: 0),
                'sold_quantity'     => $request->sold_quantity ?: 0,
                'weight_size'       => $request->input('weight_size', $locked->weight_size),

                'short_description' => $request->short_description,
                'full_description'  => $request->full_description,

                'is_top_sale'       => $request->boolean('is_top_sale'),
                'is_feature'        => $request->boolean('is_feature'),
                'is_flash_sale'     => $request->boolean('is_flash_sale'),
                'is_free_delivery'  => $request->boolean('is_free_delivery'),
                // Inactive managed products remain paused until validated below.
                'status'            => $managed && $locked->has_variants && ! $wasActive
                    ? false : $requestedActive,

                'meta_title'        => $request->meta_title,
                'meta_description'  => $request->meta_description,
            ]);

            // Update the selected SKU's combination before price/stock and release
            // validation. Do not touch order history, inventory balances or SKU IDs.
            if ($request->has('edit_variation')) {
                $selectedVariant = ProductVariant::query()->where('product_id', $locked->id)
                    ->whereKey((int) $stockVariantId)->lockForUpdate()->first();
                if (! $selectedVariant || $selectedVariant->option_signature === 'default') {
                    throw ValidationException::withMessages([
                        'edit_variation' => 'The selected SKU no longer exists. Reload Product Edit.',
                    ]);
                }
                $selectedOptions = array_filter((array) $request->input('edit_variation'),
                    static fn ($value) => $value !== null && $value !== '');
                $changed = $catalog->updateVariantOptionValues(
                    $locked, $selectedVariant, array_map('intval', $selectedOptions),
                    (string) $request->input('expected_variation_signature')
                );
                if ($changed && ProductVariant::query()->where('product_id', $locked->id)
                    ->where('option_signature', '!=', 'default')->count() === 1) {
                    // Legacy display string is meaningful only for a single combination.
                    $weightOrSizeId = $selectedOptions['weight'] ?? $selectedOptions['size'] ?? null;
                    $locked->update(['weight_size' => $weightOrSizeId
                        ? ProductOptionValue::query()->whereKey((int) $weightOrSizeId)->value('label')
                        : null]);
                }
            }

            // Update only SKUs of THIS product (never inventory of another item).
            // The existing VariantCatalogManager writes every stock adjustment
            // with an actor, before/after value, and movement reason.
            if ($variantStocks !== [] || $variantPrices !== []) {
                $ids = array_unique(array_merge(array_keys($variantStocks), array_keys($variantPrices)));
                sort($ids, SORT_NUMERIC);
                foreach ($ids as $id) {
                    $variant = ProductVariant::query()->where('product_id', $locked->id)
                        ->whereKey((int) $id)->lockForUpdate()->first();
                    if (! $variant || ($locked->has_variants && $variant->option_signature === 'default')) {
                        throw ValidationException::withMessages(['variant_stocks' => 'Invalid or retired SKU for this product.']);
                    }
                    if (array_key_exists($id, $variantPrices)) {
                        // Use the base price from when this form was opened; a
                        // base-price edit in this same request is not a stale SKU.
                        $current = (int) ($variant->price_override ?? $originalBasePrice);
                        if ($current !== (int) $expectedPrices[$id]) {
                            throw ValidationException::withMessages(['variant_prices' => 'Variant price was updated elsewhere. Reload before saving.']);
                        }
                        if ((int) $variantPrices[$id] !== $current) {
                            if ($variant->is_default || $variant->option_signature === 'default') {
                                throw ValidationException::withMessages(['variant_prices' => 'Edit the base product price for the default SKU.']);
                            }
                            $catalog->updateVariant($locked, $variant, [
                                'sku' => $variant->sku,
                                'price_override' => (int) $variantPrices[$id],
                                'old_price_override' => $variant->old_price_override,
                                'is_active' => (bool) $variant->is_active,
                            ]);
                        }
                    }
                    if (array_key_exists($id, $variantStocks)) {
                        $catalog->setStockQuantity($locked, $variant,
                            (int) $variantStocks[$id], (int) $expectedStocks[$id],
                            (int) auth()->id(), 'Exact SKU stock set from Product Edit');
                    }
                }
                $locked->refresh();
            }

            // New simple UI submits exactly one Stock field. Existing multi-SKU
            // products can choose which SKU to edit without showing stock per row.
            if ($stockVariantId !== null && $stockVariantId !== '') {
                $variant = ProductVariant::query()->where('product_id', $locked->id)
                    ->whereKey((int) $stockVariantId)->lockForUpdate()->first();
                if (! $variant || ($locked->has_variants && $variant->option_signature === 'default')) {
                    throw ValidationException::withMessages(['stock_variant_id' => 'Select a valid SKU belonging to this product.']);
                }
                $catalog->setStockQuantity(
                    $locked, $variant, (int) $request->input('stock'),
                    (int) $request->input('expected_variant_stock'), (int) auth()->id(),
                    'Exact SKU stock set from the single Product Edit Stock field'
                );
                $locked->refresh();
            }

            // The Product form's Active switch is the ONLY admin release action.
            // Reuse the original fail-closed validation rather than bypassing
            // option, ledger, category, price or checkout requirements.
            if ($managed && $locked->has_variants && $requestedActive) {
                if ($wasActive) {
                    $release->verifyActive($locked);
                } else {
                    $release->activate($locked);
                }
            }

            $product->refresh();
            $this->uploadProductMedia($product, $request);

            if ($request->ajax()) {
                return response()->json([
                    'status'  => true,
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
            'status'  => true,
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
            'status'  => true,
            'message' => 'Product restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();

        $product = Product::onlyTrashed()->findOrFail($id);
        if (Schema::hasTable('product_variants')
            && ProductVariant::withTrashed()->where('product_id', $product->id)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Product has variant inventory/history. Permanent deletion is blocked.',
            ], 422);
        }

        $product->clearMediaCollection('product_thumbnail');
        $product->clearMediaCollection('product_gallery');

        $product->forceDelete();

        return response()->json([
            'status'  => true,
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
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
        ]);

        $action = $request->action;
        $ids    = $request->ids;

        if ($action === 'delete') {
            Product::whereIn('id', $ids)->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Selected products moved to trash.',
            ]);
        }

        if ($action === 'restore') {
            Product::onlyTrashed()->whereIn('id', $ids)->restore();

            return response()->json([
                'status'  => true,
                'message' => 'Selected products restored.',
            ]);
        }

        if ($action === 'force_delete') {
            $products = Product::onlyTrashed()->whereIn('id', $ids)->get();

            foreach ($products as $product) {
                if (Schema::hasTable('product_variants')
                    && ProductVariant::withTrashed()->where('product_id', $product->id)->exists()) {
                    continue; // Preserve variant FK, stock ledger and ordered-SKU history.
                }
                $product->clearMediaCollection('product_thumbnail');
                $product->clearMediaCollection('product_gallery');
                $product->forceDelete();
            }

            return response()->json([
                'status'  => true,
                'message' => 'Selected products permanently deleted.',
            ]);
        }

        if ($action === 'active' && Schema::hasColumn('products', 'has_variants')
            && Product::whereIn('id', $ids)->where('has_variants', true)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'To activate a variable product, open Product Edit and turn Active on. Each product must pass the existing catalog safety checks.',
            ], 422);
        }

        $updateData = match ($action) {
            'active'               => ['status' => true],
            'inactive'             => ['status' => false],
            'top_sale'             => ['is_top_sale' => true],
            'remove_top_sale'      => ['is_top_sale' => false],
            'featured'             => ['is_feature' => true],
            'remove_featured'      => ['is_feature' => false],
            'flash_sale'           => ['is_flash_sale' => true],
            'remove_flash_sale'    => ['is_flash_sale' => false],
            'free_delivery'        => ['is_free_delivery' => true],
            'remove_free_delivery' => ['is_free_delivery' => false],
            default                => [],
        };

        if (empty($updateData)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid bulk action.',
            ], 422);
        }

        Product::whereIn('id', $ids)->update($updateData);

        return response()->json([
            'status'  => true,
            'message' => 'Selected products updated successfully.',
        ]);
    }

    public function deleteMedia($id)
    {
        $this->adminOrEmployeeOnly();

        $media = Media::findOrFail($id);

        if ($media->model_type !== Product::class) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid product media.',
            ], 403);
        }

        if (! in_array($media->collection_name, ['product_gallery', 'product_thumbnail'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid media collection.',
            ], 422);
        }

        $media->delete();

        return response()->json([
            'status'   => true,
            'message'  => 'Product media deleted successfully.',
            'media_id' => $id,
        ]);
    }

    private const PRODUCT_MEDIA_DISK = 'public';

    private function uploadProductMedia(Product $product, Request $request): void
    {
        $product->refresh();

        $pathSlug = $this->productMediaPathSlug($product);

        if ($request->hasFile('product_thumbnail')) {
            $thumbnail = $request->file('product_thumbnail');

            if ($thumbnail instanceof UploadedFile && $thumbnail->isValid()) {
                $product->clearMediaCollection('product_thumbnail');
                $this->ensureProductMediaDirectory($pathSlug, 'thumbnail');

                $media = $product->addMedia($thumbnail)
                    ->usingName($product->name . ' Thumbnail')
                    ->usingFileName(
                        $this->productThumbnailFileName($product, $thumbnail)
                    )
                    ->withCustomProperties(
                        $this->productMediaCustomProperties(
                            $pathSlug,
                            'thumbnail'
                        )
                    )
                    ->toMediaCollection(
                        'product_thumbnail',
                        self::PRODUCT_MEDIA_DISK
                    );

                $this->assertProductMediaStored($media);
            }
        }

        if ($request->hasFile('product_gallery')) {
            $this->ensureProductMediaDirectory($pathSlug, 'gallery');

            $gallerySequence = (int) $product->media()
                ->where('collection_name', 'product_gallery')
                ->count();

            foreach ((array) $request->file('product_gallery', []) as $galleryImage) {
                if (! $galleryImage instanceof UploadedFile || ! $galleryImage->isValid()) {
                    continue;
                }

                $gallerySequence++;

                $media = $product->addMedia($galleryImage)
                    ->usingName(
                        $product->name . ' Gallery ' . $gallerySequence
                    )
                    ->usingFileName(
                        $this->productGalleryFileName(
                            $product,
                            $galleryImage,
                            $gallerySequence
                        )
                    )
                    ->withCustomProperties(
                        $this->productMediaCustomProperties(
                            $pathSlug,
                            'gallery'
                        )
                    )
                    ->toMediaCollection(
                        'product_gallery',
                        self::PRODUCT_MEDIA_DISK
                    );

                $this->assertProductMediaStored($media);
            }
        }

        $product->unsetRelation('media');
    }

    private function ensureProductMediaDirectory(
        string $pathSlug,
        string $folder
    ): void {
        $directory = sprintf(
            'products/%s/%s',
            trim($pathSlug, '/'),
            trim($folder, '/')
        );

        $disk = Storage::disk(self::PRODUCT_MEDIA_DISK);

        if (! $disk->exists($directory) && ! $disk->makeDirectory($directory)) {
            throw new \RuntimeException(
                'Unable to create the product media directory: ' . $directory
            );
        }
    }

    private function assertProductMediaStored(Media $media): void
    {
        $relativePath = $media->getPathRelativeToRoot();

        if (! Storage::disk($media->disk)->exists($relativePath)) {
            throw new \RuntimeException(
                sprintf(
                    'Product image upload failed. Expected file was not found on disk [%s] at [%s].',
                    $media->disk,
                    $relativePath
                )
            );
        }
    }

    private function productMediaPathSlug(Product $product): string
    {
        $slug = Str::slug((string) ($product->slug ?: $product->name));

        if ($slug !== '') {
            return $slug;
        }

        return 'product-' . (int) $product->getKey();
    }

    private function productThumbnailFileName(
        Product $product,
        UploadedFile $file
    ): string {
        return sprintf(
            '%s-thumbnail.%s',
            $this->productMediaPathSlug($product),
            $this->uploadedImageExtension($file)
        );
    }

    private function productGalleryFileName(
        Product $product,
        UploadedFile $file,
        int $sequence
    ): string {
        return sprintf(
            '%s-gallery-%s-%s.%s',
            $this->productMediaPathSlug($product),
            str_pad((string) max(1, $sequence), 2, '0', STR_PAD_LEFT),
            Str::lower(Str::random(8)),
            $this->uploadedImageExtension($file)
        );
    }

    private function uploadedImageExtension(UploadedFile $file): string
    {
        $extension = strtolower((string) (
            $file->extension()
            ?: $file->getClientOriginalExtension()
            ?: 'jpg'
        ));

        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'jpg';

        return $extension;
    }

    private function productMediaCustomProperties(
        string $pathSlug,
        string $folder
    ): array {
        return [
            'product_named_path'   => true,
            'product_path_slug'    => $pathSlug,
            'product_media_folder' => $folder,
        ];
    }

}