<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Support\ExactBdt;
use App\Support\P32LocalStorefrontAccess;
use App\Support\P58ProductionStorefrontAccess;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Approved production and local-main-DB storefront checkouts reserve sellable
 * quantities WITHOUT physically deducting stock. Only admin confirmation
 * allocates them. P26 isolated QA and legacy/campaign orders are unchanged.
 */
final class StorefrontDeferredStock
{
    public const LEGACY = 0;
    public const RESERVED = 1;
    public const RELEASED = 2;
    public const ALLOCATED = 3;

    public static function checkoutEnabled(): bool
    {
        return request()->attributes->get('p58_production_checkout') === true
            || request()->attributes->get('p32_local_checkout') === true;
    }

    public static function availableForVariant(int $variantId, int $physicalStock): int
    {
        if (request()->attributes->get('p58_production_commerce') !== true
            && request()->attributes->get('p32_local_checkout') !== true) {
            return max(0, $physicalStock);
        }
        return max(0, $physicalStock - (int) DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->where('i.product_variant_id', $variantId)
            ->where('o.storefront_stock_state', self::RESERVED)
            ->whereNull('o.deleted_at')->whereNull('i.deleted_at')
            ->sum('i.quantity'));
    }

    /** Checkout already validates products/prices; lock every SKU before reserving. */
    public function assertCapacityAndLock(array $quantitiesByVariant): void
    {
        if (! self::checkoutEnabled()) {
            throw new LogicException('Deferred stock requires an approved storefront checkout.');
        }
        if (request()->attributes->get('p58_production_checkout') === true) {
            app(P58ProductionStorefrontAccess::class)->assertStorefrontCheckout();
        } elseif (request()->attributes->get('p32_local_checkout') === true) {
            app(P32LocalStorefrontAccess::class)->assertStorefrontCheckout();
        } else {
            throw new LogicException('Unrecognized storefront checkout context.');
        }
        $this->assertTransaction();
        if ($quantitiesByVariant === []) {
            throw new DomainException('Cannot reserve an empty storefront order.');
        }
        $ids = array_keys($quantitiesByVariant);
        sort($ids, SORT_NUMERIC);
        $variants = DB::table('product_variants')->whereIn('id', $ids)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($variants->count() !== count($ids)) {
            throw ValidationException::withMessages(['cart' => 'A selected product was removed.']);
        }
        foreach ($ids as $id) {
            $quantity = (int) $quantitiesByVariant[$id];
            InventoryPolicy::assertPurchasable(1000, $quantity);
            $variant = $variants->get($id);
            // Locking/current read is essential under MySQL REPEATABLE READ:
            // a normal SUM() could see a snapshot older than the SKU row lock.
            $held = (int) DB::table('order_items as i')
                ->join('orders as o', 'o.id', '=', 'i.order_id')
                ->where('i.product_variant_id', (int) $id)
                ->where('o.storefront_stock_state', self::RESERVED)
                ->whereNull('o.deleted_at')->whereNull('i.deleted_at')
                ->orderBy('i.id')->lockForUpdate()->get(['i.quantity'])->sum('quantity');
            if (! $variant->is_active || $variant->deleted_at !== null
                || (int) $variant->stock - $held < $quantity) {
                throw ValidationException::withMessages([
                    'cart' => 'Some stock is reserved by earlier orders. Review your cart before checkout.',
                ]);
            }
        }
    }

