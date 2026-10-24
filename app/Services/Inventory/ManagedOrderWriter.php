<?php

namespace App\Services\Inventory;

use App\Support\P02MigrationSafety;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

/**
 * P03 ISOLATED INTEGRATION ONLY (no production enablement).
 * Called inside existing campaign/manual/bulk order transactions.
 * Flag-off behavior is a no-op; flag-on requires a restricted test database.
 * Existing order writers must not also modify stock on the same path.
 */
final class ManagedOrderWriter
{
    public static function enabled(): bool
    {
        return config('storefront.p03_order_writer_test_enabled', false) === true;
    }

    /** Call at the top of each order transaction, BEFORE any business-data writes. */
    public function assertTransactionReady(): void
    {
        if (! self::enabled()) {
            return;
        }
        if (config('storefront.p03_core_test_enabled', false) !== true) {
            throw new LogicException('Managed order writers require the P03 test-only core flag.');
        }
        P02MigrationSafety::assertIsolatedTestDatabase();
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Order creation and stock deduction require the same transaction.');
        }
    }

    /**
     * Call only after every OrderItem is persisted, before commit/redirect.
     * Failures throw to roll back the ENTIRE order/customer/batch transaction.
     */
    public function allocateCreatedOrder(int $orderId, string $errorField): void
    {
        if (! self::enabled()) {
            return;
        }
        $this->assertTransactionReady();

        if ($orderId < 1 || ! in_array($errorField, ['products', 'items', 'bulk_orders'], true)) {
            throw new InvalidArgumentException('Invalid order identity or validation field.');
        }

        $order = DB::table('orders')->where('id', $orderId)->first(['id', 'created_via', 'inventory_version']);
        if ($order === null || ! in_array((string) $order->created_via, [
            'frontend', 'admin_manual', 'employee_manual', 'admin_bulk', 'employee_bulk',
        ], true) || (int) $order->inventory_version !== 0) {
            throw new LogicException('This order cannot enter the initial managed-inventory path.');
        }

        $items = DB::table('order_items')->where('order_id', $orderId)->whereNull('deleted_at')
            ->orderBy('id')->get(['id', 'product_id']);
        $itemsById = [];
        $productIds = [];
        foreach ($items as $item) {
            $productId = (int) ($item->product_id ?? 0);
            $itemId = (int) $item->id;
            $itemsById[$itemId] = ['product_id' => $productId];
            $productIds[$productId] = true;
        }

        // All defaults, including inactive/deleted, are intentionally inspected:
        // ambiguous state must be rejected rather than silently picking a variant.
        $variants = DB::table('product_variants')->whereIn('product_id', array_keys($productIds))
            ->where('is_default', true)->orderBy('product_id')->orderBy('id')
            ->get(['id', 'product_id', 'option_signature', 'is_default', 'is_active', 'deleted_at']);
        $variantRows = $variants->map(static fn ($variant) => [
            'id' => (int) $variant->id,
            'product_id' => (int) $variant->product_id,
            'option_signature' => (string) $variant->option_signature,
            'is_default' => (bool) $variant->is_default,
            'is_active' => (bool) $variant->is_active,
            'deleted_at' => $variant->deleted_at,
        ])->all();

        try {
            $mapping = DefaultVariantMapping::resolve($itemsById, $variantRows);
            app(InventoryAllocator::class)->allocateOrder($orderId, $mapping);
        } catch (DomainException|InvalidArgumentException $exception) {
            // Error is intentionally attached to the original form field. A
            // Laravel ValidationException ensures no partial checkout commits.
            throw ValidationException::withMessages([$errorField => $exception->getMessage()]);
        }
    }
}
