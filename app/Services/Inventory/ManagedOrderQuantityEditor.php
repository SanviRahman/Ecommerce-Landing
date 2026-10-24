<?php

namespace App\Services\Inventory;

use App\Support\ExactBdt;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use OverflowException;

/**
 * P03 TEST-SCHEMA ONLY: edit the quantity of an already allocated simple-product
 * order item without opening the unsafe legacy full-order editing path.
 *
 * Locks: order -> item -> product -> variant, matching the allocator's stock
 * lock direction. Order price snapshots are preserved (never client-priced).
 * Each write and ledger event commits or rolls back as one transaction.
 */
final class ManagedOrderQuantityEditor
{
    public function changeQuantity(int $orderId, int $itemId, int $expectedQuantity, int $newQuantity): void
    {
        if ($orderId < 1 || $itemId < 1 || $expectedQuantity < 1) {
            throw new InvalidArgumentException('Valid order, item and expected quantity are required.');
        }
        InventoryPolicy::assertPurchasable(1000, $newQuantity);

        try {
            DB::transaction(function () use ($orderId, $itemId, $expectedQuantity, $newQuantity): void {
                if (! ManagedOrderWriter::enabled()
                    || config('storefront.p03_core_test_enabled', false) !== true) {
                    throw new LogicException('P03 quantity editing is disabled.');
                }
                app(ManagedOrderWriter::class)->assertTransactionReady();

                $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
                if ($order === null || $order->deleted_at !== null || (int) $order->inventory_version !== 1) {
                    throw new DomainException('Only existing managed orders can use quantity reconciliation.');
                }
                if (! in_array((string) $order->created_via, ['frontend', 'admin_manual', 'employee_manual'], true)) {
                    throw new DomainException('Negotiated bulk and external orders need a separate review policy.');
                }
                if (! in_array((string) $order->order_status, ['pending', 'processing', 'confirmed', 'complete_invoice'], true)
                    || $order->shipped_at !== null || $order->delivered_at !== null
                    || $order->steadfast_consignment_id !== null || $order->pathao_consignment_id !== null
                    || (bool) $order->is_fake || (string) $order->payment_status === 'collected') {
                    throw new DomainException('Dispatched, cancelled, fake or collected orders cannot be quantity-edited.');
                }

                $item = DB::table('order_items')->where('id', $itemId)->where('order_id', $orderId)
                    ->whereNull('deleted_at')->lockForUpdate()->first();
                if ($item === null || (int) $item->quantity !== $expectedQuantity) {
                    throw new DomainException('The order item changed since this form was opened. Reload before editing.');
                }
                if ($item->product_id === null || $item->product_variant_id === null
                    || (int) $item->inventory_allocated_quantity !== $expectedQuantity) {
                    throw new DomainException('The order item is not fully linked to allocated stock.');
                }

                $product = DB::table('products')->where('id', (int) $item->product_id)->lockForUpdate()->first();
                $variant = DB::table('product_variants')->where('id', (int) $item->product_variant_id)
                    ->lockForUpdate()->first();
                if ($product === null || $variant === null || $product->deleted_at !== null
                    || (int) $product->inventory_version !== 1 || (bool) $product->has_variants
                    || (int) $variant->product_id !== (int) $product->id || $variant->deleted_at !== null
                    || ! (bool) $variant->is_default || ! (bool) $variant->is_active
                    || (string) $variant->option_signature !== 'default') {
                    throw new DomainException('This product requires variant/stock reconciliation before editing.');
                }
                if ((int) DB::table('product_variants')->where('product_id', $product->id)->count() !== 1
                    || (int) $product->stock !== (int) $variant->stock) {
                    throw new DomainException('The simple-product stock mirror is inconsistent.');
                }
                if (! DB::table('inventory_movements')
                    ->where('operation_key', 'allocate:v1:item:' . $itemId)
                    ->where('product_variant_id', $variant->id)->exists()
                    || DB::table('inventory_movements')->where('order_item_id', $itemId)
                        ->where('product_variant_id', '!=', $variant->id)->exists()
                    || (int) DB::table('inventory_movements')->where('order_item_id', $itemId)
                        ->sum('quantity_delta') !== -$expectedQuantity) {
                    throw new DomainException('The order item ledger is not balanced with its allocated quantity.');
                }

                // Reject corrupted snapshots and totals, including unrelated lines,
                // before changing either warehouse inventory or customer amounts.
                $items = DB::table('order_items')->where('order_id', $orderId)
                    ->whereNull('deleted_at')->orderBy('id')->get();
                $oldSubtotal = 0;
                foreach ($items as $line) {
                    $lineQuantity = (int) $line->quantity;
                    InventoryPolicy::assertPurchasable(1000, $lineQuantity);
                    if ((int) $line->inventory_allocated_quantity !== $lineQuantity
                        || $line->product_variant_id === null
                        || ExactBdt::fromDecimal((string) ($line->discount_amount ?? '0')) !== 0) {
                        throw new DomainException('One or more order lines have unreconciled allocation/discounts.');
                    }
                    $expectedLine = ExactBdt::multiply(ExactBdt::fromDecimal((string) $line->unit_price), $lineQuantity);
                    if (ExactBdt::fromDecimal((string) $line->total_price) !== $expectedLine) {
                        throw new DomainException('An order-line money snapshot is inconsistent.');
                    }
                    $oldSubtotal = ExactBdt::add($oldSubtotal, $expectedLine);
                }
                $shipping = ExactBdt::fromDecimal((string) ($order->shipping_charge ?? '0'));
                $cod = ExactBdt::fromDecimal((string) ($order->cod_charge ?? '0'));
                if ($oldSubtotal !== ExactBdt::fromDecimal((string) $order->sub_total)
                    || ExactBdt::add($oldSubtotal, $shipping, $cod)
                        !== ExactBdt::fromDecimal((string) $order->total_amount)) {
                    throw new DomainException('Order totals cannot be reconciled safely.');
                }

                // Stale duplicate submissions fail above; unchanged requests write nothing.
                if ($expectedQuantity === $newQuantity) {
                    return;
                }
                $unit = ExactBdt::fromDecimal((string) $item->unit_price);
                if ($unit === 0) {
                    throw new DomainException('An existing zero-price managed item needs price review.');
                }
                $oldLine = ExactBdt::multiply($unit, $expectedQuantity);
                $newLine = ExactBdt::multiply($unit, $newQuantity);
                $newSubtotal = ExactBdt::add(ExactBdt::subtract($oldSubtotal, $oldLine), $newLine);
                $newTotal = ExactBdt::add($newSubtotal, $shipping, $cod);

                $stockBefore = (int) $variant->stock;
                $delta = $newQuantity - $expectedQuantity;
                if ($delta > 0) {
                    InventoryPolicy::assertPurchasable($stockBefore, $delta);
                    $affected = DB::table('product_variants')->where('id', $variant->id)
                        ->where('stock', '>=', $delta)->decrement('stock', $delta);
                    if ($affected !== 1) {
                        throw new DomainException('Concurrent stock change rejected. Reload and retry.');
                    }
                } else {
                    $restore = -$delta;
                    if ($stockBefore > 4_294_967_295 - $restore) {
                        throw new DomainException('Stock return would overflow the inventory column.');
                    }
                    DB::table('product_variants')->where('id', $variant->id)->increment('stock', $restore);
                }
                $stockAfter = $stockBefore - $delta;

                DB::table('products')->where('id', $product->id)->update([
                    'stock' => $stockAfter, 'updated_at' => now(),
                ]);
                DB::table('order_items')->where('id', $itemId)->update([
                    'quantity' => $newQuantity,
                    'total_price' => ExactBdt::toDecimal($newLine),
                    'inventory_allocated_quantity' => $newQuantity,
                    'updated_at' => now(),
                ]);
                DB::table('orders')->where('id', $orderId)->update([
                    'sub_total' => ExactBdt::toDecimal($newSubtotal),
                    'total_amount' => ExactBdt::toDecimal($newTotal),
                    'updated_at' => now(),
                ]);
                $eventId = (string) Str::uuid();
                DB::table('inventory_movements')->insert([
                    'product_variant_id' => $variant->id,
                    'order_item_id' => $itemId,
                    'event_uuid' => $eventId,
                    'operation_key' => 'quantity:v1:item:' . $itemId . ':' . $eventId,
                    'kind' => 'adjustment',
                    'quantity_delta' => -$delta,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'actor_id' => auth()->id(),
                    'reason' => 'Authorized pre-dispatch managed item quantity change',
                    'metadata' => json_encode([
                        'order_id' => $orderId,
                        'old_quantity' => $expectedQuantity,
                        'new_quantity' => $newQuantity,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }, 3);
        } catch (DomainException|InvalidArgumentException|OverflowException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }
    }
}
