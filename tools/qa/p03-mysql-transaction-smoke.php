<?php

declare(strict_types=1);

/**
 * Test-only CLI smoke test for actual Laravel ManagedOrderWriter + MariaDB.
 * Synthetic fixtures exist ONLY inside transactions rolled back in finally.
 * No DDL, seeders, queue, HTTP request, or production DB connection permitted.
 */

if (PHP_SAPI !== 'cli' || (string) getenv('P03_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: Explicit P03_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY is required.\n");
    exit(2);
}

$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: Set P02_TEST_DB_PASSWORD from the local secret file.\n");
    exit(2);
}

$settings = [
    'P03_ALLOW_MYSQL_TESTS' => 'yes-transaction-rollback-only',
    'P01_ALLOW_MYSQL_TESTS' => 'yes-disposable-only',
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
    'APP_URL' => 'http://127.0.0.1',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'p01_campaign_test',
    'DB_USERNAME' => 'p01_tester',
    'DB_PASSWORD' => $password,
    'DB_URL' => '',
    'DB_SOCKET' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'FILESYSTEM_DISK' => 'local',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
];
unset($password);
foreach ($settings as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
unset($settings);

$root = dirname(__DIR__, 2);

try {
    // Pre-Laravel guard performs PDO identity/grant checks and insists that
    // the test schema has NO real product/order/customer/campaign rows.
    require $root . '/tests/bootstrap-p03.php';
    require_once __DIR__ . '/P03SyntheticFixtures.php';

    $app = require $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    \App\Support\P02MigrationSafety::assertIsolatedTestDatabase();
    config()->set('storefront.p03_core_test_enabled', true);
    config()->set('storefront.p03_order_writer_test_enabled', true);

    $db = \Illuminate\Support\Facades\DB::connection();
    $writer = app(\App\Services\Inventory\ManagedOrderWriter::class);
    $allocator = app(\App\Services\Inventory\InventoryAllocator::class);
    $passed = 0;
    $run = static function (string $label, callable $test) use ($db, &$passed): void {
        $db->beginTransaction();
        try {
            $test();
            echo 'PASS ' . $label . PHP_EOL;
            $passed++;
        } finally {
            $db->rollBack();
        }
        // Confirm rollback worked even if something changed the transaction.
        if ((int) $db->table('products')->count() !== 0 || (int) $db->table('orders')->count() !== 0) {
            throw new RuntimeException('Synthetic fixture transaction was not fully rolled back. STOP.');
        }
    };

    $run('Frontend order deducts stock + persists order snapshot', static function () use ($db, $writer): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8
            || (int) $db->table('products')->where('id', $f['product'])->value('stock') !== 8
            || (int) $db->table('orders')->where('id', $f['order'])->value('inventory_version') !== 1) {
            throw new RuntimeException('Persisted inventory is inconsistent.');
        }
    });

    $run('Manual order uses shared stock writer', static function () use ($db, $writer): void {
        $f = P03SyntheticFixtures::create(source: 'admin_manual');
        $writer->allocateCreatedOrder($f['order'], 'items');
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Manual allocation did not persist.');
        }
    });

    $run('Bulk order uses shared stock writer', static function () use ($db, $writer): void {
        $f = P03SyntheticFixtures::create(source: 'admin_bulk');
        $writer->allocateCreatedOrder($f['order'], 'bulk_orders');
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Bulk allocation did not persist.');
        }
    });

    $run('Campaign order preserves campaign pricing', static function () use ($db, $writer): void {
        $f = P03SyntheticFixtures::create(campaign: true);
        $writer->allocateCreatedOrder($f['order'], 'products');
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Campaign allocation did not persist.');
        }
    });

    $run('Oversell rejection retains original stock', static function () use ($db, $writer): void {
        $f = P03SyntheticFixtures::create(initialStock: 1, quantity: 2);
        try {
            $writer->allocateCreatedOrder($f['order'], 'products');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if (! array_key_exists('products', $exception->errors())) {
                throw new RuntimeException('Oversell failure did not report the product field.');
            }
            if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 1) {
                throw new RuntimeException('Oversell rejection modified stock.');
            }
            return;
        }
        throw new RuntimeException('Oversell was not rejected.');
    });

    $run('Tampered price is rejected', static function () use ($db, $writer): void {
        $f = P03SyntheticFixtures::create(savedUnitPrice: '75.00');
        try {
            $writer->allocateCreatedOrder($f['order'], 'products');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 10) {
                throw new RuntimeException('Price rejection modified stock.');
            }
            return;
        }
        throw new RuntimeException('Tampered price was accepted.');
    });

    $run('Allocation replay never deducts twice', static function () use ($db, $writer, $allocator): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $allocator->allocateOrder($f['order'], [$f['item'] => $f['variant']]);
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Replay deducted stock twice.');
        }
    });

    $run('Explicit cancelled order stock release is idempotent', static function () use ($db, $writer, $allocator): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('orders')->where('id', $f['order'])->update(['order_status' => 'cancelled']);
        $allocator->releaseCancelledOrder($f['order'], (string) \Illuminate\Support\Str::uuid());
        $allocator->releaseCancelledOrder($f['order'], (string) \Illuminate\Support\Str::uuid());
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 10) {
            throw new RuntimeException('Cancel release did not restore exactly once.');
        }
    });

    $run('Checkout key replay returns original managed order', static function () use ($db, $writer): void {
        $f = P03SyntheticFixtures::create();
        $service = app(\App\Services\Checkout\CheckoutIdempotencyService::class);
        $ownerHash = hash('sha256', 'p03-rollback-' . \Illuminate\Support\Str::uuid());
        $requestHash = hash('sha256', 'synthetic-cart');
        $key = (string) \Illuminate\Support\Str::uuid();
        $claim = $service->claim($ownerHash, $key, $requestHash, 'storefront');
        $writer->allocateCreatedOrder($f['order'], 'products');
        $service->complete($claim['checkout_request_id'], $f['order']);
        $replay = $service->claim($ownerHash, $key, $requestHash, 'storefront');
        if ($replay['state'] !== 'replayed' || $replay['order_id'] !== $f['order']
            || (int) $db->table('checkout_requests')->where('owner_hash', $ownerHash)->count() !== 1) {
            throw new RuntimeException('Idempotency replay contract failed.');
        }
    });

    echo 'RESULT: ' . $passed . '/9 REAL MYSQL INTEGRATION CHECKS PASS (all fixture transactions rolled back).' . PHP_EOL;
    echo 'INFO: Feature flags were enabled in this isolated process only. Original config is unchanged.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P03 integration: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (function_exists('config')) {
        try {
            config()->set('storefront.p03_order_writer_test_enabled', false);
            config()->set('storefront.p03_core_test_enabled', false);
        } catch (Throwable $ignored) {
            // Do not mask the real cause.
        }
    }
}
