<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Inventory\StorefrontDeferredStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * P66: Reversible administrative order archive, including stock-linked orders.
 *
 * Archiving NEVER changes physical stock or the immutable inventory ledger.
 * Deferred checkout reservations are excluded from availableForVariant() as
 * soon as an order is soft-deleted. Restoring those orders must atomically
 * reacquire capacity so a later Confirmed transition cannot oversell stock.
 * Permanent deletion remains under AuditedOrderTrashPurger's stricter policy.
 */
final class ManagedOrderTrashService
{
    /** @param list<int> $orderIds */
    public function moveToTrash(array $orderIds, int $adminId): int
    {
        $this->assertAdmin($adminId);
        $orderIds = $this->normalizeIds($orderIds);
        if ($orderIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($orderIds, $adminId): int {
            $orders = Order::query()->whereIn('id', $orderIds)
                ->orderBy('id')->lockForUpdate()->get();
            $this->assertExactMatch($orderIds, $orders->count(), 'active');

            $canAudit = Schema::hasTable('order_status_logs');
            foreach ($orders as $order) {
                // SoftDeletes updates deleted_at. The reservation query skips
                // deleted orders, so an outstanding hold is freed without
                // deducting/refunding stock or changing its state/history.
                $order->delete();
                $this->recordAudit($order, $adminId, 'Moved to Trash', $canAudit);
            }

            return $orders->count();
        }, 3);
    }

    /** @param list<int> $orderIds */
    public function restoreFromTrash(array $orderIds, int $adminId): int
    {
        $this->assertAdmin($adminId);
        $orderIds = $this->normalizeIds($orderIds);
        if ($orderIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($orderIds, $adminId): int {
            $orders = Order::onlyTrashed()->whereIn('id', $orderIds)
                ->orderBy('id')->lockForUpdate()->get();
            $this->assertExactMatch($orderIds, $orders->count(), 'trashed');

            // No inventory adjustments for allocated or legacy orders. For
            // deferred orders, the active hold was removed by soft deletion,
            // so restoring must first atomically reserve the same SKU quantity.
            $reserved = $orders->filter(static fn (Order $order): bool =>
                (int) ($order->storefront_stock_state ?? StorefrontDeferredStock::LEGACY)
                    === StorefrontDeferredStock::RESERVED
            );
            if ($reserved->isNotEmpty()) {
                $this->assertRestorableReservationCapacity($reserved->pluck('id')->map(
                    static fn ($id): int => (int) $id
                )->all());
            }

            $canAudit = Schema::hasTable('order_status_logs');
            foreach ($orders as $order) {
                $order->restore();
                $this->recordAudit($order, $adminId, 'Restored from Trash', $canAudit);
            }

            return $orders->count();
        }, 3);
    }

