<?php

declare(strict_types=1);

/**
 * P09: TRUE committed two-client final-item race on the restricted test DB.
 * This differs from rollback-only PHPUnit: fixtures briefly COMMIT so two
 * independent Laravel processes can actually observe/compete for the same SKU.
 * Any interrupted run can leave synthetic rows; no automatic broad reset.
 */
if (PHP_SAPI !== 'cli' || getenv('P09_RACE_WRITE_APPROVED') !== 'YES_SYNTHETIC_COMMIT_AND_CLEANUP') {
    fwrite(STDERR, "BLOCKED: Explicit P09_RACE_WRITE_APPROVED=YES_SYNTHETIC_COMMIT_AND_CLEANUP required.\n");
    exit(2);
}
if (getenv('P02_TEST_DB_PASSWORD') === false || getenv('P02_TEST_DB_PASSWORD') === '') {
    fwrite(STDERR, "BLOCKED: Restricted test password missing; never use the app DB password.\n");
    exit(2);
}

require_once __DIR__ . '/P02TestSchemaGate.php';

function p09Uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $h = bin2hex($b);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4)
        . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
}
function p09Sql(PDO $db, string $sql, array $args = []): PDOStatement
{
    $statement = $db->prepare($sql);
    $statement->execute($args);
    return $statement;
}
function p09Count(PDO $db, string $table): int
{
    return (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

/** @return array<string,mixed> */
function createFixture(PDO $db): array
{
    $suffix = bin2hex(random_bytes(8));
    $now = date('Y-m-d H:i:s');
    $fixture = [
        'suffix' => $suffix,
        'owners' => [hash('sha256', 'p09-race-A-' . $suffix), hash('sha256', 'p09-race-B-' . $suffix)],
        'carts' => [p09Uuid(), p09Uuid()],
        'keys' => [p09Uuid(), p09Uuid()],
        'phones' => ['017' . sprintf('%08d', random_int(0, 99999999)), '018' . sprintf('%08d', random_int(0, 99999999))],
    ];
    $db->beginTransaction();
    try {
        p09Sql($db, 'INSERT INTO categories (name, slug, status, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            ['P09 synthetic', 'p09-cat-' . $suffix, $now, $now]);
        $fixture['category'] = (int) $db->lastInsertId();
        p09Sql($db, 'INSERT INTO products (category_id, name, slug, product_code, stock, new_price, status, has_variants, inventory_version, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 750, 1, 0, 1, ?, ?)',
            [$fixture['category'], 'P09 synthetic last stock', 'p09-prod-' . $suffix, 'P09-' . $suffix, $now, $now]);
        $fixture['product'] = (int) $db->lastInsertId();
        p09Sql($db, 'INSERT INTO product_variants (product_id, sku, option_signature, stock, is_default, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, 1, 1, ?, ?)',
            [$fixture['product'], 'P09-SKU-' . $suffix, 'default', $now, $now]);
        $fixture['variant'] = (int) $db->lastInsertId();
        p09Sql($db, 'INSERT INTO inventory_movements (product_variant_id, event_uuid, operation_key, kind, quantity_delta, stock_before, stock_after, reason, created_at) VALUES (?, ?, ?, ?, 1, 0, 1, ?, ?)',
            [$fixture['variant'], p09Uuid(), 'opening:v1:product:' . $fixture['product'], 'opening', 'P09 synthetic opening', $now]);
        p09Sql($db, 'INSERT INTO shipping_charges (campaign_id, area_name, delivery_charge, status, created_at, updated_at) VALUES (NULL, ?, 65, 1, ?, ?)',
            ['P09 local test area ' . $suffix, $now, $now]);
        $fixture['shipping'] = (int) $db->lastInsertId();
        for ($i = 0; $i < 2; $i++) {
            p09Sql($db, 'INSERT INTO carts (id, owner_hash, currency, lock_version, expires_at, created_at, updated_at) VALUES (?, ?, ?, 2, DATE_ADD(NOW(), INTERVAL 1 DAY), ?, ?)',
                [$fixture['carts'][$i], $fixture['owners'][$i], 'BDT', $now, $now]);
            p09Sql($db, 'INSERT INTO cart_items (cart_id, product_id, product_variant_id, quantity, quoted_unit_price, created_at, updated_at) VALUES (?, ?, ?, 1, 750.00, ?, ?)',
                [$fixture['carts'][$i], $fixture['product'], $fixture['variant'], $now, $now]);
        }
        $db->commit(); // Explicit, narrow, committed synthetic fixture.
        return $fixture;
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }
}

/** Safely delete ONLY generated fixture IDs. Never run table-wide deletes. */
function cleanupFixture(PDO $db, array $f): void
{
    $db->beginTransaction();
    try {
        $customerIds = p09Sql($db, 'SELECT id FROM customers WHERE phone IN (?, ?)', $f['phones'])->fetchAll(PDO::FETCH_COLUMN);
        $orderIds = [];
        foreach ($customerIds as $customerId) {
            $orders = p09Sql($db, 'SELECT id FROM orders WHERE customer_id = ? FOR UPDATE', [(int) $customerId])->fetchAll(PDO::FETCH_COLUMN);
            foreach ($orders as $id) {
                $orderIds[] = (int) $id;
            }
        }
        foreach ($orderIds as $id) {
            p09Sql($db, 'DELETE FROM order_status_logs WHERE order_id = ?', [$id]);
            p09Sql($db, 'DELETE FROM inventory_movements WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ?)', [$id]);
            p09Sql($db, 'DELETE FROM order_items WHERE order_id = ?', [$id]);
        }
        foreach ($f['owners'] as $owner) {
            p09Sql($db, 'DELETE FROM checkout_requests WHERE owner_hash = ?', [$owner]);
        }
        foreach ($orderIds as $id) {
            p09Sql($db, 'DELETE FROM orders WHERE id = ?', [$id]);
        }
        foreach ($f['carts'] as $cartId) {
            p09Sql($db, 'DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
            p09Sql($db, 'DELETE FROM carts WHERE id = ?', [$cartId]);
        }
        // No historical or unrelated product movements are ever touched.
        p09Sql($db, 'DELETE FROM inventory_movements WHERE product_variant_id = ?', [$f['variant']]);
        p09Sql($db, 'DELETE FROM shipping_charges WHERE id = ?', [$f['shipping']]);
        p09Sql($db, 'DELETE FROM product_variants WHERE id = ?', [$f['variant']]);
        p09Sql($db, 'DELETE FROM products WHERE id = ?', [$f['product']]);
        p09Sql($db, 'DELETE FROM categories WHERE id = ?', [$f['category']]);
        foreach ($customerIds as $id) {
            p09Sql($db, 'DELETE FROM customers WHERE id = ?', [(int) $id]);
        }
        $remaining = (int) p09Sql($db, 'SELECT COUNT(*) FROM products WHERE id = ?', [$f['product']])->fetchColumn();
        $remaining += (int) p09Sql($db, 'SELECT COUNT(*) FROM product_variants WHERE id = ?', [$f['variant']])->fetchColumn();
        $remaining += (int) p09Sql($db, 'SELECT COUNT(*) FROM carts WHERE id IN (?, ?)', $f['carts'])->fetchColumn();
        $remaining += (int) p09Sql($db, 'SELECT COUNT(*) FROM checkout_requests WHERE owner_hash IN (?, ?)', $f['owners'])->fetchColumn();
        if ($remaining !== 0) {
            throw new RuntimeException('Targeted cleanup incomplete; transaction not committed.');
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function p09WorkerEnv(array $f, int $i, string $gate, string $ready): array
{
    $base = [];
    foreach (['PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP', 'HOME', 'USERPROFILE', 'COMSPEC'] as $key) {
        $value = getenv($key);
        if (is_string($value)) {
            $base[$key] = $value;
        }
    }
    return array_merge($base, [
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
        'APP_URL' => 'http://127.0.0.1',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
        'DB_DATABASE' => 'p01_campaign_test', 'DB_USERNAME' => 'p01_tester',
        'DB_PASSWORD' => (string) getenv('P02_TEST_DB_PASSWORD'),
        'P02_TEST_DB_PASSWORD' => (string) getenv('P02_TEST_DB_PASSWORD'),
        'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync', 'FILESYSTEM_DISK' => 'local',
        'P07_CATALOG_TEST_ENABLED' => 'true', 'P08_CART_TEST_ENABLED' => 'true',
        'P08_CHECKOUT_TEST_ENABLED' => 'true',
        'P09_RACE_WORKER' => 'SYNTHETIC_ONLY',
        'P09_RACE_WRITE_APPROVED' => 'YES_SYNTHETIC_COMMIT_AND_CLEANUP',
        'P09_RACE_OWNER_HASH' => $f['owners'][$i],
        'P09_RACE_KEY' => $f['keys'][$i],
        'P09_RACE_PHONE' => $f['phones'][$i],
        'P09_RACE_SHIPPING_ID' => (string) $f['shipping'],
        'P09_RACE_CART_VERSION' => '2',
        'P09_RACE_GATE' => $gate, 'P09_RACE_READY' => $ready,
    ]);
}

$db = null;
$fixture = null;
$processes = [];
$prefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p09race_' . bin2hex(random_bytes(8));
$gate = $prefix . '.go';
$readyFiles = [$prefix . '.A.ready', $prefix . '.B.ready'];
$error = null;
$cleanupOkay = false;
try {
    $root = dirname(__DIR__, 2);
    if (! is_file($root . '/vendor/autoload.php') || ! is_file($root . '/bootstrap/app.php')) {
        throw new RuntimeException('Laravel vendor/bootstrap missing. Use your full local website, not a source-only ZIP.');
    }
    $db = P02TestSchemaGate::connectFromEnvironment();
    $status = P02TestSchemaGate::evaluate(P02TestSchemaGate::sourceInventory($root), P02TestSchemaGate::inspect($db));
    if ($status['status'] !== 'ALREADY_APPLIED') {
        throw new RuntimeException('Required P02 schema not fully applied on the isolated test DB.');
    }
    // Ensure no existing commerce or synthetic leftovers. This is NOT a test for
    // customer/production data; it refuses to operate on nonempty schemas.
    foreach (['products', 'orders', 'order_items', 'customers', 'carts',
        'cart_items', 'checkout_requests', 'inventory_movements', 'order_status_logs'] as $table) {
        if (p09Count($db, $table) !== 0) {
            throw new RuntimeException('Test schema contains existing commerce/old fixtures: ' . $table);
        }
    }
    $fixture = createFixture($db);
    echo "INFO P09 two-worker synthetic fixture committed in restricted test DB.\n";
    $worker = $root . '/tools/qa/p09-checkout-race-worker.php';
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, $worker], [
            0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, $root, p09WorkerEnv($fixture, $i, $gate, $readyFiles[$i]));
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start two independent test clients.');
        }
        $processes[] = ['handle' => $process, 'pipes' => $pipes];
    }
    $deadline = microtime(true) + 12;
    while ((! is_file($readyFiles[0]) || ! is_file($readyFiles[1])) && microtime(true) < $deadline) {
        clearstatcache();
        usleep(15000);
    }
    if (! is_file($readyFiles[0]) || ! is_file($readyFiles[1])) {
        throw new RuntimeException('The two checkout workers did not both reach the start gate.');
    }
    // Hold a real InnoDB product/variant lock briefly so both requests contend.
    $db->beginTransaction();
    try {
        p09Sql($db, 'SELECT id FROM products WHERE id = ? FOR UPDATE', [$fixture['product']]);
        p09Sql($db, 'SELECT id FROM product_variants WHERE id = ? FOR UPDATE', [$fixture['variant']]);
        file_put_contents($gate, 'go', LOCK_EX);
        usleep(180000);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
    $outcomes = [];
    foreach ($processes as $process) {
        $output = stream_get_contents($process['pipes'][1]);
        $stderr = stream_get_contents($process['pipes'][2]);
        fclose($process['pipes'][1]);
        fclose($process['pipes'][2]);
        $exit = proc_close($process['handle']);
        if ($exit !== 0 || ! in_array(trim($output), ['P09_RESULT:SUCCESS', 'P09_RESULT:STOCK_REJECTED'], true)) {
            throw new RuntimeException('A checkout worker failed unexpectedly (exit ' . $exit . '). ' . substr(trim($output . ' ' . $stderr), 0, 140));
        }
        $outcomes[] = trim($output);
    }
    $processes = [];
    $successes = count(array_filter($outcomes, static fn ($outcome) => $outcome === 'P09_RESULT:SUCCESS'));
    $rejects = count(array_filter($outcomes, static fn ($outcome) => $outcome === 'P09_RESULT:STOCK_REJECTED'));
    if ($successes !== 1 || $rejects !== 1) {
        throw new RuntimeException('Parallel checkout invariant failed: expected exactly one committed winner.');
    }
    if ((int) p09Sql($db, 'SELECT stock FROM product_variants WHERE id = ?', [$fixture['variant']])->fetchColumn() !== 0
        || (int) p09Sql($db, 'SELECT stock FROM products WHERE id = ?', [$fixture['product']])->fetchColumn() !== 0) {
        throw new RuntimeException('Last-item product and variant stock disagree with expected zero.');
    }
    $orders = (int) p09Sql($db, 'SELECT COUNT(*) FROM orders WHERE customer_id IN (SELECT id FROM customers WHERE phone IN (?, ?))', $fixture['phones'])->fetchColumn();
    $allocations = (int) p09Sql($db, 'SELECT COUNT(*) FROM inventory_movements WHERE product_variant_id = ? AND kind = ?', [$fixture['variant'], 'allocation'])->fetchColumn();
    $claims = (int) p09Sql($db, 'SELECT COUNT(*) FROM checkout_requests WHERE owner_hash IN (?, ?) AND status = ?', [...$fixture['owners'], 'completed'])->fetchColumn();
    $leftover = (int) p09Sql($db, 'SELECT COUNT(*) FROM cart_items WHERE cart_id IN (?, ?)', $fixture['carts'])->fetchColumn();
    if ($orders !== 1 || $allocations !== 1 || $claims !== 1 || $leftover !== 1) {
        throw new RuntimeException('Parallel checkout produced extra orders/allocations or did not retain losing cart.');
    }
    echo "PASS Two independent Laravel processes competed for last available SKU\n";
    echo "PASS Exactly one COD order and one stock allocation committed\n";
    echo "PASS Product/variant mirror is zero; losing cart retained one item\n";
} catch (Throwable $exception) {
    $error = $exception->getMessage();
} finally {
    foreach ($processes as $process) {
        if (is_resource($process['handle'])) {
            proc_terminate($process['handle']);
        }
        foreach ($process['pipes'] as $pipe) {
            if (is_resource($pipe)) fclose($pipe);
        }
        if (is_resource($process['handle'])) proc_close($process['handle']);
    }
    foreach ([$gate, ...$readyFiles] as $file) {
        if (is_file($file)) @unlink($file);
    }
    if ($fixture !== null && $db instanceof PDO) {
        try {
            if ($db->inTransaction()) $db->rollBack();
            cleanupFixture($db, $fixture);
            $cleanupOkay = true;
            echo "PASS Targeted synthetic fixture cleanup committed; no table reset performed\n";
        } catch (Throwable $cleanupError) {
            $cleanupOkay = false;
            fwrite(STDERR, "STOP: Synthetic fixture cleanup failed. Do NOT rerun or reset DB; inspect test schema first.\n");
            $error = ($error ? $error . '; ' : '') . 'synthetic cleanup failure: ' . $cleanupError->getMessage();
        }
    }
}
if ($error !== null || ($fixture !== null && ! $cleanupOkay)) {
    fwrite(STDERR, 'FAIL P09: ' . ($error ?? 'cleanup incomplete') . PHP_EOL);
    exit(1);
}
echo "RESULT: P09 4/4 COMMITTED TWO-WORKER LAST-ITEM CHECKS PASS (isolated schema, fixtures deleted).\n";
