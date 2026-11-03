<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Services\Catalog\VariantCatalogManager;
use App\Services\Catalog\VariableStorefrontActivation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    /** Existing audited SKU editor: expose it only to authorized admins. */
    public function index(Product $product): View
    {
        $this->adminOnly();

        $product->load('variants.optionValues.option');
        $options = ProductOption::query()->where('is_active', true)
            ->with(['values' => static fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('id')->get();

        // A live product cannot gain a new dimension without being paused.
        if ($product->has_variants && $product->status) {
            $assigned = DB::table('product_option_assignments')
                ->where('product_id', $product->id)->pluck('product_option_id')->all();
            $options = $options->whereIn('id', $assigned)->values();
        }

        // The batch builder only combines dimensions already assigned to this
        // product. Never introduce a new dimension behind a live cart/order.
        $assignedOptionIds = DB::table('product_option_assignments')
            ->where('product_id', $product->id)->pluck('product_option_id')
            ->map(static fn ($id) => (int) $id)->all();
        $matrixOptions = $assignedOptionIds === [] ? $options
            : $options->whereIn('id', $assignedOptionIds)->values();
        $allExisting = ProductVariant::withTrashed()->where('product_id', $product->id)
            ->with('optionValues.option')->get();
        $existingMatrix = $allExisting->filter(static fn ($variant) =>
            $variant->option_signature !== 'default' && $variant->optionValues->isNotEmpty()
        )->map(static function ($variant): array {
            $selected = [];
            foreach ($variant->optionValues as $value) {
                if ($value->option && isset(VariantCatalogManager::OPTIONS[$value->option->code])) {
                    $selected[$value->option->code] = (int) $value->id;
                }
            }
            return ['values' => $selected, 'sku' => $variant->sku, 'archived' => $variant->trashed()];
        })->values()->all();

        return view('admin.products.variants', [
            'product' => $product,
            'variants' => $product->variants,
            'options' => $options,
            'matrixOptions' => $matrixOptions,
            'existingMatrix' => $existingMatrix,
            'remainingVariantSlots' => max(0, 200 - $allExisting->count()),
        ]);
    }

    public function prepare(Product $product, VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        $catalog->prepareSimple($product);
        return back()->with('success', 'Default SKU and opening stock ledger prepared. Verify physical balance.');
    }

    public function convert(Product $product, VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        $catalog->convertToVariable($product);
        return back()->with('success', 'Product converted to variable; add exact option combinations.');
    }

    public function store(Request $request, Product $product, VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        // The guided form may generate a SKU. Existing API/form callers still supply one.
        $quickAdd = $request->boolean('quick_add');
        $validated = $request->validate([
            'quick_add' => ['sometimes', 'boolean'],
            'sku' => [$quickAdd ? 'nullable' : 'required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D'],
            'options' => ['required', 'array:color,size,weight,piece_count'],
            'options.color' => ['nullable', 'integer', 'min:1'],
            'options.size' => ['nullable', 'integer', 'min:1'],
            'options.weight' => ['nullable', 'integer', 'min:1'],
            'options.piece_count' => ['nullable', 'integer', 'min:1'],
            'price_override' => [$quickAdd ? 'required' : 'nullable', 'integer', 'min:' . ($quickAdd ? 1 : 0), 'max:4294967295'],
            'old_price_override' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'stock' => [$quickAdd ? 'required' : 'nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $selected = [];
        foreach (VariantCatalogManager::OPTIONS as $code => $name) {
            if (isset($validated['options'][$code]) && $validated['options'][$code] !== null) {
                $selected[$code] = (int) $validated['options'][$code];
            }
        }
        // An SKU is global and immutable once referenced. A random, product-scoped
        // 64-bit suffix avoids changing business codes or assuming an available counter.
        $enteredSku = trim((string) ($validated['sku'] ?? ''));
        $sku = $enteredSku !== '' ? $enteredSku : 'PV-' . $product->id . '-' . strtoupper(bin2hex(random_bytes(8)));
        DB::transaction(function () use ($catalog, $product, $validated, $selected, $request, $sku, $quickAdd): void {
            $variant = $catalog->createVariant($product, $sku, $selected,
                array_key_exists('price_override', $validated) ? $validated['price_override'] : null,
                array_key_exists('old_price_override', $validated) ? $validated['old_price_override'] : null,
                $request->boolean('is_active', ! $quickAdd));
            $catalog->setStockQuantity($product, $variant, (int) ($validated['stock'] ?? 0),
                0, (int) auth()->id(), 'Initial stock entered while adding new variant');
        }, 3);

        return back()->with('success', 'Variant created. Stock quantity saved exactly as entered.');
    }

    /**
     * Create a deliberately selected set of SKUs in one atomic transaction.
     * Every row has an independently approved price and exact pack stock.
     * Duplicate, archived and racing combinations are rejected by the existing
     * VariantCatalogManager; no historical SKU/order is rewritten.
     */
    public function bulkStore(Request $request, Product $product, VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        $validated = $request->validate([
            'variants' => ['required', 'array', 'min:1', 'max:60'],
            'variants.*.options' => ['required', 'array:color,size,weight,piece_count'],
            'variants.*.options.color' => ['nullable', 'integer', 'min:1'],
            'variants.*.options.size' => ['nullable', 'integer', 'min:1'],
            'variants.*.options.weight' => ['nullable', 'integer', 'min:1'],
            'variants.*.options.piece_count' => ['nullable', 'integer', 'min:1'],
            'variants.*.price_override' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'variants.*.old_price_override' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Require a complete, consistent set of the already assigned dimensions.
        // This check also applies while the product is paused.
        $assigned = DB::table('product_option_assignments as assignments')
            ->join('product_options as options', 'options.id', '=', 'assignments.product_option_id')
            ->where('assignments.product_id', $product->id)->pluck('options.code')->all();
        $required = $assigned;
        sort($required);
        $rowSelections = [];
        foreach ($validated['variants'] as $index => $row) {
            $selected = [];
            foreach (VariantCatalogManager::OPTIONS as $code => $name) {
                if (isset($row['options'][$code]) && $row['options'][$code] !== null) {
                    $selected[$code] = (int) $row['options'][$code];
                }
            }
            $codes = array_keys($selected);
            sort($codes);
            if ($required === [] && $rowSelections === []) {
                $required = $codes;
            }
            if ($codes === [] || $codes !== $required) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'variants' => 'Each combination must select exactly the same active product dimensions.',
                ]);
            }
            $rowSelections[$index] = $selected;
        }

        $count = count($rowSelections);
        DB::transaction(function () use ($catalog, $product, $validated, $rowSelections, $request): void {
            // Catalog service locks the product row for each insert and enforces
            // the total-200 ceiling, signature uniqueness and active value IDs.
            $actor = (int) auth()->id();
            foreach ($validated['variants'] as $index => $row) {
                $sku = 'PV-' . $product->id . '-' . strtoupper(bin2hex(random_bytes(8)));
                $variant = $catalog->createVariant(
                    $product, $sku, $rowSelections[$index],
                    (int) $row['price_override'],
                    isset($row['old_price_override']) ? (int) $row['old_price_override'] : null,
                    $request->boolean('is_active', true)
                );
                $catalog->setStockQuantity($product, $variant, (int) $row['stock'], 0,
                    $actor, 'Initial stock entered in the SKU combination builder');
            }
        }, 3);

        return redirect()->route('admin.products.variants.index', $product->id)
            ->with('success', $count . ' new SKU combinations created with independent prices and stock.');
    }

    public function update(Request $request, Product $product, ProductVariant $variant,
        VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D'],
            'price_override' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'old_price_override' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $catalog->updateVariant($product, $variant, [
            'sku' => $validated['sku'],
            'price_override' => $validated['price_override'] ?? null,
            'old_price_override' => $validated['old_price_override'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        return back()->with('success', 'Variant properties updated. Historic order snapshots unchanged.');
    }

    public function stock(Request $request, Product $product, ProductVariant $variant,
        VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        if ($request->exists('stock')) {
            $data = $request->validate([
                'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
                'expected_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            ]);
            $catalog->setStockQuantity($product, $variant, (int) $data['stock'],
                (int) $data['expected_stock'], (int) auth()->id(),
                'Exact SKU stock set from Variants page');
            return back()->with('success', 'Stock quantity saved exactly as entered.');
        }
        // Backwards-compatible audited delta endpoint for existing integrations.
        $validated = $request->validate([
            'delta' => ['required', 'integer', 'min:-1000000', 'max:1000000', 'not_in:0'],
            'expected_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'reason' => ['required', 'string', 'min:8', 'max:255'],
        ]);
        $catalog->adjustStock($product, $variant, (int) $validated['delta'],
            (int) $validated['expected_stock'], $validated['reason'], (int) auth()->id());
        return back()->with('success', 'Audited stock adjustment saved.');
    }

    /** Explicit local/test-only storefront release. Never silently flip status. */
    public function activate(Product $product, VariableStorefrontActivation $release): RedirectResponse
    {
        $this->adminOnly();
        $release->activate($product);
        return back()->with('success', 'Variant inventory approved for the verified storefront.');
    }

    public function pause(Product $product, VariableStorefrontActivation $release): RedirectResponse
    {
        $this->adminOnly();
        $release->pause($product);
        return back()->with('success', 'Variable product paused. Its cart history and audited SKU stock remain unchanged.');
    }

    private function adminOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
        try {
            app(VariantCatalogManager::class)->assertTestSchema();
        } catch (\RuntimeException $exception) {
            // Never expose staged admin endpoints on a different DB/unfinished schema.
            abort(404, 'Staged variant administration is unavailable on this database.');
        }
    }
}
