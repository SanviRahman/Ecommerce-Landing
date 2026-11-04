<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\VariantCatalogManager;
use App\Services\Inventory\StorefrontDeferredStock;
use App\Support\P33CatalogAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Audited stock entry point: the SAME variant balance and movement ledger as checkout. */
final class StockManagementController extends Controller
{
    private const PURCHASE_PREFIX = 'Stock Management purchase';

    public function index(Request $request): View
    {
        P33CatalogAdminAccess::assertAllowed();
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $query = trim((string) ($data['q'] ?? ''));

        // One row per product: its hidden default variant is the shared stock
        // authority. Retired multi-SKU data is visible but never made editable.
        $variants = ProductVariant::query()->where('is_default', true)->with('product')
            ->whereHas('product', static fn ($q) => $q->where('inventory_version', 1))
            ->when($query !== '', static function ($q) use ($query): void {
                $escaped = addcslashes($query, '%_\\');
                $q->whereHas('product', static fn ($product) => $product
                    ->where('name', 'like', '%' . $escaped . '%')
                    ->orWhere('product_code', 'like', '%' . $escaped . '%'));
            })
            ->orderBy('product_id')->orderBy('id')->paginate(15)->withQueryString();
        $ids = $variants->getCollection()->pluck('id')->all();

        // Physical stock is held in product_variants.stock. Pending orders
        // reserve packs without deducting them until confirmation.
        $reserved = Schema::hasColumn('orders', 'storefront_stock_state')
            ? DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
                ->whereIn('i.product_variant_id', $ids)
                ->where('o.storefront_stock_state', StorefrontDeferredStock::RESERVED)
                ->whereNull('o.deleted_at')->whereNull('i.deleted_at')
                ->selectRaw('i.product_variant_id, SUM(i.quantity) AS held')
                ->groupBy('i.product_variant_id')->pluck('held', 'product_variant_id')
            : collect();

        // Leatherket-style "In a Stock": received opening inventory plus
        // recorded positive receipts/corrections minus safely reversed
        // purchases. Confirmed orders reduce Available, not Received.
        $received = DB::table('inventory_movements')
            ->whereIn('product_variant_id', $ids)
            ->select('product_variant_id')
            ->selectRaw("SUM(CASE
                WHEN kind = 'opening' AND quantity_delta > 0 THEN quantity_delta
                WHEN kind = 'adjustment' AND quantity_delta > 0 THEN quantity_delta
                WHEN kind = 'adjustment' AND quantity_delta < 0
                    AND reason LIKE ? THEN quantity_delta
                ELSE 0 END) AS received_total", ['Reversed unused Stock Management purchase #%'])
            ->groupBy('product_variant_id')->pluck('received_total', 'product_variant_id');

        // A row-level Delete button reverses ONLY its latest unused purchase.
        // All other rows show a disabled Delete button rather than pretending
        // that a product/inventory ledger can be deleted safely.
        $latestMovementIds = DB::table('inventory_movements')
            ->whereIn('product_variant_id', $ids)
            ->selectRaw('MAX(id) AS latest_id')->groupBy('product_variant_id')
            ->pluck('latest_id');
        $latestMovements = DB::table('inventory_movements')->whereIn('id', $latestMovementIds)
            ->get()->keyBy('product_variant_id');

        // The header purchase action supports all current shared-stock products.
        // Rows beyond the first 500 remain accessible using the search/table.
        $purchaseVariants = ProductVariant::query()->where('is_default', true)
            ->where('option_signature', 'default')->with('product')
            ->whereHas('product', static fn ($q) => $q->where('inventory_version', 1)
                ->where('has_variants', false))
            ->orderBy('product_id')->limit(500)->get();

        $history = DB::table('inventory_movements as m')
            ->join('product_variants as v', 'v.id', '=', 'm.product_variant_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->select('m.*', 'v.sku', 'p.name AS product_name')
            ->orderByDesc('m.id')->limit(35)->get();
        $legacy = Product::query()->where('inventory_version', 0)
            ->orderBy('name')->limit(30)->get(['id', 'name', 'product_code', 'stock']);

        return view('admin.stock.index', compact(
            'variants', 'reserved', 'received', 'latestMovements',
            'purchaseVariants', 'history', 'legacy', 'query'
        ));
    }

    public function purchase(Request $request, ProductVariant $variant, VariantCatalogManager $catalog): RedirectResponse
    {
        P33CatalogAdminAccess::assertAllowed();
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'expected_stock' => ['required', 'integer', 'min:0'],
            'reference' => ['required', 'string', 'min:3', 'max:120'],
        ]);
        $catalog->adjustStock($variant->product, $variant, (int) $data['quantity'],
            (int) $data['expected_stock'], self::PURCHASE_PREFIX . ': ' . trim($data['reference']),
            (int) auth()->id());
        return back()->with('success', 'Purchase received and stock ledger updated.');
    }

    public function setExact(Request $request, ProductVariant $variant, VariantCatalogManager $catalog): RedirectResponse
    {
        P33CatalogAdminAccess::assertAllowed();
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'expected_stock' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:8', 'max:160'],
        ]);
        $catalog->setStockQuantity($variant->product, $variant, (int) $data['quantity'],
            (int) $data['expected_stock'], (int) auth()->id(), trim($data['reason']));
        return back()->with('success', 'Exact stock saved with an audited adjustment.');
    }

    /** Safe delete substitute: reverse ONLY the latest unused purchase with a new movement. */
    public function reversePurchase(int $movement, VariantCatalogManager $catalog): RedirectResponse
    {
        P33CatalogAdminAccess::assertAllowed();
        DB::transaction(function () use ($movement, $catalog): void {
            $entry = DB::table('inventory_movements')->where('id', $movement)->first();
            if (! $entry || $entry->kind !== 'adjustment' || $entry->order_item_id !== null
                || ! str_starts_with((string) $entry->reason, self::PURCHASE_PREFIX . ': ')
                || (int) $entry->quantity_delta <= 0) {
                throw ValidationException::withMessages(['purchase' => 'Only recorded Stock Management purchases can be reversed.']);
            }
            $variant = ProductVariant::query()->whereKey($entry->product_variant_id)->firstOrFail();
            $product = Product::query()->whereKey($variant->product_id)->lockForUpdate()->firstOrFail();
            $variant = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $latest = DB::table('inventory_movements')->where('product_variant_id', $variant->id)
                ->orderByDesc('id')->lockForUpdate()->first();
            if (! $latest || (int) $latest->id !== $movement
                || (int) $variant->stock !== (int) $entry->stock_after) {
                throw ValidationException::withMessages(['purchase' => 'This purchase has later stock activity. It cannot be reversed.']);
            }
            // No physical units may be removed if they are already held by unconfirmed orders.
            $held = Schema::hasColumn('orders', 'storefront_stock_state')
                ? (int) DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
                    ->where('i.product_variant_id', $variant->id)
                    ->where('o.storefront_stock_state', StorefrontDeferredStock::RESERVED)
                    ->whereNull('o.deleted_at')->whereNull('i.deleted_at')->sum('i.quantity')
                : 0;
            if ((int) $variant->stock - (int) $entry->quantity_delta < $held) {
                throw ValidationException::withMessages(['purchase' => 'Reserved orders need this stock; reversal is blocked.']);
            }
            $catalog->adjustStock($product, $variant, -(int) $entry->quantity_delta,
                (int) $entry->stock_after, 'Reversed unused Stock Management purchase #' . $entry->id,
                (int) auth()->id());
        }, 3);
        return back()->with('success', 'Purchase safely reversed; audit history retained.');
    }
}
