<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Services\CampaignVariantCatalog;
use App\Services\Catalog\OptionWiseSelections;
use App\Services\Storefront\PublicCatalog;
use App\Support\ExactBdt;
use App\Support\VariantSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Campaign orders containing SKU choices use the existing inventory_movements
 * ledger/variant authority. This operation MUST run inside order creation's
 * transaction, so an invalid or oversold selection rolls back the whole order.
 * Legacy simple-only campaign orders continue using ManagedOrderWriter.
 */
final class CampaignOrderInventoryAllocator
{
    public function allocate(int $orderId, bool $deferUntilConfirmed = false): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Campaign stock allocation requires an order transaction.');
        }

        $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
        if (! $order || $order->deleted_at !== null || $order->campaign_id === null
            || $order->created_via !== 'frontend' || (int) $order->inventory_version !== 0
            || ! in_array($order->order_status, ['pending', 'processing', 'confirmed'], true)) {
            throw ValidationException::withMessages(['products' => 'Campaign order inventory is not eligible for allocation.']);
        }

        $items = DB::table('order_items')->where('order_id', $orderId)->whereNull('deleted_at')
            ->orderBy('id')->lockForUpdate()->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['products' => 'Select at least one campaign product.']);
        }

        // Lock products before variants consistently with the existing allocator.
        $productIds = $items->pluck('product_id')->map(static fn ($v) => (int) $v)->unique()->sort()->values()->all();
        $products = Product::query()->whereIn('id', $productIds)->orderBy('id')
            ->lockForUpdate()->with('variants.optionValues.option')->get()->keyBy('id');
        $campaignPivots = DB::table('campaign_product')->where('campaign_id', $order->campaign_id)
            ->whereIn('product_id', $productIds)->get()->keyBy('product_id');
        if ($products->count() !== count($productIds) || $campaignPivots->count() !== count($productIds)) {
            throw ValidationException::withMessages(['products' => 'An order product is no longer in this campaign.']);
        }
        foreach ($products as $product) {
            if (! $product->status || $product->trashed() || (int) $product->inventory_version !== 1) {
                throw ValidationException::withMessages(['products' => 'A product is inactive or needs inventory setup.']);
            }
        }

        $skuByItem = [];
        foreach ($items as $item) {
            $product = $products->get((int) $item->product_id);
            $selectedId = (int) ($item->product_variant_id ?? 0);
            if (! $product->has_variants) {
                $default = $product->variants->first(static fn ($variant) => $variant->is_default && ! $variant->trashed());
                $selectedId = (int) ($default?->id ?? 0);
            }
            if ($selectedId < 1) {
                throw ValidationException::withMessages(['products' => 'A selected product is missing its inventory SKU.']);
            }
            $skuByItem[(int) $item->id] = $selectedId;
        }

        $variantIds = array_values(array_unique(array_values($skuByItem)));
        sort($variantIds, SORT_NUMERIC);
        $variants = DB::table('product_variants')->whereIn('id', $variantIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($variants->count() !== count($variantIds)) {
            throw ValidationException::withMessages(['products' => 'A selected variation no longer exists.']);
        }

        $catalog = app(PublicCatalog::class);
        $eligible = [];
        foreach ($products as $product) {
            if ($product->has_variants) {
                $eligible[(int) $product->id] = $catalog->eligibleVariants($product)
                    ->pluck('id')->map(static fn ($id) => (int) $id)->all();
            }
        }

        $subtotal = 0;
        $needed = [];
        $approved = [];
        foreach ($items as $item) {
            $product = $products->get((int) $item->product_id);
            $variantId = $skuByItem[(int) $item->id];
            $variant = $variants->get($variantId);
            $qty = (int) $item->quantity;
            if ((int) $variant->product_id !== (int) $product->id || ! $variant->is_active
                || $variant->deleted_at !== null || $qty < 1 || $qty > 1000
                || (int) $item->inventory_allocated_quantity !== 0
                || DB::table('inventory_movements')->where('operation_key', 'allocate:v1:item:' . $item->id)->exists()) {
                throw ValidationException::withMessages(['products' => 'The selected variation is invalid or already allocated.']);
            }
            if ($product->has_variants) {
                if ($variant->is_default || ! in_array($variantId, $eligible[(int) $product->id], true)) {
                    throw ValidationException::withMessages(['products' => 'Choose an active, complete variation for this campaign product.']);
                }
                $selected = DB::table('product_variant_values')->where('product_variant_id', $variantId)
                    ->pluck('product_option_value_id', 'product_option_id')->map(static fn ($v) => (int) $v)->all();
                if ($selected === [] || VariantSignature::fromOptionValues($selected) !== $variant->option_signature) {
                    throw ValidationException::withMessages(['products' => 'The selected variation options have changed.']);
                }
            } else {
                if (! $variant->is_default || $variant->option_signature !== 'default') {
                    throw ValidationException::withMessages(['products' => 'Invalid shared inventory identity.']);
                }
                $selected = [];
            }
            $optionWise = app(OptionWiseSelections::class);
            if ($optionWise->configured($product)) {
                $selected = json_decode((string)($item->variant_options ?? '{}'), true) ?: [];
                // OptionWise quotes are in whole BDT; every stored order monetary
                // field is compared here in minor units (poisha). Previously the
                // unconverted quote triggered a false price-change rollback.
                $unit = ExactBdt::fromDecimal((string) $optionWise->quoteSnapshot($product, $selected)['price']);
            } else {
                $unit = ExactBdt::fromDecimal((string) CampaignVariantCatalog::resolvedUnitPrice(
                    (int)$product->new_price,
                    (int)$campaignPivots->get($product->id)->campaign_price,
                    $variant->price_override === null ? null : (int)$variant->price_override,
                    (bool)$product->has_variants
                ));
            }
            $line = ExactBdt::multiply($unit, $qty);
            if ($unit < 1 || ExactBdt::fromDecimal((string) $item->unit_price) !== $unit
                || ExactBdt::fromDecimal((string) $item->total_price) !== $line
                || ExactBdt::fromDecimal((string) ($item->discount_amount ?? '0')) !== 0) {
                throw ValidationException::withMessages(['products' => 'A variation price changed; refresh and review your order.']);
            }
            $subtotal = ExactBdt::add($subtotal, $line);
            $needed[$variantId] = ($needed[$variantId] ?? 0) + $qty;
            $approved[] = [(int) $item->id, $variantId, $qty, (string) $variant->sku, $selected];
        }
        if (ExactBdt::fromDecimal((string) $order->sub_total) !== $subtotal) {
            throw ValidationException::withMessages(['products' => 'Order total does not match the selected SKUs.']);
        }

        // Respect physical quantities already held by deferred storefront orders.
        // Current, locked reads avoid stale MySQL REPEATABLE READ snapshots.
        foreach ($needed as $variantId => $qty) {
            $held = (int) DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
                ->where('i.product_variant_id', $variantId)
                ->where('o.storefront_stock_state', StorefrontDeferredStock::RESERVED)
                ->whereNull('o.deleted_at')->whereNull('i.deleted_at')
                ->orderBy('i.id')->lockForUpdate()->get(['i.quantity'])->sum('quantity');
            if ((int) $variants->get($variantId)->stock - $held < $qty) {
                throw ValidationException::withMessages(['products' => 'This variation is out of stock. Please update your selection.']);
            }
        }
        // An approved managed campaign is a reservation, NOT a physical deduction.
        // Order status Confirmed is the single stock-allocating transition shared
        // with storefront checkout. Keep the older allocate() mode for legacy QA.
        if ($deferUntilConfirmed) {
            if (! Schema::hasColumn('orders', 'storefront_stock_state')) {
                throw ValidationException::withMessages(['products' => 'Deferred inventory schema is not installed.']);
            }
            foreach ($approved as [$itemId, $variantId, $qty, $sku, $selected]) {
                DB::table('order_items')->where('id', $itemId)->where('order_id', $orderId)->update([
                    'product_variant_id' => $variantId,
                    'variant_sku' => $sku,
                    'variant_options' => json_encode($selected === [] ? new \stdClass() : $selected, JSON_THROW_ON_ERROR),
                    'inventory_allocated_quantity' => 0,
                    'updated_at' => now(),
                ]);
            }
            DB::table('orders')->where('id', $orderId)
                ->where('storefront_stock_state', StorefrontDeferredStock::LEGACY)
                ->update([
                    'storefront_stock_state' => StorefrontDeferredStock::RESERVED,
                    'updated_at' => now(),
                ]);
            return;
        }
        foreach ($needed as $variantId => $qty) {
            if (DB::table('product_variants')->where('id', $variantId)
                ->where('stock', '>=', $qty)->decrement('stock', $qty) !== 1) {
                throw ValidationException::withMessages(['products' => 'Stock changed. Please refresh and try again.']);
            }
        }

        $remaining = [];
        foreach ($variants as $variant) $remaining[(int) $variant->id] = (int) $variant->stock;
        foreach ($approved as [$itemId, $variantId, $qty, $sku, $selected]) {
            $before = $remaining[$variantId];
            $after = $before - $qty;
            $remaining[$variantId] = $after;
            DB::table('inventory_movements')->insert([
                'product_variant_id' => $variantId, 'order_item_id' => $itemId,
                'event_uuid' => (string) Str::uuid(),
                'operation_key' => 'allocate:v1:item:' . $itemId,
                'kind' => 'allocation', 'quantity_delta' => -$qty,
                'stock_before' => $before, 'stock_after' => $after,
                'actor_id' => null, 'reason' => 'Campaign SKU order allocation',
                'metadata' => json_encode(['order_id' => $orderId, 'campaign_id' => $order->campaign_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            DB::table('order_items')->where('id', $itemId)->where('order_id', $orderId)->update([
                'product_variant_id' => $variantId, 'variant_sku' => $sku,
                'variant_options' => json_encode($selected === [] ? new \stdClass() : $selected, JSON_THROW_ON_ERROR),
                'inventory_allocated_quantity' => $qty, 'updated_at' => now(),
            ]);
        }
        foreach ($productIds as $productId) {
            $totalStock = (int) DB::table('product_variants')->where('product_id', $productId)
                ->whereNull('deleted_at')->sum('stock');
            if ($totalStock > 4_294_967_295) {
                throw ValidationException::withMessages(['products' => 'Product stock is outside the supported range.']);
            }
            DB::table('products')->where('id', $productId)->update(['stock' => $totalStock]);
        }
        DB::table('orders')->where('id', $orderId)->update(['inventory_version' => 1, 'updated_at' => now()]);
    }
}
