<?php

declare(strict_types=1);

require_once __DIR__ . '/P02TestSchemaGate.php';

/**
 * P02 local MariaDB test-schema migration runner.
 * Default execution is READ-ONLY. To change the disposable test schema:
 *   P02_TEST_DDL_APPROVED=YES_P01_CAMPAIGN_TEST_ONLY
 *   php tools/qa/p02-migrate-test-only.php --apply-test-only
 *
 * Runs EXACTLY the 11 reviewed P02 migration files. Never invokes a generic
 * "php artisan migrate", migrate:fresh, migration rollback, or broad seeding.
 * Original DB is unreachable using the p01_tester account.
 */
try {
    $root = dirname(__DIR__, 2);
    $sources = P02TestSchemaGate::sourceInventory($root);
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    $before = P02TestSchemaGate::evaluate($sources, P02TestSchemaGate::inspect($pdo));

    echo 'STATUS: ' . $before['status'] . PHP_EOL;
    echo 'DETAIL: ' . $before['reason'] . PHP_EOL;
    echo 'INFO migrations P02 applied: ' . $before['p02_applied'] . '/11' . PHP_EOL;
    $apply = in_array('--apply-test-only', $argv, true);
    if (! $apply) {
        echo "DRY-RUN ONLY. No Laravel bootstrap and no DB write.\n";
        exit($before['status'] === 'BLOCKED' ? 1 : 0);
    }

    if ($before['status'] !== 'READY_TO_TEST_MIGRATE') {
        throw new RuntimeException('P02 is not a clean, pending migration set. Apply refused.');
    }
    if (getenv('P02_TEST_DDL_APPROVED') !== 'YES_P01_CAMPAIGN_TEST_ONLY') {
        throw new RuntimeException('Explicit P02_TEST_DDL_APPROVED test-only confirmation is missing.');
    }
    if (is_file($root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php')) {
        throw new RuntimeException('Laravel config cache exists; refuse DB migration until reviewed.');
    }
    if (! is_file($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
        throw new RuntimeException('Composer vendor autoloader is missing. Apply refused.');
    }
    if (PHP_VERSION_ID < 80400) {
        throw new RuntimeException('Installed Laravel vendor requires the reviewed PHP >= 8.4 runtime.');
    }

    // The schema is dedicated to synthetic tests. Refuse to alter a populated
    // commerce schema even when access is restricted to the test database.
    P02TestSchemaGate::assertNoCommerceData($pdo);

    $env = getenv();
    if (! is_array($env)) {
        throw new RuntimeException('Cannot create a constrained child-process environment.');
    }
    $env['APP_ENV'] = 'testing';
    $env['APP_DEBUG'] = 'false';
    $env['APP_URL'] = 'http://127.0.0.1:8000';
    $env['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
    $env['DB_CONNECTION'] = 'mysql';
    $env['DB_HOST'] = '127.0.0.1';
    $env['DB_PORT'] = '3306';
    $env['DB_DATABASE'] = P02TestSchemaGate::TEST_DATABASE;
    $env['DB_USERNAME'] = P02TestSchemaGate::TEST_ACCOUNT;
    $env['DB_PASSWORD'] = (string) getenv('P02_TEST_DB_PASSWORD');
    $env['DB_URL'] = '';
    $env['DB_SOCKET'] = '';
    $env['CACHE_STORE'] = 'array';
    $env['SESSION_DRIVER'] = 'array';
    $env['QUEUE_CONNECTION'] = 'sync';
    $env['MAIL_MAILER'] = 'log';
    $env['FILESYSTEM_DISK'] = 'local';
    $env['APP_CONFIG_CACHE'] = $root . '/bootstrap/cache/p02-test-config-not-created.php';

    foreach ($sources['p02'] as $migration) {
        P02TestSchemaGate::assertIsolatedConnection($pdo);
        echo "APPLY TEST ONLY: {$migration}\n";
        $cmd = [
            PHP_BINARY,
            'artisan',
            'migrate',
            '--force',
            '--no-interaction',
            '--no-ansi',
            '--path=database/migrations/' . $migration . '.php',
        ];
        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $root, $env);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not run the guarded Laravel migration subprocess.');
        }
        fclose($pipes[0]);
        // Individual Laravel migration logs are small. Do not expose exception
        // output to this summary; operator can inspect local Laravel logs.
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);
        unset($stdout, $stderr);
        if ($status !== 0) {
            throw new RuntimeException('Stopped after a failed test migration: ' . $migration
                . '. Inspect the local test-only Laravel log. Never auto-reset a partial schema.');
        }
        $recorded = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
        $recorded->execute([$migration]);
        if ((int) $recorded->fetchColumn() !== 1) {
            throw new RuntimeException('Migration succeeded but its history row cannot be verified. Stop.');
        }
        echo "PASS: {$migration} registered in isolated test DB\n";
    }

    $result = P02TestSchemaGate::evaluate($sources, P02TestSchemaGate::inspect($pdo));
    if ($result['status'] !== 'ALREADY_APPLIED') {
        throw new RuntimeException('P02 schema verification is incomplete; do not begin P03 database tests.');
    }
    echo "PASS all P02 migrations + required test DB schema verification.\n";
    echo "No production database or deployment change was authorized or executed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'BLOCKED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
