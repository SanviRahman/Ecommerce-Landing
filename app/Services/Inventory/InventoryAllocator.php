<?php

namespace App\Services\Inventory;

use App\Support\ExactBdt;
use App\Support\P02MigrationSafety;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * P03 managed inventory core with separately verified P58 storefront checkout allocation.
 *
 * Must be called INSIDE the same transaction that creates/changes the order.
 * Before the P02 MariaDB + campaign/manual regression gates pass this class is
 * deliberately locked to a disposable, local MySQL test schema only.
 *
 * Lock order for every future writer: order -> products ascending -> variants
 * ascending. Do not wire a separate stock decrement alongside this allocator.
 */
final class InventoryAllocator
{
    /**
     * Allocate one existing, fully server-priced simple-product order.
     * Keys are persisted order_item IDs, values are exact default variant IDs.
     *
     * @param array<int,int> $variantByOrderItem
     */
    public function allocateOrder(int $orderId, array $variantByOrderItem): void
    {
        $this->assertTestTransaction();

        if ($orderId < 1 || $variantByOrderItem === []) {
            throw new InvalidArgumentException('An order with at least one item is required.');
        }
        foreach ($variantByOrderItem as $itemId => $variantId) {
            if (! is_int($itemId) || $itemId < 1 || ! is_int($variantId) || $variantId < 1) {
                throw new InvalidArgumentException('Order item and variant IDs must be positive integers.');
            }
        }

        $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
        if ($order === null || $order->deleted_at !== null || $order->created_via === 'external_api') {
            throw new DomainException('This order is not eligible for local inventory allocation.');
        }
        if ((int) $order->inventory_version !== 0 && (int) $order->inventory_version !== 1) {
            throw new DomainException('Unknown order inventory version.');
        }

        $items = DB::table('order_items')
            ->where('order_id', $orderId)->whereNull('deleted_at')
            ->orderBy('id')->get()->keyBy('id');
        if ($items->count() !== count($variantByOrderItem)) {
            throw new DomainException('Every persisted order item must have an explicit variant mapping.');
        }

        $productIds = [];
        foreach ($items as $itemId => $item) {
            if (! isset($variantByOrderItem[$itemId]) || $item->product_id === null) {
                throw new DomainException('An order item has no authorized local product/variant.');
            }
            InventoryPolicy::assertPurchasable(1000, (int) $item->quantity);
            $productIds[] = (int) $item->product_id;
        }
        $productIds = array_values(array_unique($productIds));
        sort($productIds, SORT_NUMERIC);

        $products = DB::table('products')->whereIn('id', $productIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($products->count() !== count($productIds)) {
            throw new DomainException('An order product no longer exists.');
        }
        foreach ($products as $product) {
            if (! (bool) $product->status || $product->deleted_at !== null
                || (int) $product->inventory_version !== 1 || (bool) $product->has_variants) {
                throw new DomainException('Product is inactive, unreconciled, or requires variant administration.');
            }
        }

        $variantIds = array_values(array_unique(array_values($variantByOrderItem)));
        sort($variantIds, SORT_NUMERIC);
        $variants = DB::table('product_variants')->whereIn('id', $variantIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($variants->count() !== count($variantIds)) {
            throw new DomainException('One or more variants are missing.');
        }

        $needed = [];
        $newLines = [];
        $subtotal = 0;

        foreach ($items as $itemId => $item) {
            $product = $products->get((int) $item->product_id);
            $variantId = $variantByOrderItem[$itemId];
            $variant = $variants->get($variantId);
            if ((int) $variant->product_id !== (int) $product->id
                || ! (bool) $variant->is_default
                || (string) $variant->option_signature !== 'default'
                || ! (bool) $variant->is_active || $variant->deleted_at !== null) {
                throw new DomainException('Variant is inactive or does not belong to this simple product.');
            }

            $catalogUnit = $variant->price_override === null
                ? (string) $product->new_price : (string) $variant->price_override;
            if ($order->campaign_id !== null) {
                $campaignRow = DB::table('campaign_product')
                    ->where('campaign_id', $order->campaign_id)
                    ->where('product_id', $product->id)->first(['campaign_price']);
                if ($campaignRow === null) {
                    throw new DomainException('Order product is not attached to its campaign.');
                }
                if ((int) $campaignRow->campaign_price > 0) {
                    $catalogUnit = (string) $campaignRow->campaign_price;
                }
            }

            $expectedUnit = ExactBdt::fromDecimal($catalogUnit);
            if ($expectedUnit === 0 || ExactBdt::fromDecimal((string) $item->unit_price) !== $expectedUnit) {
                throw new DomainException('Order unit price does not match authoritative catalog/campaign pricing.');
            }
            $expectedLine = ExactBdt::multiply($expectedUnit, (int) $item->quantity);
            if (ExactBdt::fromDecimal((string) $item->total_price) !== $expectedLine
                || ExactBdt::fromDecimal((string) ($item->discount_amount ?? '0')) !== 0) {
                throw new DomainException('Order item totals do not match authoritative pricing.');
            }
            $subtotal = ExactBdt::add($subtotal, $expectedLine);

            $operationKey = 'allocate:v1:item:' . $itemId;
            $movementExists = DB::table('inventory_movements')->where('operation_key', $operationKey)->exists();
            if ((int) $item->inventory_allocated_quantity > 0) {
                if (! $movementExists || (int) $item->inventory_allocated_quantity !== (int) $item->quantity
                    || (int) $item->product_variant_id !== $variantId) {
                    throw new DomainException('Existing inventory allocation is inconsistent.');
                }
                continue; // Verified replay; never deduct twice.
            }
            if ($movementExists || $item->product_variant_id !== null) {
                throw new DomainException('Previously allocated/released item requires a reviewed lifecycle transition.');
            }
            $quantity = (int) $item->quantity;
            $needed[$variantId] = ($needed[$variantId] ?? 0) + $quantity;
            $newLines[] = [$itemId, $variantId, $quantity, $operationKey, (string) $variant->sku];
        }

        if (ExactBdt::fromDecimal((string) $order->sub_total) !== $subtotal) {
            throw new DomainException('Order subtotal does not equal persisted item totals.');
        }

        if ($needed !== [] && ! in_array((string) $order->order_status, ['pending', 'processing', 'confirmed', 'complete_invoice'], true)) {
            throw new DomainException('Cannot newly allocate stock to a shipped, cancelled, delivered, or fake order.');
        }

        foreach ($needed as $variantId => $quantity) {
            InventoryPolicy::assertPurchasable((int) $variants->get($variantId)->stock, $quantity);
        }

        foreach ($needed as $variantId => $quantity) {
            $updated = DB::table('product_variants')->where('id', $variantId)
                ->where('stock', '>=', $quantity)->decrement('stock', $quantity);
            if ($updated !== 1) {
                throw new DomainException('Concurrent stock update rejected; retry the entire transaction.');
            }
        }

        $runningStock = [];
        foreach ($variants as $variant) {
            $runningStock[(int) $variant->id] = (int) $variant->stock;
        }
        foreach ($newLines as [$itemId, $variantId, $quantity, $operationKey, $sku]) {
            $stockBefore = $runningStock[$variantId];
            $stockAfter = $stockBefore - $quantity;
            $runningStock[$variantId] = $stockAfter;
            DB::table('inventory_movements')->insert([
                'product_variant_id' => $variantId,
                'order_item_id' => $itemId,
                'event_uuid' => (string) Str::uuid(),
                'operation_key' => $operationKey,
                'kind' => 'allocation',
                'quantity_delta' => -$quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'actor_id' => null,
                'reason' => 'P03 managed order allocation',
                'metadata' => json_encode(['order_id' => $orderId], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            DB::table('order_items')->where('id', $itemId)->where('order_id', $orderId)->update([
                'product_variant_id' => $variantId,
                'variant_sku' => $sku,
                'variant_options' => json_encode(new \stdClass(), JSON_THROW_ON_ERROR),
                'inventory_allocated_quantity' => $quantity,
                'updated_at' => now(),
            ]);
        }

        if ($newLines !== []) {
            $this->refreshLegacyStock($productIds);
        }
        DB::table('orders')->where('id', $orderId)->update(['inventory_version' => 1]);
    }

    /**
     * Variant-aware storefront allocation, in the SAME inventory ledger as P03.
     * The caller provides item->variant mappings only after persisting order lines;
     * catalog validation, price verification and all stock mutations happen here.
     * Existing campaign/default-variant allocation is unchanged.
     *
     * @param array<int,int> $variantByOrderItem
     */
    public function allocateStorefrontOrder(int $orderId, array $variantByOrderItem): void
    {
        if (request()->attributes->get('p58_production_checkout') === true) {
            app(\App\Support\P58ProductionStorefrontAccess::class)->assertStorefrontCheckout();
            if (DB::transactionLevel() < 1) {
                throw new LogicException('Production inventory allocation must share the checkout transaction.');
            }
        } elseif (request()->attributes->get('p32_local_checkout', false) === true) {
            app(\App\Support\P32LocalStorefrontAccess::class)->assertStorefrontCheckout();
            if (DB::transactionLevel() < 1) {
                throw new LogicException('Storefront inventory must share the checkout transaction.');
            }
        } else {
            $this->assertTestTransaction();
        }
        if (! config('storefront.p08_checkout_test_enabled', false) || $orderId < 1 || $variantByOrderItem === []) {
            throw new LogicException('Storefront inventory allocation is not enabled or has no order.');
        }
        foreach ($variantByOrderItem as $itemId => $variantId) {
            if (! is_int($itemId) || $itemId < 1 || ! is_int($variantId) || $variantId < 1) {
                throw new InvalidArgumentException('Invalid storefront order item/variant mapping.');
            }
        }
        $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
        if (! $order || $order->deleted_at !== null || $order->campaign_id !== null
            || $order->created_via !== 'frontend' || (int) $order->inventory_version !== 0) {
            throw new DomainException('Order is not an unallocated storefront order.');
        }
        $items = DB::table('order_items')->where('order_id', $orderId)->whereNull('deleted_at')
            ->orderBy('id')->get()->keyBy('id');
        if ($items->count() !== count($variantByOrderItem)) {
            throw new DomainException('All storefront order items require a server-authorized variant.');
        }
        $productIds = [];
        $variantIds = [];
        foreach ($items as $item) {
            $itemId = (int) $item->id;
            if (! isset($variantByOrderItem[$itemId]) || $item->product_id === null) {
                throw new DomainException('Missing storefront product or option selection.');
            }
            InventoryPolicy::assertPurchasable(1000, (int) $item->quantity);
            $productIds[] = (int) $item->product_id;
            $variantIds[] = $variantByOrderItem[$itemId];
        }
        $productIds = array_values(array_unique($productIds));
        $variantIds = array_values(array_unique($variantIds));
        sort($productIds, SORT_NUMERIC);
        sort($variantIds, SORT_NUMERIC);
        $products = DB::table('products')->whereIn('id', $productIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $variants = DB::table('product_variants')->whereIn('id', $variantIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($products->count() !== count($productIds) || $variants->count() !== count($variantIds)) {
            throw new DomainException('Storefront product/variant was removed.');
        }
        $catalog = app(\App\Services\Storefront\PublicCatalog::class);
        $eligible = [];
        foreach ($products as $product) {
            if (! $product->status || $product->deleted_at !== null || (int) $product->inventory_version !== 1
                || ! $catalog->eligibleProducts()->where('products.id', $product->id)->exists()) {
                throw new DomainException('Storefront product is inactive or inventory is not reconciled.');
            }
            if ((bool) $product->has_variants) {
                $model = \App\Models\Product::query()->with('variants.optionValues.option')->find($product->id);
                $eligible[(int) $product->id] = $catalog->eligibleVariants($model)->pluck('id')->map('intval')->all();
            }
        }
        $subtotal = 0;
        $needed = [];
        $approvedLines = [];
        foreach ($items as $item) {
            $itemId = (int) $item->id;
            $product = $products->get((int) $item->product_id);
            $variantId = $variantByOrderItem[$itemId];
            $variant = $variants->get($variantId);
            if ((int) $variant->product_id !== (int) $product->id || ! (bool) $variant->is_active
                || $variant->deleted_at !== null || (int) ($item->product_variant_id ?? 0) !== $variantId) {
                throw new DomainException('Storefront variant identity is not consistent.');
            }
            if ((bool) $product->has_variants) {
                if ((bool) $variant->is_default || ! in_array($variantId, $eligible[(int) $product->id], true)) {
                    throw new DomainException('Incomplete or inactive storefront option combination.');
                }
                $selected = DB::table('product_variant_values')->where('product_variant_id', $variantId)
                    ->pluck('product_option_value_id', 'product_option_id')->map('intval')->all();
                if ($selected === [] || \App\Support\VariantSignature::fromOptionValues($selected) !== $variant->option_signature) {
                    throw new DomainException('Variant option identity has changed.');
                }
            } else {
                if (! (bool) $variant->is_default || $variant->option_signature !== 'default') {
                    throw new DomainException('Simple product requires its active default variant.');
                }
                $selected = [];
            }
            $unitMinor = \App\Support\ExactBdt::fromDecimal((string) ($variant->price_override ?? $product->new_price));
            $lineMinor = \App\Support\ExactBdt::multiply($unitMinor, (int) $item->quantity);
            if ($unitMinor < 1 || \App\Support\ExactBdt::fromDecimal((string) $item->unit_price) !== $unitMinor
                || \App\Support\ExactBdt::fromDecimal((string) $item->total_price) !== $lineMinor
                || \App\Support\ExactBdt::fromDecimal((string) ($item->discount_amount ?? '0')) !== 0
                || (int) $item->inventory_allocated_quantity !== 0
                || DB::table('inventory_movements')->where('operation_key', 'allocate:v1:item:' . $itemId)->exists()) {
                throw new DomainException('Storefront price, order item or stock movement is inconsistent.');
            }
            $subtotal = \App\Support\ExactBdt::add($subtotal, $lineMinor);
            $quantity = (int) $item->quantity;
            $needed[$variantId] = ($needed[$variantId] ?? 0) + $quantity;
            $approvedLines[] = [$itemId, $variantId, $quantity, (string) $variant->sku, $selected];
        }
        if (\App\Support\ExactBdt::fromDecimal((string) $order->sub_total) !== $subtotal
            || ! in_array($order->order_status, ['pending', 'processing', 'confirmed'], true)) {
            throw new DomainException('Storefront totals or order status do not permit allocation.');
        }
        foreach ($needed as $variantId => $quantity) {
            InventoryPolicy::assertPurchasable((int) $variants->get($variantId)->stock, $quantity);
        }
        foreach ($needed as $variantId => $quantity) {
            $affected = DB::table('product_variants')->where('id', $variantId)
                ->where('stock', '>=', $quantity)->decrement('stock', $quantity);
            if ($affected !== 1) {
                throw new DomainException('Concurrent checkout stock change: order was rolled back.');
            }
        }
        $running = [];
        foreach ($variants as $variant) {
            $running[(int) $variant->id] = (int) $variant->stock;
        }
        foreach ($approvedLines as [$itemId, $variantId, $quantity, $sku, $selected]) {
            $before = $running[$variantId];
            $after = $before - $quantity;
            $running[$variantId] = $after;
            DB::table('inventory_movements')->insert([
                'product_variant_id' => $variantId,
                'order_item_id' => $itemId, 'event_uuid' => (string) Str::uuid(),
                'operation_key' => 'allocate:v1:item:' . $itemId,
                'kind' => 'allocation', 'quantity_delta' => -$quantity,
                'stock_before' => $before, 'stock_after' => $after,
                'actor_id' => null, 'reason' => 'P08 storefront COD checkout',
                'metadata' => json_encode(['order_id' => $orderId], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            DB::table('order_items')->where('id', $itemId)->where('order_id', $orderId)->update([
                'variant_sku' => $sku,
                'variant_options' => json_encode($selected === [] ? new \stdClass() : $selected, JSON_THROW_ON_ERROR),
                'inventory_allocated_quantity' => $quantity,
                'updated_at' => now(),
            ]);
        }
        $this->refreshLegacyStock($productIds);
        DB::table('orders')->where('id', $orderId)->update(['inventory_version' => 1, 'updated_at' => now()]);
    }

    /**
     * Explicit pre-dispatch cancellation ONLY. Never called from generic status
     * updates, courier callbacks, or order deletion without an approved policy.
     */
    public function releaseCancelledOrder(int $orderId, string $eventUuid): void
    {
        if (app(\App\Support\P58ProductionStorefrontAccess::class)->allowsAdminManaged()) {
            app(\App\Support\P58ProductionStorefrontAccess::class)->assertAdminManaged();
            if (DB::transactionLevel() < 1) {
                throw new LogicException('Production cancellation and stock release must share one transaction.');
            }
        } else {
            $this->assertTestTransaction();
        }
        if ($orderId < 1 || Str::isUuid($eventUuid) === false) {
            throw new InvalidArgumentException('A valid order ID and persisted transition UUID are required.');
        }
        $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
        if ($order === null || $order->deleted_at !== null || (int) $order->inventory_version !== 1) {
            throw new DomainException('Legacy, deleted and missing orders must never be auto-restocked.');
        }
        InventoryPolicy::assertCanRelease(
            (string) $order->order_status,
            $order->shipped_at !== null,
            $order->delivered_at !== null,
            $order->steadfast_consignment_id !== null || $order->pathao_consignment_id !== null
        );

        $items = DB::table('order_items')->where('order_id', $orderId)->whereNull('deleted_at')
            ->orderBy('id')->get();
        $toReturn = $items->filter(fn ($item) => (int) $item->inventory_allocated_quantity > 0);
        if ($toReturn->isEmpty()) {
            return; // Already released; no second credit.
        }
        if ($items->contains(fn ($item) => $item->product_variant_id === null)) {
            throw new DomainException('Mixed legacy/unmapped items require manual reconciliation.');
        }

        $productIds = $toReturn->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $variantsIds = $toReturn->pluck('product_variant_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $products = DB::table('products')->whereIn('id', $productIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $variants = DB::table('product_variants')->whereIn('id', $variantsIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($products->count() !== count($productIds) || $variants->count() !== count($variantsIds)) {
            throw new DomainException('Referenced product or variant is missing.');
        }

        $returnQuantity = [];
        foreach ($toReturn as $item) {
            $variant = $variants->get((int) $item->product_variant_id);
            if ((int) $variant->product_id !== (int) $item->product_id || (int) $item->inventory_allocated_quantity > 1000) {
                throw new DomainException('Order allocation/variant reference is inconsistent.');
            }
            if (! DB::table('inventory_movements')->where('operation_key', 'allocate:v1:item:' . $item->id)->exists()) {
                throw new DomainException('Cannot release stock without an original allocation movement.');
            }
            $variantId = (int) $variant->id;
            $returnQuantity[$variantId] = ($returnQuantity[$variantId] ?? 0) + (int) $item->inventory_allocated_quantity;
        }
        foreach ($returnQuantity as $variantId => $quantity) {
            if ($quantity > 2_147_483_647 || $quantity > 4_294_967_295 - (int) $variants->get($variantId)->stock) {
                throw new DomainException('Released stock exceeds the inventory column capacity.');
            }
        }
        foreach ($returnQuantity as $variantId => $quantity) {
            DB::table('product_variants')->where('id', $variantId)->increment('stock', $quantity);
        }
        $runningStock = [];
        foreach ($variants as $variant) {
            $runningStock[(int) $variant->id] = (int) $variant->stock;
        }
        foreach ($toReturn as $item) {
            $variantId = (int) $item->product_variant_id;
            $quantity = (int) $item->inventory_allocated_quantity;
            $operationKey = 'release:v1:item:' . $item->id . ':' . $eventUuid;
            if (DB::table('inventory_movements')->where('operation_key', $operationKey)->exists()) {
                throw new DomainException('A release event already exists for a still-allocated item.');
            }
            $before = $runningStock[$variantId];
            $after = $before + $quantity;
            $runningStock[$variantId] = $after;
            DB::table('inventory_movements')->insert([
                'product_variant_id' => $variantId,
                'order_item_id' => $item->id,
                'event_uuid' => (string) Str::uuid(),
                'operation_key' => $operationKey,
                'kind' => 'release',
                'quantity_delta' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'actor_id' => null,
                'reason' => 'Approved pre-dispatch cancellation; not a physical return',
                'metadata' => json_encode(['order_id' => $orderId, 'transition' => $eventUuid], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            DB::table('order_items')->where('id', $item->id)->update([
                'inventory_allocated_quantity' => 0,
                'updated_at' => now(),
            ]);
        }
        $this->refreshLegacyStock($productIds);
    }

    /** @param array<int,int> $productIds */
    private function refreshLegacyStock(array $productIds): void
    {
        foreach ($productIds as $productId) {
            $total = (int) DB::table('product_variants')
                ->where('product_id', $productId)->whereNull('deleted_at')->sum('stock');
            if ($total > 4_294_967_295) {
                throw new DomainException('Product compatibility stock exceeds unsigned integer capacity.');
            }
            DB::table('products')->where('id', $productId)->update(['stock' => $total]);
        }
    }

    private function assertTestTransaction(): void
    {
        if (config('storefront.p03_core_test_enabled', false) !== true) {
            throw new LogicException('P03 inventory core is disabled pending isolated MySQL integration approval.');
        }
        // Even a mistaken feature-flag toggle cannot activate it on the old DB.
        P02MigrationSafety::assertIsolatedTestDatabase();
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Managed inventory calls must share the enclosing order transaction.');
        }
    }
}
