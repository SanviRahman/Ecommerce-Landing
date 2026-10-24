<?php

declare(strict_types=1);

/**
 * P03 quantity-edit smoke: dedicated empty local MariaDB test schema ONLY.
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
    $editor = app(\App\Services\Inventory\ManagedOrderQuantityEditor::class);
    $lifecycle = app(\App\Services\Inventory\ManagedOrderLifecycle::class);
    $passed = 0;
    $total = 16;

    $run = static function (string $name, callable $test) use ($db, &$passed): void {
        $db->beginTransaction();
        try {
            $test();
            echo 'PASS ' . $name . PHP_EOL;
            $passed++;
        } finally {
            $db->rollBack();
        }
        foreach (['products', 'orders', 'order_items', 'product_variants', 'inventory_movements'] as $table) {
            if ((int) $db->table($table)->count() !== 0) {
                throw new RuntimeException('Rollback left synthetic records in ' . $table . '. STOP.');
            }
        }
    };
    $expectRejected = static function (callable $operation): void {
        try {
            $operation();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if (! array_key_exists('quantity', $exception->errors())) {
                throw new RuntimeException('Rejection was not attached to the quantity field.');
            }
            return;
        }
        throw new RuntimeException('Unsafe quantity edit was accepted.');
    };

    $run('Increasing quantity updates stock, mirror, snapshots and ledger', static function () use ($db, $writer, $editor): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $editor->changeQuantity($f['order'], $f['item'], 2, 4);
        $item = $db->table('order_items')->find($f['item']);
        $order = $db->table('orders')->find($f['order']);
        if ((int) $item->quantity !== 4 || (int) $item->inventory_allocated_quantity !== 4
            || (string) $item->total_price !== '500.00' || (string) $order->sub_total !== '500.00'
            || (string) $order->total_amount !== '500.00'
            || (int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 6
            || (int) $db->table('products')->where('id', $f['product'])->value('stock') !== 6
            || (int) $db->table('inventory_movements')->where('order_item_id', $f['item'])->sum('quantity_delta') !== -4) {
            throw new RuntimeException('Increasing quantity corrupted an inventory/order snapshot.');
        }
    });

    $run('Decreasing quantity returns only the approved delta', static function () use ($db, $writer, $editor): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $editor->changeQuantity($f['order'], $f['item'], 2, 1);
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 9
            || (string) $db->table('orders')->where('id', $f['order'])->value('total_amount') !== '125.00') {
            throw new RuntimeException('Stock return or monetary total incorrect.');
        }
    });

    $run('No-op quantity produces no ledger write', static function () use ($db, $writer, $editor): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $editor->changeQuantity($f['order'], $f['item'], 2, 2);
        if ((int) $db->table('inventory_movements')->where('order_item_id', $f['item'])->count() !== 1) {
            throw new RuntimeException('No-op generated duplicate ledger movement.');
        }
    });

    $run('Stale duplicate submission rejects second stock deduction', static function () use ($db, $writer, $editor, $expectRejected): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $editor->changeQuantity($f['order'], $f['item'], 2, 3);
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 3));
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 7) {
            throw new RuntimeException('Stale form deducted stock twice.');
        }
    });

    $run('Insufficient stock rejects and preserves money and inventory', static function () use ($db, $writer, $editor, $expectRejected): void {
        $f = P03SyntheticFixtures::create(initialStock: 2, quantity: 2);
        $writer->allocateCreatedOrder($f['order'], 'products');
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 3));
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 0
            || (int) $db->table('order_items')->where('id', $f['item'])->value('quantity') !== 2
            || (string) $db->table('orders')->where('id', $f['order'])->value('total_amount') !== '250.00') {
            throw new RuntimeException('Oversell rejection modified the order.');
        }
    });

    $run('Shipped status is rejected', static function () use ($db, $writer, $editor, $expectRejected): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('orders')->where('id', $f['order'])->update(['order_status' => 'shipped']);
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 3));
    });

    $run('Courier consignment blocks post-dispatch edit', static function () use ($db, $writer, $editor, $expectRejected): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('orders')->where('id', $f['order'])->update(['steadfast_consignment_id' => 'test-synthetic-id']);
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 1));
    });

    $run('Cancelled order cannot be edited', static function () use ($writer, $editor, $lifecycle, $expectRejected): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $lifecycle->transitionStatus($f['order'], 'cancelled', static function (\App\Models\Order $order): void {
            $order->update(['order_status' => 'cancelled']);
        });
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 3));
    });

    $run('Corrupt ledger refuses quantity changes', static function () use ($db, $writer, $editor, $expectRejected): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('inventory_movements')->where('operation_key', 'allocate:v1:item:' . $f['item'])
            ->update(['quantity_delta' => -1]);
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 3));
    });

    $run('Corrupt saved price snapshot refuses repricing', static function () use ($db, $writer, $editor, $expectRejected): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $db->table('order_items')->where('id', $f['item'])->update(['unit_price' => '100.00']);
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 3));
    });

    $run('Foreign order item cannot be edited', static function () use ($writer, $editor, $expectRejected): void {
        $a = P03SyntheticFixtures::create();
        $b = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($a['order'], 'products');
        $writer->allocateCreatedOrder($b['order'], 'products');
        $expectRejected(static fn () => $editor->changeQuantity($a['order'], $b['item'], 2, 3));
    });

    $run('Manual order uses the same reconciliation service', static function () use ($db, $writer, $editor): void {
        $f = P03SyntheticFixtures::create(source: 'admin_manual');
        $writer->allocateCreatedOrder($f['order'], 'items');
        $editor->changeQuantity($f['order'], $f['item'], 2, 5);
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 5) {
            throw new RuntimeException('Manual adjustment failed.');
        }
    });

    $run('Campaign snapshot price is preserved', static function () use ($db, $writer, $editor): void {
        $f = P03SyntheticFixtures::create(campaign: true);
        $writer->allocateCreatedOrder($f['order'], 'products');
        $editor->changeQuantity($f['order'], $f['item'], 2, 3);
        if ((string) $db->table('order_items')->where('id', $f['item'])->value('unit_price') !== '125.00'
            || (string) $db->table('orders')->where('id', $f['order'])->value('sub_total') !== '375.00') {
            throw new RuntimeException('Campaign line was repriced.');
        }
    });

    $run('Negotiated bulk order is rejected pending separate contract', static function () use ($writer, $editor, $expectRejected): void {
        $f = P03SyntheticFixtures::create(source: 'admin_bulk');
        $writer->allocateCreatedOrder($f['order'], 'bulk_orders');
        $expectRejected(static fn () => $editor->changeQuantity($f['order'], $f['item'], 2, 3));
    });

    $run('Quantity edit followed by cancellation releases the exact new quantity', static function () use ($db, $writer, $editor, $lifecycle): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        $editor->changeQuantity($f['order'], $f['item'], 2, 4);
        $lifecycle->transitionStatus($f['order'], 'cancelled', static function (\App\Models\Order $order): void {
            $order->update(['order_status' => 'cancelled']);
        });
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 10
            || (int) $db->table('order_items')->where('id', $f['item'])->value('inventory_allocated_quantity') !== 0
            || (int) $db->table('inventory_movements')->where('order_item_id', $f['item'])->sum('quantity_delta') !== 0) {
            throw new RuntimeException('Edited order cancellation did not rebalance stock.');
        }
    });

    $run('Outer transaction rollback undoes quantity, stock and ledger', static function () use ($db, $writer, $editor): void {
        $f = P03SyntheticFixtures::create();
        $writer->allocateCreatedOrder($f['order'], 'products');
        try {
            $db->transaction(static function () use ($editor, $f): void {
                $editor->changeQuantity($f['order'], $f['item'], 2, 4);
                throw new RuntimeException('synthetic parent transaction rejection');
            });
            throw new RuntimeException('Synthetic rollback exception was not raised.');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'synthetic parent transaction rejection') {
                throw $exception;
            }
        }
        if ((int) $db->table('product_variants')->where('id', $f['variant'])->value('stock') !== 8
            || (int) $db->table('order_items')->where('id', $f['item'])->value('quantity') !== 2
            || (int) $db->table('inventory_movements')->where('order_item_id', $f['item'])->count() !== 1) {
            throw new RuntimeException('Outer transaction did not rollback the adjustment.');
        }
    });

    if ($passed !== $total) {
        throw new RuntimeException('Unexpected quantity smoke-test count: ' . $passed);
    }
    echo 'RESULT: ' . $passed . '/' . $total . ' REAL MYSQL P03 QUANTITY CHECKS PASS (all fixture transactions rolled back).' . PHP_EOL;
    echo 'INFO: Test-only flags were set in this isolated CLI process. Public site unchanged.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P03 quantity smoke: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (function_exists('config')) {
        try {
            config()->set('storefront.p03_order_writer_test_enabled', false);
            config()->set('storefront.p03_core_test_enabled', false);
        } catch (Throwable $ignored) {
            // Do not mask the real failure.
        }
    }
}
