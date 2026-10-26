<?php

declare(strict_types=1);

/**
 * P28: scoped forward-only schema migration helper for the synthetic p01_campaign_test DB.
 * No .env updates, no original DB access, no seeding, rollback, or schema reset.
 * No DDL unless --apply + explicit approval; the existing migration's own
 * P02MigrationSafety guard is authoritative and is NOT modified here.
 */

require_once __DIR__ . '/../qa/P02TestSchemaGate.php';

const P28_MIGRATION = '2026_10_26_100001_create_storefront_accounts';
const P28_CONSENT = 'YES_P01_TEST_ACCOUNT_ONLY';

/** @param array<string,mixed> $snapshot @param list<string> $p02Names
 *  @return array{status:string, reason:string}
 */
function p28State(array $snapshot, array $p02Names): array
{
    $tables = array_fill_keys($snapshot['tables'], true);
    $applied = array_fill_keys($snapshot['applied'], true);
    foreach ($p02Names as $migration) {
        if (! isset($applied[$migration])) {
            return ['status' => 'BLOCKED', 'reason' => 'The complete P02 migration set is not recorded in isolated DB.'];
        }
    }
    foreach (['migrations', 'orders', 'customers', 'users', 'products', 'product_variants',
        'storefront_pages', 'storefront_revisions', 'checkout_requests'] as $table) {
        if (! isset($tables[$table])) {
            return ['status' => 'BLOCKED', 'reason' => 'Missing required reviewed base table: ' . $table];
        }
    }
    if (! isset($snapshot['columns']['orders']['customer_id'])) {
        return ['status' => 'BLOCKED', 'reason' => 'Required orders.customer_id is missing.'];
    }
    $hasTable = isset($tables['storefront_accounts']);
    $hasColumn = isset($snapshot['columns']['orders']['storefront_account_id']);
    $hasMigration = isset($applied[P28_MIGRATION]);

    if ($hasTable && $hasColumn && $hasMigration) {
        return ['status' => 'ALREADY_APPLIED', 'reason' => 'Isolated account schema, order FK column and migration history exist.'];
    }
    if ($hasTable || $hasColumn || $hasMigration) {
        return ['status' => 'BLOCKED', 'reason' => 'Partial account migration artifacts/history found; manual review required.'];
    }
    return ['status' => 'READY', 'reason' => 'Only the single reviewed forward account migration is pending.'];
}

function p28SelfTest(): void
{
    $migrations = array_map(static fn (int $n): string => 'p02_' . $n, range(1, 11));
    $sample = [
        'tables' => ['migrations', 'orders', 'customers', 'users', 'products', 'product_variants',
            'storefront_pages', 'storefront_revisions', 'checkout_requests'],
        'applied' => $migrations,
        'columns' => ['orders' => ['customer_id' => []]],
    ];
    if (p28State($sample, $migrations)['status'] !== 'READY') {
        throw new RuntimeException('Self-test READY case failed.');
    }
    $sample['tables'][] = 'storefront_accounts';
    if (p28State($sample, $migrations)['status'] !== 'BLOCKED') {
        throw new RuntimeException('Self-test partial DDL case failed.');
    }
    $sample['columns']['orders']['storefront_account_id'] = [];
    $sample['applied'][] = P28_MIGRATION;
    if (p28State($sample, $migrations)['status'] !== 'ALREADY_APPLIED') {
        throw new RuntimeException('Self-test idempotent case failed.');
    }
    unset($sample['applied'][0]);
    if (p28State($sample, $migrations)['status'] !== 'BLOCKED') {
        throw new RuntimeException('Self-test missing P02 migration case failed.');
    }
    echo "PASS P28 4/4 pure schema-state safety cases. No database access.\n";
}