    /** Admin confirmation consumes the reservation and writes the standard P03 ledger. */
    public function finalize(int $orderId): void
    {
        // Neither an untrusted request flag nor a normal checkout may finalize stock.
        if (app(P58ProductionStorefrontAccess::class)->allowsAdminManaged()) {
            app(P58ProductionStorefrontAccess::class)->assertAdminManaged();
        } else {
            app(P32LocalStorefrontAccess::class)->assertAdminManaged();
        }
        $this->assertTransaction();
        $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
        if (! $order || $order->deleted_at !== null || (int) $order->storefront_stock_state !== self::RESERVED
            || (int) $order->inventory_version !== 0 || $order->campaign_id !== null
            || $order->created_via !== 'frontend'
            || $order->order_status !== 'confirmed') {
            throw new DomainException('This order does not have a pending storefront stock reservation.');
        }
        $items = DB::table('order_items')->where('order_id', $orderId)->whereNull('deleted_at')
            ->orderBy('id')->get();
        if ($items->isEmpty() || $items->count() > 30) {
            throw new DomainException('Reserved order lines are missing or inconsistent.');
        }
        $needed = [];
        $total = 0;
        foreach ($items as $item) {
            $id = (int) $item->product_variant_id;
            $quantity = (int) $item->quantity;
            if ($id < 1 || $item->product_id === null
                || (int) $item->inventory_allocated_quantity !== 0
                || DB::table('inventory_movements')->where('operation_key', 'allocate:v1:item:' . $item->id)->exists()) {
                throw new DomainException('The order contains an already processed or invalid line.');
            }
            InventoryPolicy::assertPurchasable(1000, $quantity);
            $unit = ExactBdt::fromDecimal((string) $item->unit_price);
            $line = ExactBdt::multiply($unit, $quantity);
            if ($unit < 1 || ExactBdt::fromDecimal((string) $item->total_price) !== $line
                || ExactBdt::fromDecimal((string) ($item->discount_amount ?? '0')) !== 0) {
                throw new DomainException('The reserved order price snapshot is inconsistent.');
            }
            $total = ExactBdt::add($total, $line);
            $needed[$id] = ($needed[$id] ?? 0) + $quantity;
        }
        if (ExactBdt::fromDecimal((string) $order->sub_total) !== $total) {
            throw new DomainException('Reserved order totals were modified after checkout.');
        }
        $ids = array_keys($needed);
        sort($ids, SORT_NUMERIC);
        $variants = DB::table('product_variants')->whereIn('id', $ids)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($variants->count() !== count($ids)) {
            throw new DomainException('A reserved product variant is missing.');
        }
        foreach ($items as $item) {
            if ((int) $variants[(int) $item->product_variant_id]->product_id !== (int) $item->product_id) {
                throw new DomainException('Reserved SKU no longer belongs to its order product.');
            }
        }
        foreach ($needed as $id => $quantity) {
            InventoryPolicy::assertPurchasable((int) $variants[$id]->stock, $quantity);
        }
        foreach ($needed as $id => $quantity) {
            if (DB::table('product_variants')->where('id', $id)
                ->where('stock', '>=', $quantity)->decrement('stock', $quantity) !== 1) {
                throw new DomainException('Stock changed during confirmation. Retry the status update.');
            }
        }
        $running = [];
        foreach ($variants as $variant) $running[(int) $variant->id] = (int) $variant->stock;
        $productIds = [];
        foreach ($items as $item) {
            $variantId = (int) $item->product_variant_id;
            $quantity = (int) $item->quantity;
            $before = $running[$variantId];
            $after = $before - $quantity;
            $running[$variantId] = $after;
            DB::table('inventory_movements')->insert([
                'product_variant_id' => $variantId,
                'order_item_id' => $item->id,
                'event_uuid' => (string) Str::uuid(),
                'operation_key' => 'allocate:v1:item:' . $item->id,
                'kind' => 'allocation', 'quantity_delta' => -$quantity,
                'stock_before' => $before, 'stock_after' => $after,
                'actor_id' => auth()->id(), 'reason' => 'P64 admin-confirmed storefront allocation',
                'metadata' => json_encode(['order_id' => $orderId, 'confirmed_by_admin' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            DB::table('order_items')->where('id', $item->id)->update([
                'inventory_allocated_quantity' => $quantity, 'updated_at' => now(),
            ]);
            $productIds[(int) $item->product_id] = (int) $item->product_id;
        }
        foreach ($productIds as $productId) {
            $stock = (int) DB::table('product_variants')->where('product_id', $productId)
                ->whereNull('deleted_at')->sum('stock');
            if ($stock > 4294967295) throw new DomainException('Physical product stock exceeds its storage capacity.');
            DB::table('products')->where('id', $productId)->update(['stock' => $stock]);
        }
        DB::table('orders')->where('id', $orderId)
            ->where('storefront_stock_state', self::RESERVED)
            ->update(['inventory_version' => 1, 'storefront_stock_state' => self::ALLOCATED, 'updated_at' => now()]);
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Deferred inventory requires the enclosing order transaction.');
        }
    }
}
