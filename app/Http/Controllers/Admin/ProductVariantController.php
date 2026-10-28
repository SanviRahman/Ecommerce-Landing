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
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    public function index(Product $product, VariantCatalogManager $catalog): View
    {
        $this->adminOnly();
        $catalog->assertTestSchema();
        return view('admin.products.variants', [
            'product' => $product->load('category'),
            'options' => ProductOption::query()->whereIn('code', array_keys(VariantCatalogManager::OPTIONS))
                ->where('is_active', true)->with(['values' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')->get(),
            'variants' => ProductVariant::query()->where('product_id', $product->id)
                ->with('optionValues.option')->orderBy('id')->get(),
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
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D'],
            'options' => ['required', 'array:color,size,weight,piece_count'],
            'options.color' => ['nullable', 'integer', 'min:1'],
            'options.size' => ['nullable', 'integer', 'min:1'],
            'options.weight' => ['nullable', 'integer', 'min:1'],
            'options.piece_count' => ['nullable', 'integer', 'min:1'],
            'price_override' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'old_price_override' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $selected = [];
        foreach (VariantCatalogManager::OPTIONS as $code => $name) {
            if (isset($validated['options'][$code]) && $validated['options'][$code] !== null) {
                $selected[$code] = (int) $validated['options'][$code];
            }
        }
        $catalog->createVariant($product, $validated['sku'], $selected,
            array_key_exists('price_override', $validated) ? $validated['price_override'] : null,
            array_key_exists('old_price_override', $validated) ? $validated['old_price_override'] : null,
            $request->boolean('is_active', true));

        return back()->with('success', 'Variant created with zero opening stock. Add physical stock with a reason.');
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
        return back()->with('success', 'Variant inventory approved. This product is now active for the verified local storefront.');
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
