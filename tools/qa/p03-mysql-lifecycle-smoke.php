<?php

declare(strict_types=1);

/**
 * P03 lifecycle smoke: dedicated empty local MariaDB test schema ONLY.
 * Writes synthetic fixtures within per-case rollback transactions.
 * No migrations, external APIs, seeding or production settings.
 */

if (PHP_SAPI !== 'cli' || (string) getenv('P03_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: P03_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY is required.\n");
    exit(2);
}

$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: Set P02_TEST_DB_PASSWORD from restricted local secret file.\n");
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
    require $root . '/tests/bootstrap-p03.php';
    require_once __DIR__ . '/P03SyntheticFixtures.php';

    $app = require $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    \App\Support\P02MigrationSafety::assertIsolatedTestDatabase();
    config()->set('storefront.p03_core_test_enabled', true);
    config()->set('storefront.p03_order_writer_test_enabled', true);

    $db = \Illuminate\Support\Facades\DB::connection();
    $writer = app(\App\Services\Inventory\ManagedOrderWriter::class);
    $lifecycle = app(\App\Services\Inventory\ManagedOrderLifecycle::class);
    $passed = 0;
    $total = 11;

    $run = static function (string $name, callable $test) use ($db, &$passed): void {
        $db->beginTransaction();
        try {
            $test();
            echo 'PASS ' . $name . PHP_EOL;
            $passed++;
        } finally {
            $db->rollBack();
        }
        foreach (['products', 'orders', 'order_items', 'product_variants'] as $table) {
            if ((int) $db->table($table)->count() !== 0) {
                throw new RuntimeException('Synthetic fixture rollback did not clear ' . $table . '. STOP.');
            }
        }
    };
    $statusWrite = static function (string $target): \Closure {
        return static function (\App\Models\Order $order) use ($target): void {
            $order->update(['order_status' => $target]);
        };
    };
    $expectStatusError = static function (callable $operation): void {
        try {
            $operation();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if (! array_key_exists('order_status', $exception->errors())) {
                throw new RuntimeException('Missing order_status validation error.');
            }
            return;
        }
        throw new RuntimeException('Unsafe lifecycle transition was accepted.');
    };

    $run('Managed cancellation updates status and returns stock atomically', static function () use ($db, $writer, $lifecycle, $statusWrite): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled'));
        if ((string) $db->table('orders')->where('id', $f['order'])->value('order_status') !== 'cancelled'
            || (int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 10
            || (int) $db->table('products')->where('id', $f['product'])->value('stock') !== 10
            || (int) $db->table('inventory_movements')->where('order_item_id', $f['item'])->where('kind', 'release')->count() !== 1
            || (int) $db->table('order_items')->where('id', $f['item'])->value('inventory_allocated_quantity') !== 0) {
            throw new RuntimeException('Atomic cancellation is inconsistent.');
        }
    });

    $run('Repeated cancellation does not issue another credit', static function () use ($db, $writer, $lifecycle, $statusWrite): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled'));
        $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled'));
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 10
            || (int) $db->table('inventory_movements')->where('order_item_id', $f['item'])->where('kind', 'release')->count() !== 1) {
            throw new RuntimeException('Repeated cancellation credited twice.');
        }
    });

    $run('Cancelled managed order cannot reactivate without allocation', static function () use ($db, $writer, $lifecycle, $statusWrite, $expectStatusError): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled'));
        $expectStatusError(static fn () => $lifecycle->transitionStatus($f['order'], 'pending', $statusWrite('pending')));
        if ((string) $db->table('orders')->where('id', $f['order'])->value('order_status') !== 'cancelled'
            || (int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 10) {
            throw new RuntimeException('Cancelled order was reactivated.');
        }
    });

    $run('Previously shipped status rejects return even without shipped timestamp', static function () use ($db, $writer, $lifecycle, $statusWrite, $expectStatusError): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('orders')->where('id', $f['order'])->update(['order_status' => 'shipped']);
        $expectStatusError(static fn () => $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled')));
        if ((string) $db->table('orders')->where('id', $f['order'])->value('order_status') !== 'shipped'
            || (int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Shipped managed order was restocked.');
        }
    });

    $run('Courier-pending status rejects automatic stock release', static function () use ($db, $writer, $lifecycle, $statusWrite, $expectStatusError): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('orders')->where('id', $f['order'])->update(['order_status' => 'courier_pending']);
        $expectStatusError(static fn () => $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled')));
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Courier-pending inventory was released.');
        }
    });

    $run('Courier consignment identifier rejects automatic restock', static function () use ($db, $writer, $lifecycle, $statusWrite, $expectStatusError): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('orders')->where('id', $f['order'])->update(['steadfast_consignment_id' => 'synthetic-test-id']);
        $expectStatusError(static fn () => $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled')));
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Courier-registered order inventory was released.');
        }
    });

    $run('Persistence exception rolls back order status and stock', static function () use ($db, $writer, $lifecycle): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        try {
            $lifecycle->transitionStatus($f['order'], 'cancelled', static function (\App\Models\Order $order): void {
                $order->update(['order_status' => 'cancelled']);
                throw new RuntimeException('Synthetic status/log failure');
            });
            throw new RuntimeException('Expected persistence failure not observed.');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Synthetic status/log failure') {
                throw $exception;
            }
        }
        if ((string) $db->table('orders')->where('id', $f['order'])->value('order_status') !== 'pending'
            || (int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Persistence failure committed a partial state.');
        }
    });

    $run('Release validation failure rolls back changed status', static function () use ($db, $writer, $lifecycle, $statusWrite, $expectStatusError): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('order_items')->where('id', $f['item'])->update(['product_variant_id' => null]);
        $expectStatusError(static fn () => $lifecycle->transitionStatus($f['order'], 'cancelled', $statusWrite('cancelled')));
        if ((string) $db->table('orders')->where('id', $f['order'])->value('order_status') !== 'pending'
            || (int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Invalid release partially committed cancellation.');
        }
    });

    $run('Full edit rejects managed lines and preserves legacy allowance', static function () use ($db, $writer, $lifecycle): void {
        $f = P03SyntheticFixtures::create();
        $lifecycle->assertCanFullEdit($f['order']);
        $writer->allocateCreatedOrder($f['order'], 'products');
        try {
            $lifecycle->assertCanFullEdit($f['order']);
            throw new RuntimeException('Managed full edit was not blocked.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if (! array_key_exists('items', $exception->errors())) {
                throw new RuntimeException('Wrong managed edit error key.');
            }
        }
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8) {
            throw new RuntimeException('Edit guard mutated stock.');
        }
    });

    $run('Delete/restore guard blocks managed order and allows legacy order', static function () use ($writer, $lifecycle): void {
        $f = P03SyntheticFixtures::create();
        $lifecycle->assertCanDeleteOrRestore([$f['order']]);
        $writer->allocateCreatedOrder($f['order'], 'products');
        try {
            $lifecycle->assertCanDeleteOrRestore([$f['order']]);
            throw new RuntimeException('Managed deletion was not blocked.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if (! array_key_exists('orders', $exception->errors())) {
                throw new RuntimeException('Wrong managed deletion error key.');
            }
        }
    });

    $run('Bulk transaction rolls back all cancellations if one is unsafe', static function () use ($db, $writer, $lifecycle, $statusWrite, $expectStatusError): void {
        $a = P03SyntheticFixtures::create();
        $b = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($a['order'], 'products');
        $writer->allocateCreatedOrder($b['order'], 'products');
        $db->table('orders')->where('id', $b['order'])->update(['order_status' => 'shipped']);
        $expectStatusError(static function () use ($db, $a, $b, $lifecycle, $statusWrite): void {
            $db->transaction(static function () use ($a, $b, $lifecycle, $statusWrite): void {
                $lifecycle->transitionStatus($a['order'], 'cancelled', $statusWrite('cancelled'));
                $lifecycle->transitionStatus($b['order'], 'cancelled', $statusWrite('cancelled'));
            });
        });
        if ((string) $db->table('orders')->where('id', $a['order'])->value('order_status') !== 'pending'
            || (int) $db->table('product_variants')->where('id', $a['variant'])->value('stock') !== 8
            || (int) $db->table('inventory_movements')->where('order_item_id', $a['item'])->where('kind', 'release')->count() !== 0) {
            throw new RuntimeException('Invalid batch left a partial release.');
        }
    });

    echo 'RESULT: ' . $passed . '/' . $total . ' REAL MYSQL P03 LIFECYCLE CHECKS PASS (synthetic transactions rolled back).' . PHP_EOL;
    echo 'INFO: Test-only flags were set in this isolated CLI process. Public site unchanged.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P03 lifecycle: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (function_exists('config')) {
        try {
            config()->set('storefront.p03_order_writer_test_enabled', false);
            config()->set('storefront.p03_core_test_enabled', false);
        } catch (Throwable $ignored) {
            // Never replace the actual error with cleanup failure.
        }
    }
}
