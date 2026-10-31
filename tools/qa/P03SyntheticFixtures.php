<?php

declare(strict_types=1);

use App\Support\ExactBdt;
use App\Support\P02MigrationSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Synthetic-only P03 test fixtures. Never called from a web route or seeder.
 * Inserts are only allowed inside an existing rollback-managed MySQL transaction.
 */
final class P03SyntheticFixtures
{
    /** @return array{product:int,variant:int,order:int,item:int} */
    public static function create(
        int $initialStock = 10,
        int $quantity = 2,
        string $source = 'frontend',
        string $unitPrice = '125',
        string $savedUnitPrice = '125.00',
        bool $campaign = false,
    ): array {
        P02MigrationSafety::assertIsolatedTestDatabase();
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Synthetic fixtures require an enclosing rollback transaction.');
        }
        if ($initialStock < 0 || $initialStock > 1000 || $quantity < 1 || $quantity > 1000) {
            throw new InvalidArgumentException('Test stock and quantities must be limited to 0-1000.');
        }

        $key = strtolower(Str::random(15));
        $now = now();
        $categoryId = (int) DB::table('categories')->insertGetId([
            'name' => 'P03 synthetic category', 'slug' => 'p03-cat-' . $key,
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $productId = (int) DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'name' => 'P03 synthetic product',
            'slug' => 'p03-prod-' . $key, 'product_code' => 'P03-' . strtoupper($key),
            'new_price' => $unitPrice, 'stock' => $initialStock,
            'status' => 1, 'has_variants' => 0, 'inventory_version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $variantId = (int) DB::table('product_variants')->insertGetId([
            'product_id' => $productId, 'sku' => 'P03-SKU-' . strtoupper($key),
            'option_signature' => 'default', 'is_default' => 1, 'is_active' => 1,
            'stock' => $initialStock, 'price_override' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('inventory_movements')->insert([
            'product_variant_id' => $variantId, 'event_uuid' => (string) Str::uuid(),
            'operation_key' => 'opening:v1:product:' . $productId,
            'kind' => 'opening', 'quantity_delta' => $initialStock,
            'stock_before' => 0, 'stock_after' => $initialStock,
            'reason' => 'Synthetic rollback-only test opening', 'created_at' => $now,
        ]);
        $campaignId = null;
        if ($campaign) {
            $campaignId = (int) DB::table('campaigns')->insertGetId([
                'title' => 'P03 synthetic campaign', 'slug' => 'p03-campaign-' . $key,
                'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('campaign_product')->insert([
                'campaign_id' => $campaignId, 'product_id' => $productId,
                'campaign_price' => $unitPrice, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $savedLine = ExactBdt::toDecimal(ExactBdt::multiply(ExactBdt::fromDecimal($savedUnitPrice), $quantity));
        $orderId = (int) DB::table('orders')->insertGetId([
            'invoice_id' => 'P03-T-' . strtoupper($key),
            'campaign_id' => $campaignId,
            'customer_name' => 'Synthetic Buyer', 'phone' => '01700000000',
            'address' => 'Synthetic rollback-only address',
            'created_via' => $source, 'inventory_version' => 0,
            'sub_total' => $savedLine, 'total_amount' => $savedLine,
            'order_status' => 'pending',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $itemId = (int) DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_id' => $productId,
            'product_name' => 'P03 synthetic product', 'quantity' => $quantity,
            'unit_price' => $savedUnitPrice, 'total_price' => $savedLine,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['product' => $productId, 'variant' => $variantId, 'order' => $orderId, 'item' => $itemId];
    }
}
