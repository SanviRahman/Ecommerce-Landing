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

        $product->load('variants');
        $options = ProductOption::query()->where('is_active', true)
            ->whereIn('code', \App\Services\Catalog\OptionWiseSelections::CODES)
            ->with(['values' => static fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('id')->get();
        $choices = DB::table('product_option_choices as c')
            ->join('product_option_values as v', 'v.id', '=', 'c.product_option_value_id')
            ->join('product_options as o', 'o.id', '=', 'v.product_option_id')
            ->where('c.product_id', $product->id)
            ->orderBy('o.sort_order')->orderBy('v.sort_order')->orderBy('v.id')
            ->get(['o.code', 'v.id', 'v.label', 'v.is_active', 'o.is_active as group_active']);
        $configuredSelections = [];
        $configuredOptionLabels = [];
        foreach ($choices as $choice) {
            $configuredSelections[$choice->code][] = (int) $choice->id;
            $configuredOptionLabels[$choice->code][] = [
                'label' => (string) $choice->label,
                'active' => (bool) $choice->is_active && (bool) $choice->group_active,
            ];
        }
        $configuredPrices = DB::table('product_piece_prices')
            ->where('product_id', $product->id)->pluck('price', 'product_option_value_id')->all();

        // First setup only: 1 Piece inherits Product Create's New Price.
        // Never overwrite previously saved Piece Count prices or selections.
        $prefilledOnePiecePrice = false;
        if ($configuredPrices === []) {
            $pieceCountOption = $options->firstWhere('code', 'piece_count');
            $onePiece = $pieceCountOption?->values->first(static function ($value): bool {
                foreach ([$value->code, $value->label] as $text) {
                    if (preg_match('/\A(?:1|১)[ _-]*(?:piece|pieces|pcs|pic|পিস)\z/ui', trim((string) $text))) {
                        return true;
                    }
                }
                return false;
            });
            if ($onePiece !== null) {
                $configuredSelections['piece_count'] = [(int) $onePiece->id];
                if ((int) $product->new_price > 0) {
                    $configuredPrices[(int) $onePiece->id] = (int) $product->new_price;
                    $prefilledOnePiecePrice = true;
                }
            }
        }

        // Read-only saved price rows for this product (including values later deactivated).
        $savedPiecePrices = DB::table('product_piece_prices as p')
            ->join('product_option_values as v', 'v.id', '=', 'p.product_option_value_id')
            ->join('product_options as o', 'o.id', '=', 'v.product_option_id')
            ->where('p.product_id', $product->id)
            ->where('o.code', 'piece_count')
            ->orderBy('v.sort_order')->orderBy('v.id')
            ->get(['v.label', 'v.is_active as value_active', 'o.is_active as group_active',
                'p.price', 'p.old_price']);

        // Compact global index: one row per option-wise product, not one row per SKU.
        // The grouped subquery prevents duplicate rows and avoids N+1 pricing queries.
        $priceSummary = DB::table('product_piece_prices')
            ->select('product_id')
            ->selectRaw('COUNT(*) as option_count, MIN(price) as min_price, MAX(price) as max_price')
            ->groupBy('product_id');
        $optionWiseProducts = Product::query()
            ->joinSub($priceSummary, 'price_summary', static function ($join): void {
                $join->on('price_summary.product_id', '=', 'products.id');
            })
            ->select(['products.id', 'products.name', 'products.product_code',
                'products.stock', 'products.status', 'price_summary.option_count',
                'price_summary.min_price', 'price_summary.max_price'])
            ->orderBy('products.name')->orderBy('products.id')
            ->paginate(10, ['*'], 'optionwise_page');

        return view('admin.products.variants', compact(
            'product', 'options', 'configuredSelections', 'configuredOptionLabels',
            'configuredPrices', 'prefilledOnePiecePrice', 'savedPiecePrices', 'optionWiseProducts'
        ));
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
        abort(410, 'The SKU-matrix conversion flow has been retired. Use Option-wise Pricing with shared inventory.');
    }

    /** Old single-SKU creation is deliberately retired. */
    public function store(Request $request, Product $product, VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        abort(410, 'Single-SKU variation creation has been replaced by Option-wise Pricing.');
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
        abort(410, 'The bulk SKU editor has been replaced by Option-wise Pricing.');
    }

    /** Individual SKU prices must not bypass the single Piece Count price. */
    public function update(Request $request, Product $product, ProductVariant $variant,
        VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        abort(410, 'Individual variation editing is retired. Use Option-wise Pricing and Stock Management.');
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
