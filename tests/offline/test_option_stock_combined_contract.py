"""Offline source/logic guardrails for Option-wise Pricing and Stock Management.
These checks do not replace Laravel + MySQL concurrency and browser tests.
"""
from pathlib import Path
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]


def code(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


class UnifiedOptionStockContracts(unittest.TestCase):
    def test_matrix_eight_combinations_and_invalid_selection(self):
        php = r'''require "app/Services/Catalog/OptionWiseMatrix.php";
use App\Services\Catalog\OptionWiseMatrix as M;
$rows=M::combinations(["color"=>[1,2],"size"=>[3,4],"weight"=>[5],"piece_count"=>[6,7]]);
if(count($rows)!==8 || $rows[0]!==["color"=>1,"size"=>3,"weight"=>5,"piece_count"=>6]) exit(1);
try { M::combinations(["color"=>[1,1]]); exit(2); } catch (InvalidArgumentException $e) {}
try { M::combinations(["color"=>range(1,20),"size"=>range(1,20)]); exit(3); } catch (InvalidArgumentException $e) {}
echo "OK\n";'''
        result = subprocess.run(['php', '-r', php], cwd=ROOT, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr + result.stdout)
        self.assertEqual(result.stdout.strip(), 'OK')

    def test_price_only_piece_count_from_server(self):
        src = code('app/Http/Controllers/Admin/OptionWisePriceController.php')
        for t in ('P33CatalogAdminAccess::assertAllowed()',
                  "isset($selected['piece_count'])", 'OptionWiseMatrix::combinations($selected)',
                  "'price_override' => $prices[(int) $pieceId]", 'createVariant($locked,',
                  'price_override', 'DB::transaction'):
            # price expression is generated via variable, not direct quoted expression
            if t == "'price_override' => $prices[(int) $pieceId]":
                self.assertIn("$variant->update(['price_override' => $prices[(int) $pieceId]])", src)
            else:
                self.assertIn(t, src)
        self.assertIn('$newRequired', src)
        self.assertIn('200', src)

    def test_new_skus_start_zero_stock_and_use_existing_ledger(self):
        src = code('app/Services/Catalog/VariantCatalogManager.php')
        self.assertIn("'stock' => 0", src)
        self.assertIn('recordMovement($variant,', src)
        self.assertIn('adjustStock($locked, $row', src)
        self.assertIn('unconfirmed orders', src)

    def test_stock_management_admin_routes(self):
        routes = code('routes/admin.php')
        for marker in ('StockManagementController', "prefix('stock')", "middleware('role:admin')",
                       "name('purchase')", "name('exact')", "name('purchase.reverse')"):
            self.assertIn(marker, routes)
        page = code('resources/views/admin/stock/index.blade.php')
        for marker in ('@csrf', "@method('PATCH')", 'Stock Management',
                       'Add New Purchase', 'Set Exact Stock', 'Reverse*', 'Recent Stock Movement History'):
            self.assertIn(marker, page)

    def test_stock_controller_uses_same_catalog_writer(self):
        src = code('app/Http/Controllers/Admin/StockManagementController.php')
        for marker in ('P33CatalogAdminAccess::assertAllowed()',
                       '$catalog->adjustStock(', '$catalog->setStockQuantity(',
                       "'expected_stock'", 'lockForUpdate()', 'Reversed unused Stock Management purchase'):
            self.assertIn(marker, src)
        self.assertNotIn("DB::table('product_variants')->update(['stock'", src)

    def test_campaign_managed_orders_reserve_until_confirmed(self):
        controller = code('app/Http/Controllers/Admin/CampaignOrderController.php')
        allocator = code('app/Services/Inventory/CampaignOrderInventoryAllocator.php')
        self.assertIn('$deferManagedStock = $allManaged && $approvedDatabase;', controller)
        self.assertIn('->allocate((int) $order->id, true)', controller)
        self.assertIn('if ($deferUntilConfirmed)', allocator)
        self.assertIn('StorefrontDeferredStock::RESERVED', allocator)
        self.assertIn('return;', allocator.split('if ($deferUntilConfirmed)', 1)[1].split('foreach ($needed as', 1)[0])
        self.assertIn('ManagedOrderWriter::class)->allocateCreatedOrder(', controller)

    def test_confirmation_allocates_once_and_reserved_cancellation_releases_hold(self):
        confirm = code('app/Services/Inventory/StorefrontDeferredStock.php')
        lifecycle = code('app/Services/Inventory/ManagedOrderLifecycle.php')
        self.assertIn("$order->order_status !== 'confirmed'", confirm)
        self.assertIn('self::RESERVED', confirm)
        self.assertIn("'allocate:v1:item:'", confirm)
        self.assertIn("'storefront_stock_state' => self::ALLOCATED", confirm)
        self.assertIn('Campaign', confirm) if 'Campaign' in confirm else self.assertIn('campaign allocation', confirm)
        self.assertIn("$targetStatus === 'confirmed'", lifecycle)
        self.assertIn('StorefrontDeferredStock::RELEASED', lifecycle)
        self.assertIn('->releaseCancelledOrder(', lifecycle)

    def test_public_available_excludes_pending_holds_but_physical_shows_separately(self):
        deferred = code('app/Services/Inventory/StorefrontDeferredStock.php')
        self.assertIn("->where('o.storefront_stock_state', self::RESERVED)", deferred)
        self.assertIn('max(0, $physicalStock -', deferred)
        catalog = code('app/Services/Storefront/PublicCatalog.php')
        self.assertIn("'physical_stock'", catalog)

    def test_campaign_finalization_actually_accepts_campaign_orders(self):
        src = code('app/Services/Inventory/StorefrontDeferredStock.php')
        allowed = src.split('public function finalize(', 1)[1].split('$items = ', 1)[0]
        self.assertNotIn("$order->campaign_id !== null", allowed)
        self.assertIn("$order->order_status !== 'confirmed'", allowed)
        self.assertIn('storefront_stock_state', allowed)
        self.assertIn('inventory_version', allowed)

    def test_campaign_reserved_trash_restore_recovers_hold_capacity(self):
        src = code('app/Services/Orders/ManagedOrderTrashService.php')
        restored = src.split('private function assertRestorableReservationCapacity(', 1)[1]
        self.assertNotIn("$order->campaign_id !== null", restored)
        self.assertIn("->where('o.storefront_stock_state', StorefrontDeferredStock::RESERVED)", restored)
        self.assertIn("->lockForUpdate()->get", restored)

    def test_incomplete_campaign_order_cannot_be_created(self):
        src = code('app/Http/Controllers/Admin/CampaignOrderController.php')
        self.assertIn("$deferManagedStock && count($orderItems) > 30", src)
        self.assertIn('->allocate((int) $order->id, true)', src)
        self.assertIn("'order_status'      => Order::STATUS_PROCESSING", src)

    def test_unselected_piece_count_price_can_remain_empty(self):
        src = code('app/Http/Controllers/Admin/OptionWisePriceController.php')
        self.assertIn("'prices.*' => ['nullable'", src)
        self.assertIn("$data['prices'][$id]", src)
        self.assertIn('Every selected Piece Count requires a positive price.', src)

    def test_no_duplicate_inventory_ledger_lookup(self):
        src = code('app/Services/Catalog/VariantCatalogManager.php')
        self.assertNotIn("->first();            $last", src)
        self.assertIn("Stock cannot be lowered below quantities held", src)

    def test_existing_legacy_and_frontend_routes_are_not_replaced(self):
        routes = code('routes/admin.php')
        self.assertIn("[ProductController::class, 'store']", routes)
        self.assertIn("[ProductVariantController::class, 'index']", routes)
        self.assertIn("[OrderController::class, 'updateStatus']", routes)
        assert 'route(' in code('resources/views/storefront/pages/product.blade.php')


if __name__ == '__main__':
    unittest.main(verbosity=2)
