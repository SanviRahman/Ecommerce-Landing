<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * P55: explicitly requested, admin-only, transactional Trash purge.
 *
 * The order and its cascading order-items may be removed only AFTER a durable,
 * non-PII financial/inventory audit is persisted. Inventory movements survive:
 * their nullable order_item_id is detached and their original order_id metadata
 * remains. Checkout request tokens survive as "purged" tombstones so replay
 * cannot create a second order. Stock counts are NEVER changed by a purge.
 */
final class AuditedOrderTrashPurger
{
    /** @param list<int> $ids Returns the number actually purged. */
    public function purge(array $ids, int $adminId): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);

        if ($ids === [] || count($ids) > 200 || min($ids) < 1 || $adminId < 1) {
            throw ValidationException::withMessages([
                'orders' => 'Select between 1 and 200 valid trashed orders.',
            ]);
        }

        if (! Schema::hasTable('order_purge_audits')) {
            throw ValidationException::withMessages([
                'orders' => 'Safe permanent deletion requires the P55 order_purge_audits migration. Back up the database and apply the reviewed local migration first.',
            ]);
        }

        try {
            return DB::transaction(function () use ($ids, $adminId): int {
                $orders = Order::onlyTrashed()->whereIn('id', $ids)
                    ->orderBy('id')->lockForUpdate()->get();

                if ($orders->count() !== count($ids)) {
                    throw ValidationException::withMessages([
                        'orders' => 'One or more orders are no longer in Trash. Refresh and retry.',
                    ]);
                }

                foreach ($orders as $order) {
                    $this->purgeOne($order, $adminId);
                }

                return $orders->count();
            }, 3);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1451) {
                throw ValidationException::withMessages([
                    'orders' => 'A related financial, courier or inventory record still prevents permanent deletion. Nothing was deleted.',
                ]);
            }
            throw $exception;
        }
    }

    private function purgeOne(Order $order, int $adminId): void
    {
        // Never erase orders with live courier dispatch, collected payment or
        // external sync history: these have financial/provider obligations.
        if (in_array((string) $order->order_status, [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_COMPLETE_INVOICE], true)
            || $order->shipped_at !== null || $order->delivered_at !== null
            || $order->steadfast_consignment_id !== null || $order->pathao_consignment_id !== null
            || $order->steadfast_sent_at !== null || $order->pathao_sent_at !== null
            || $order->external_website_id !== null
            || (string) $order->payment_status === Order::PAYMENT_STATUS_COLLECTED
            || (Schema::hasTable('external_order_syncs')
                && DB::table('external_order_syncs')->where('order_id', $order->id)->exists())) {
            throw ValidationException::withMessages([
                'orders' => "Order #{$order->id} has courier, external-sync or paid history. Keep this record archived in Trash; permanent deletion is restricted.",
            ]);
        }

        $items = DB::table('order_items')->where('order_id', $order->id)->orderBy('id')
            ->lockForUpdate()->get([
                'id', 'product_id', 'product_name', 'quantity', 'unit_price', 'total_price',
                'product_variant_id', 'variant_sku', 'inventory_allocated_quantity',
            ]);
        $itemIds = $items->pluck('id')->all();
        $movements = collect();

        if (Schema::hasTable('inventory_movements') && $itemIds !== []) {
            $movements = DB::table('inventory_movements')->whereIn('order_item_id', $itemIds)
                ->orderBy('id')->lockForUpdate()->get(['id', 'order_item_id', 'operation_key']);
        }

        if ((int) $order->inventory_version === 1) {
            if (! Schema::hasTable('inventory_movements') || ! Schema::hasTable('checkout_requests')) {
                throw ValidationException::withMessages([
                    'orders' => 'Managed order audit tables are incomplete; deletion is blocked.',
                ]);
            }
            foreach ($items as $item) {
                if ((int) $item->inventory_allocated_quantity > 0
                    && ! $movements->contains('order_item_id', (int) $item->id)) {
                    throw ValidationException::withMessages([
                        'orders' => "Order #{$order->id} has unreconciled managed stock. Deletion is blocked.",
                    ]);
                }
            }
        }

        $checkoutRequests = Schema::hasTable('checkout_requests')
            ? DB::table('checkout_requests')->where('order_id', $order->id)
                ->lockForUpdate()->get(['id', 'status'])
            : collect();

        if ($checkoutRequests->contains(fn ($row) => $row->status !== 'completed')) {
            throw ValidationException::withMessages([
                'orders' => "Order #{$order->id} has a non-completed checkout reservation.",
            ]);
        }

        // Store only essential non-PII evidence. Audit rows have no FK to orders;
        // they remain immutable even after cascading order item/status log deletion.
        DB::table('order_purge_audits')->insert([
            'original_order_id' => (int) $order->id,
            'invoice_id' => (string) $order->invoice_id,
            'admin_id' => $adminId,
            'inventory_version' => (int) $order->inventory_version,
            'audit_snapshot' => json_encode([
                'created_via' => $order->created_via,
                'campaign_id' => $order->campaign_id,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'sub_total' => $order->sub_total,
                'shipping_charge' => $order->shipping_charge,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $items->map(static fn ($item) => [
                    'id' => (int) $item->id,
                    'product_id' => $item->product_id,
                    'sku' => $item->variant_sku,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'allocated_quantity' => (int) $item->inventory_allocated_quantity,
                ])->all(),
                'movement_ids' => $movements->pluck('id')->all(),
                'status_history' => DB::table('order_status_logs')->where('order_id', $order->id)
                    ->orderBy('id')->get(['status', 'created_at'])
                    ->map(static fn ($log) => ['status' => $log->status, 'at' => $log->created_at])->all(),
                'checkout_request_ids' => $checkoutRequests->pluck('id')->all(),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        // Important: keep claims to block idempotency-key replay. Removing the
        // request rows would let a browser retry a previously completed checkout.
        if ($checkoutRequests->isNotEmpty()) {
            DB::table('checkout_requests')->where('order_id', $order->id)->update([
                'order_id' => null,
                'status' => 'purged',
                'updated_at' => now(),
            ]);
        }

        // Keep the inventory ledger, with operation_key and metadata unchanged;
        // the FK is nullable by design. Never restock or re-deduct here.
        if ($movements->isNotEmpty()) {
            DB::table('inventory_movements')->whereIn('id', $movements->pluck('id')->all())
                ->update(['order_item_id' => null]);
        }

        $order->forceDelete(); // Cascading legacy records now have no restrictive FKs.
    }
}