    /**
     * @param list<int> $orderIds
     */
    private function assertRestorableReservationCapacity(array $orderIds): void
    {
        if (! Schema::hasColumn('orders', 'storefront_stock_state')) {
            throw ValidationException::withMessages([
                'orders' => 'Cannot restore reserved orders until the stock-state migration is applied.',
            ]);
        }

        $orders = DB::table('orders')->whereIn('id', $orderIds)->orderBy('id')->get([
            'id', 'storefront_stock_state', 'inventory_version', 'created_via',
            'campaign_id', 'order_status',
        ]);
        foreach ($orders as $order) {
            if ((int) $order->storefront_stock_state !== StorefrontDeferredStock::RESERVED
                || (int) $order->inventory_version !== 0
                || (string) $order->created_via !== Order::CREATED_VIA_FRONTEND
                || $order->campaign_id !== null
                || ! in_array((string) $order->order_status, ['processing', 'pending'], true)) {
                throw ValidationException::withMessages([
                    'orders' => "Order #{$order->id} has an inconsistent pending stock reservation. Restore was cancelled.",
                ]);
            }
        }

        $items = DB::table('order_items')->whereIn('order_id', $orderIds)
            ->whereNull('deleted_at')->orderBy('id')->get([
                'order_id', 'product_id', 'product_variant_id', 'quantity', 'inventory_allocated_quantity',
            ]);
        $needed = [];
        $itemCounts = [];
        foreach ($items as $item) {
            $id = (int) $item->product_variant_id;
            $qty = (int) $item->quantity;
            if ($id < 1 || $qty < 1 || $qty > 1000 || (int) $item->inventory_allocated_quantity !== 0) {
                throw ValidationException::withMessages([
                    'orders' => 'A trashed order has invalid stock item details. Restore was cancelled.',
                ]);
            }
            $needed[$id] = ($needed[$id] ?? 0) + $qty;
            $orderId = (int) $item->order_id;
            $itemCounts[$orderId] = ($itemCounts[$orderId] ?? 0) + 1;
        }
        foreach ($orderIds as $orderId) {
            if (($itemCounts[$orderId] ?? 0) < 1 || $itemCounts[$orderId] > 30) {
                throw ValidationException::withMessages([
                    'orders' => "Order #{$orderId} has missing or excessive stock items. Restore was cancelled.",
                ]);
            }
        }

        $variantIds = array_keys($needed);
        sort($variantIds, SORT_NUMERIC);
        $variants = DB::table('product_variants')->whereIn('id', $variantIds)
            ->orderBy('id')->lockForUpdate()->get([
                'id', 'product_id', 'stock', 'is_active', 'deleted_at',
            ])->keyBy('id');
        if ($variants->count() !== count($variantIds)) {
            throw ValidationException::withMessages([
                'orders' => 'An archived SKU no longer exists. Restore was cancelled.',
            ]);
        }

        foreach ($items as $item) {
            $variant = $variants->get((int) $item->product_variant_id);
            if ((int) $variant->product_id !== (int) $item->product_id) {
                throw ValidationException::withMessages([
                    'orders' => 'A stock item no longer belongs to its original product. Restore was cancelled.',
                ]);
            }
        }

        // Locking current reads are essential under MySQL REPEATABLE READ.
        // This excludes the trashed orders and counts existing active holds.
        $held = DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->whereIn('i.product_variant_id', $variantIds)
            ->where('o.storefront_stock_state', StorefrontDeferredStock::RESERVED)
            ->whereNull('o.deleted_at')->whereNull('i.deleted_at')
            ->orderBy('i.id')->lockForUpdate()->get(['i.product_variant_id', 'i.quantity']);
        $heldByVariant = [];
        foreach ($held as $item) {
            $variantId = (int) $item->product_variant_id;
            $heldByVariant[$variantId] = ($heldByVariant[$variantId] ?? 0) + (int) $item->quantity;
        }

        foreach ($variantIds as $variantId) {
            $variant = $variants->get($variantId);
            $available = (int) $variant->stock - (int) ($heldByVariant[$variantId] ?? 0);
            if (! $variant->is_active || $variant->deleted_at !== null
                || $available < $needed[$variantId]) {
                throw ValidationException::withMessages([
                    'orders' => 'Not enough unreserved stock to restore the selected Processing orders. No orders were restored.',
                ]);
            }
        }
    }

    /** @param list<int> $orderIds @return list<int> */
    private function normalizeIds(array $orderIds): array
    {
        foreach ($orderIds as $id) {
            if (! is_int($id) || $id < 1) {
                throw ValidationException::withMessages(['orders' => 'Select valid order IDs.']);
            }
        }
        $orderIds = array_values(array_unique($orderIds));
        sort($orderIds, SORT_NUMERIC);
        if (count($orderIds) > 500) {
            throw ValidationException::withMessages(['orders' => 'Select no more than 500 orders at a time.']);
        }

        return $orderIds;
    }

    private function assertAdmin(int $adminId): void
    {
        if ($adminId < 1 || (int) auth()->id() !== $adminId || ! auth()->user()?->isAdmin()) {
            abort(403, 'Only admins may archive or restore orders.');
        }
    }

    private function assertExactMatch(array $orderIds, int $count, string $state): void
    {
        if ($count !== count($orderIds)) {
            throw ValidationException::withMessages([
                'orders' => "Some selected orders are no longer {$state}. Refresh and retry; nothing was changed.",
            ]);
        }
    }

    private function recordAudit(Order $order, int $adminId, string $action, bool $canAudit): void
    {
        if (! $canAudit) {
            return;
        }
        // Status history remains intact: archiving is not an order-status or
        // courier transition. Record the admin action in the existing log.
        DB::table('order_status_logs')->insert([
            'order_id' => (int) $order->id,
            'status' => (string) $order->order_status,
            'note' => "{$action} by admin #{$adminId} (P66; no stock mutation).",
            'created_by' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
