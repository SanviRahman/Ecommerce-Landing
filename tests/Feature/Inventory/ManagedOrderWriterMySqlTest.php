<?php

namespace Tests\Feature\Inventory;

use App\Services\Checkout\CheckoutIdempotencyService;
use App\Services\Inventory\InventoryAllocator;
use App\Services\Inventory\ManagedOrderWriter;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

require_once dirname(__DIR__, 3) . '/tools/qa/P03SyntheticFixtures.php';

/**
 * Real MariaDB integration: only the dedicated test schema is permitted.
 * Uses synthetic rows via Query Builder (not Eloquent observers) and rolls
 * back EVERYTHING in the DatabaseTransactions test transaction.
 * Never migrate, truncate, seed, send mail, or call external APIs.
 */
final class ManagedOrderWriterMySqlTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        $this->assertGreaterThan(0, DB::transactionLevel());
        config()->set('storefront.p03_core_test_enabled', true);
        config()->set('storefront.p03_order_writer_test_enabled', true);
    }

    protected function tearDown(): void
    {
        config()->set('storefront.p03_order_writer_test_enabled', false);
        config()->set('storefront.p03_core_test_enabled', false);
        parent::tearDown();
    }

    /** @return array{product:int,variant:int,order:int,item:int} */
    private function fixture(
        int $initialStock = 10,
        int $quantity = 2,
        string $source = 'frontend',
        string $unitPrice = '125',
        string $savedUnitPrice = '125.00',
        bool $campaign = false,
    ): array {
        return \P03SyntheticFixtures::create($initialStock, $quantity, $source, $unitPrice, $savedUnitPrice, $campaign);
    }

    private function stock(int $variantId): int
    {
        return (int) DB::table('product_variants')->where('id', $variantId)->value('stock');
    }

    private function allocationCount(int $itemId): int
    {
        return DB::table('inventory_movements')
            ->where('operation_key', 'allocate:v1:item:' . $itemId)->count();
    }

    public function test_frontend_allocation_changes_stock_and_persists_snapshot(): void
    {
        $f = $this->fixture();
        app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');

        $this->assertSame(8, $this->stock($f['variant']));
        $this->assertSame(8, (int) DB::table('products')->where('id', $f['product'])->value('stock'));
        $this->assertSame(1, $this->allocationCount($f['item']));
        $this->assertSame(2, (int) DB::table('order_items')->where('id', $f['item'])->value('inventory_allocated_quantity'));
        $this->assertSame($f['variant'], (int) DB::table('order_items')->where('id', $f['item'])->value('product_variant_id'));
        $this->assertSame(1, (int) DB::table('orders')->where('id', $f['order'])->value('inventory_version'));
    }

    public function test_admin_manual_allocation_uses_shared_writer(): void
    {
        $f = $this->fixture(source: 'admin_manual');
        app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'items');
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_admin_bulk_allocation_uses_shared_writer(): void
    {
        $f = $this->fixture(source: 'admin_bulk');
        app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'bulk_orders');
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_campaign_price_is_server_authoritative(): void
    {
        $f = $this->fixture(campaign: true);
        app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_oversell_rejects_without_decrement_or_allocation(): void
    {
        $f = $this->fixture(initialStock: 1, quantity: 2);
        try {
            app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
            $this->fail('Expected ValidationException for insufficient stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('products', $exception->errors());
        }
        $this->assertSame(1, $this->stock($f['variant']));
        $this->assertSame(0, $this->allocationCount($f['item']));
        $this->assertSame(0, (int) DB::table('orders')->where('id', $f['order'])->value('inventory_version'));
    }

    public function test_wrong_price_fails_without_stock_change(): void
    {
        $f = $this->fixture(savedUnitPrice: '75.00');
        try {
            app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
            $this->fail('Expected ValidationException for manipulated price.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('products', $exception->errors());
        }
        $this->assertSame(10, $this->stock($f['variant']));
    }

    public function test_replaying_verified_allocation_never_deducts_twice(): void
    {
        $f = $this->fixture();
        app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
        app(InventoryAllocator::class)->allocateOrder($f['order'], [$f['item'] => $f['variant']]);
        $this->assertSame(8, $this->stock($f['variant']));
        $this->assertSame(1, $this->allocationCount($f['item']));
    }

    public function test_legacy_product_is_denied_without_stock_change(): void
    {
        $f = $this->fixture();
        DB::table('products')->where('id', $f['product'])->update(['inventory_version' => 0]);
        try {
            app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
            $this->fail('Expected ValidationException for unreconciled stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('products', $exception->errors());
        }
        $this->assertSame(10, $this->stock($f['variant']));
    }

    public function test_explicit_pre_dispatch_release_returns_stock_only_once(): void
    {
        $f = $this->fixture();
        app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
        DB::table('orders')->where('id', $f['order'])->update(['order_status' => 'cancelled']);
        app(InventoryAllocator::class)->releaseCancelledOrder($f['order'], (string) Str::uuid());
        $this->assertSame(10, $this->stock($f['variant']));
        $this->assertSame(0, (int) DB::table('order_items')->where('id', $f['item'])->value('inventory_allocated_quantity'));
        $this->assertSame(1, DB::table('inventory_movements')->where('kind', 'release')->where('order_item_id', $f['item'])->count());
        app(InventoryAllocator::class)->releaseCancelledOrder($f['order'], (string) Str::uuid());
        $this->assertSame(10, $this->stock($f['variant']));
    }

    public function test_failed_allocation_rolls_back_entire_nested_transaction(): void
    {
        $before = DB::table('orders')->count();
        try {
            DB::transaction(function (): void {
                $f = $this->fixture(initialStock: 1, quantity: 2);
                app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
            });
            $this->fail('Expected failed stock allocation to roll back the transaction.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('products', $exception->errors());
        }
        $this->assertSame($before, DB::table('orders')->count());
    }

    public function test_duplicate_checkout_key_returns_original_order_after_completion(): void
    {
        $f = $this->fixture();
        $service = app(CheckoutIdempotencyService::class);
        $ownerHash = hash('sha256', 'p03-synthetic-owner-' . Str::uuid());
        $requestHash = hash('sha256', 'p03-synthetic-cart');
        $key = (string) Str::uuid();
        $claim = $service->claim($ownerHash, $key, $requestHash, 'storefront');
        $this->assertSame('new', $claim['state']);
        app(ManagedOrderWriter::class)->allocateCreatedOrder($f['order'], 'products');
        $service->complete($claim['checkout_request_id'], $f['order']);
        $replayed = $service->claim($ownerHash, $key, $requestHash, 'storefront');
        $this->assertSame('replayed', $replayed['state']);
        $this->assertSame($f['order'], $replayed['order_id']);
        $this->assertSame(1, DB::table('checkout_requests')->where('owner_hash', $ownerHash)->count());
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_checkout_key_fingerprint_change_is_rejected(): void
    {
        $service = app(CheckoutIdempotencyService::class);
        $ownerHash = hash('sha256', 'p03-synthetic-owner-' . Str::uuid());
        $key = (string) Str::uuid();
        $service->claim($ownerHash, $key, hash('sha256', 'original'), 'storefront');
        $this->expectException(\DomainException::class);
        $service->claim($ownerHash, $key, hash('sha256', 'modified'), 'storefront');
    }

}