function p28SourceReview(string $root): void
{
    $migration = $root . '/database/migrations/' . P28_MIGRATION . '.php';
    $manifest = $root . '/reports/p11-source-manifest.json';
    if (! is_file($migration) || ! is_file($manifest)) {
        throw new RuntimeException('Reviewed migration/manifest missing.');
    }
    $data = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
    $matching = array_values(array_filter(
        $data['files'] ?? [],
        static fn (array $file): bool => ($file['path'] ?? '') === 'database/migrations/' . P28_MIGRATION . '.php'
    ));
    if (count($matching) !== 1 || (int) $matching[0]['bytes'] !== filesize($migration)
        || ! hash_equals((string) $matching[0]['sha256'], hash_file('sha256', $migration))) {
        throw new RuntimeException('Account migration does not match the reviewed P11 manifest.');
    }
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}
$mode = $argv[1] ?? '--check';
try {
    if ($mode === '--self-test') {
        p28SelfTest();
        exit(0);
    }
    if (! in_array($mode, ['--check', '--apply'], true) || count($argv) !== 2) {
        throw new RuntimeException('Use --check, --apply or --self-test.');
    }
    $root = dirname(__DIR__, 2);
    p28SourceReview($root);
    $sources = P02TestSchemaGate::sourceInventory($root);
    if (count($sources['p02']) !== 11) {
        throw new RuntimeException('Reviewed P02 migration inventory count is incorrect.');
    }
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    $state = p28State(P02TestSchemaGate::inspect($pdo), $sources['p02']);
    echo 'P28 ISOLATED TEST DB STATUS: ' . $state['status'] . PHP_EOL;
    echo 'DETAIL: ' . $state['reason'] . PHP_EOL;
    if ($mode === '--check') {
        echo "READ-ONLY. No schema or business data changed.\n";
        exit($state['status'] === 'BLOCKED' ? 2 : 0);
    }
    if ($state['status'] === 'ALREADY_APPLIED') {
        echo "PASS Account schema already applied. No DDL repeated.\n";
        exit(0);
    }
    if ($state['status'] !== 'READY') {
        throw new RuntimeException('Blocked: schema is not cleanly pending; no migration executed.');
    }
    if (getenv('P28_ISOLATED_ACCOUNT_DDL_APPROVED') !== P28_CONSENT) {
        throw new RuntimeException('Explicit test-only DDL approval is missing.');
    }
    if (! is_file($root . '/vendor/autoload.php')) {
        throw new RuntimeException('Composer dependencies missing.');
    }
    if (is_file($root . '/bootstrap/cache/config.php')) {
        throw new RuntimeException('Shared Laravel configuration cache exists; refuse isolated migration.');
    }
    // The child process receives a completely explicit restricted DB identity.
    // Its process environment does not persist to the owner's main storefront.
    $env = getenv();
    if (! is_array($env)) {
        throw new RuntimeException('Cannot prepare restricted child process environment.');
    }
    $password = getenv('P02_TEST_DB_PASSWORD');
    if (! is_string($password) || $password === '') {
        throw new RuntimeException('Restricted test credential not available.');
    }
    $env = array_merge($env, [
        'APP_ENV' => 'local',
        'APP_DEBUG' => 'false',
        'APP_URL' => 'http://127.0.0.1:8088',
        'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
        'APP_CONFIG_CACHE' => $root . '/bootstrap/cache/p28-isolated-config-never-created.php',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'p01_campaign_test',
        'DB_USERNAME' => 'p01_tester',
        'DB_PASSWORD' => $password,
        'DB_URL' => '',
        'DB_SOCKET' => '',
        'P14_LOCAL_P02_MIGRATION_APPROVED' => '',
        'P14_LOCAL_HOMEPAGE_BUILDER_ENABLED' => 'false',
        'P25_LOCAL_STOREFRONT_BROWSE_ENABLED' => 'false',
        'P26_ISOLATED_COMMERCE_QA_ENABLED' => 'false',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'log',
    ]);
    unset($password);
    P02TestSchemaGate::assertIsolatedConnection($pdo);
    echo "APPLY EXACT MIGRATION: " . P28_MIGRATION . " (p01_campaign_test only)\n";
    $command = [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction', '--no-ansi',
        '--path=database/migrations/' . P28_MIGRATION . '.php'];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root, $env);
    unset($env);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start the isolated Laravel migration child process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    // Keep any sensitive SQL/connection strings in local-only logs, not stdout.
    unset($stdout, $stderr);
    if ($exitCode !== 0) {
        throw new RuntimeException('Laravel isolated migration failed (exit ' . $exitCode . '). '
            . 'Do not rerun: DDL may be partial. Review test-only Laravel log and metadata.');
    }
    P02TestSchemaGate::assertIsolatedConnection($pdo);
    $after = p28State(P02TestSchemaGate::inspect($pdo), $sources['p02']);
    if ($after['status'] !== 'ALREADY_APPLIED') {
        throw new RuntimeException('Migration exited normally but isolated schema/history verification is incomplete.');
    }
    echo "PASS P28 account migration recorded and verified in p01_campaign_test only.\n";
    echo "No changes to original ecommerce_landing_db or project .env.\n";
} catch (Throwable $exception) {
    $message = $exception instanceof RuntimeException ? $exception->getMessage() : get_class($exception);
    fwrite(STDERR, 'BLOCKED P28: ' . $message . PHP_EOL);
    exit(2);
}
