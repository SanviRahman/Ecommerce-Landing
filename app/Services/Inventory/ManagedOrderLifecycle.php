<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Support\P02MigrationSafety;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

/**
 * P03 isolated lifecycle plus P58 production and P32 verified-local admin transitions.
 *
 * The caller must authorize/validate status changes before invoking this method.
 * The isolated test DB or an explicitly verified local/production admin can activate it.
 */
final class ManagedOrderLifecycle
{
    /**
     * Atomically persist one validated status transition and its stock effect.
     * $persist is called with a fresh, locked Eloquent order and MUST NOT commit.
     * Stock release happens in the same transaction as the status/log write.
     *
     * @param Closure(Order):void $persist
     */
    public function transitionStatus(int $orderId, string $targetStatus, Closure $persist): void
    {
        $this->assertIsolatedEnabled();
        if ($orderId < 1 || $targetStatus === '') {
            throw new InvalidArgumentException('A persisted order and target status are required.');
        }

        try {
            DB::transaction(function () use ($orderId, $targetStatus, $persist): void {
                $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
                $managed = (int) $order->inventory_version === 1;
                $stockState = (int) ($order->storefront_stock_state ?? 0);
                $deferred = $stockState === StorefrontDeferredStock::RESERVED;
                // Invoice printing and Complete Invoice must not deduct stock
                // before the operator explicitly moves Processing to Confirmed.
                if ($deferred && $targetStatus === 'complete_invoice') {
                    throw ValidationException::withMessages([
                        'order_status' => 'Confirm this storefront order before moving it to Complete Invoice.',
                    ]);
                }

                if ($stockState === StorefrontDeferredStock::RELEASED
                    && ! in_array($targetStatus, ['cancelled', 'canceled', 'fake', 'stock_out', 'courier_cancelled'], true)) {
                    throw ValidationException::withMessages([
                        'order_status' => 'This cancelled/released storefront reservation cannot be reactivated.',
                    ]);
                }
                if ($deferred && ! in_array($targetStatus, [
                    'pending', 'processing', 'confirmed', 'complete_invoice',
                    'cancelled', 'canceled', 'fake', 'stock_out', 'courier_cancelled',
                ], true)) {
                    throw ValidationException::withMessages([
                        'order_status' => 'Complete this storefront order first so inventory is allocated before dispatch.',
                    ]);
                }

                if ($managed) {
                    $oldStatus = (string) $order->order_status;
                    // A cancelled order has already released its stock. Reinstating
                    // it without a new stock allocation is never allowed.
                    if (in_array($oldStatus, ['cancelled', 'canceled'], true)
                        && ! in_array($targetStatus, ['cancelled', 'canceled'], true)) {
                        throw ValidationException::withMessages([
                            'order_status' => 'A released managed order cannot be reactivated without a new reviewed allocation.',
                        ]);
                    }

                    if (in_array($targetStatus, ['cancelled', 'canceled'], true)) {
                        // Only explicitly pre-dispatch workflow statuses may
                        // release inventory, even if an old shipped_at is missing.
                        if (! in_array($oldStatus, ['pending', 'processing', 'confirmed', 'complete_invoice', 'cancelled', 'canceled'], true)) {
                            throw ValidationException::withMessages([
                                'order_status' => 'This order status cannot release reserved stock automatically.',
                            ]);
                        }
                        // Check dispatch state BEFORE mutating status. The allocator
                        // repeats the check under the same order lock.
                        InventoryPolicy::assertCanRelease(
                            'cancelled',
                            $order->shipped_at !== null,
                            $order->delivered_at !== null,
                            $order->steadfast_consignment_id !== null || $order->pathao_consignment_id !== null
                        );
                        if (! in_array($oldStatus, ['cancelled', 'canceled'], true)
                            && ! DB::table('order_items')->where('order_id', $orderId)
                                ->whereNull('deleted_at')->where('inventory_allocated_quantity', '>', 0)->exists()) {
                            throw new DomainException('Managed cancellation has no outstanding allocated stock to release.');
                        }
                    }
                }

                $persist($order);
                if ((string) $order->fresh()->order_status !== $targetStatus) {
                    throw new LogicException('Status writer did not persist the requested transition.');
                }

                if ($deferred) {
                    if ($targetStatus === 'confirmed') {
                        app(StorefrontDeferredStock::class)->finalize($orderId);
                    } elseif (in_array($targetStatus, [
                        'cancelled', 'canceled', 'fake', 'stock_out', 'courier_cancelled',
                    ], true)) {
                        // The physical stock was NEVER deducted; release only the
                        // pending availability hold. A subsequent retry can't reactivate it.
                        DB::table('orders')->where('id', $orderId)
                            ->where('storefront_stock_state', StorefrontDeferredStock::RESERVED)
                            ->update(['storefront_stock_state' => StorefrontDeferredStock::RELEASED]);
                    }
                }
                if ($managed && in_array($targetStatus, ['cancelled', 'canceled'], true)) {
                    app(InventoryAllocator::class)->releaseCancelledOrder($orderId, (string) Str::uuid());
                }
            }, 3); // Retry a transient lock deadlock with concurrent checkout.
        } catch (DomainException $exception) {
            // Business-rule denial is a normal 422 response, not a 500.
            throw ValidationException::withMessages(['order_status' => $exception->getMessage()]);
        }
    }

    /**
     * Stop legacy full-form editing from silently desynchronizing managed
     * order items from previously allocated warehouse stock.
     * Must be called before any other transaction-scoped business writes.
     */
    public function assertCanFullEdit(int $orderId): void
    {
        $this->assertIsolatedEnabled();
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Edit safety check must share the order transaction.');
        }
        $columns = ['id', 'inventory_version'];
        if (Schema::hasColumn('orders', 'storefront_stock_state')) $columns[] = 'storefront_stock_state';
        $order = DB::table('orders')->where('id', $orderId)->whereNull('deleted_at')
            ->lockForUpdate()->first($columns);
        if ($order === null) {
            throw new DomainException('Cannot edit an unknown/deleted order.');
        }
        if ((int) $order->inventory_version === 1
            || (int) ($order->storefront_stock_state ?? 0) !== StorefrontDeferredStock::LEGACY) {
            throw ValidationException::withMessages([
                'items' => 'Managed order item editing is locked until audited stock-delta reconciliation is enabled.',
            ]);
        }
    }

    /**
     * Keep managed stock/ledger references intact until a reviewed
     * deletion/restore/archive policy exists. Legacy rows remain editable.
     * Call inside the same transaction as the bulk/single mutation.
     *
     * @param array<int,int> $orderIds
     */
    public function assertCanDeleteOrRestore(array $orderIds): void
    {
        $this->assertIsolatedEnabled();
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Delete guard must share the mutation transaction.');
        }
        if ($orderIds === []) {
            return;
        }
        foreach ($orderIds as $orderId) {
            if (! is_int($orderId) || $orderId < 1) {
                throw new InvalidArgumentException('Order IDs must be positive integers.');
            }
        }
        $hasStockState = Schema::hasColumn('orders', 'storefront_stock_state');
        $managed = DB::table('orders')->whereIn('id', array_values(array_unique($orderIds)))
            ->where(function ($query) use ($hasStockState): void {
                $query->where('inventory_version', 1);
                if ($hasStockState) {
                    $query->orWhere('storefront_stock_state', '<>', StorefrontDeferredStock::LEGACY);
                }
            })->orderBy('id')->lockForUpdate()->first(['id']);
        if ($managed !== null) {
            throw ValidationException::withMessages([
                'orders' => 'Managed stock-linked orders cannot be deleted or restored until a reviewed archive policy exists.',
            ]);
        }
    }

    /** Existing admin status/edit/trash routes may manage allocated storefront stock. */
    public static function enabledForAdmin(): bool
    {
        return ManagedOrderWriter::enabled()
            || app(\App\Support\P58ProductionStorefrontAccess::class)->allowsAdminManaged()
            || app(\App\Support\P32LocalStorefrontAccess::class)->allowsAdminManaged();
    }

    private function assertIsolatedEnabled(): void
    {
        if (app(\App\Support\P58ProductionStorefrontAccess::class)->allowsAdminManaged()) {
            app(\App\Support\P58ProductionStorefrontAccess::class)->assertAdminManaged();
            return;
        }
        if (app(\App\Support\P32LocalStorefrontAccess::class)->allowsAdminManaged()) {
            app(\App\Support\P32LocalStorefrontAccess::class)->assertAdminManaged();
            return;
        }
        if (! ManagedOrderWriter::enabled() || config('storefront.p03_core_test_enabled', false) !== true) {
            throw new LogicException('P03 managed lifecycle remains disabled.');
        }
        P02MigrationSafety::assertIsolatedTestDatabase();
    }
}
