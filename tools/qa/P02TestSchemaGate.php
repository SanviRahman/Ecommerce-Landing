<?php

declare(strict_types=1);

/**
 * A standalone, fail-closed P02 migration-history and schema gate.
 * The audit side reads ONLY information_schema and Laravel's migrations table.
 * It does not load Laravel, .env, customer rows, or application models.
 */
final class P02TestSchemaGate
{
    public const TEST_DATABASE = 'p01_campaign_test';
    public const TEST_ACCOUNT = 'p01_tester';
    public const EXPECTED_P02_COUNT = 11;

    private const REQUIRED_LEGACY_TABLES = [
        'migrations', 'categories', 'products', 'campaigns', 'campaign_product',
        'orders', 'order_items', 'users',
    ];

    private const P02_COLUMNS = [
        'categories' => ['parent_id', 'sort_order'],
        'products' => ['has_variants', 'inventory_version'],
        'orders' => ['inventory_version'],
        'order_items' => [
            'product_variant_id', 'variant_sku', 'variant_options', 'inventory_allocated_quantity',
        ],
    ];

    private const EXACT_MONEY_COLUMNS = [
        'orders' => ['sub_total', 'shipping_charge', 'cod_charge', 'total_amount'],
        'order_items' => ['unit_price', 'total_price', 'discount_amount'],
    ];

    /** @return array{legacy:list<string>,p02:list<string>,new_tables:list<string>} */
    public static function sourceInventory(string $root): array
    {
        $directory = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php');
        if (! is_array($files) || $files === []) {
            throw new RuntimeException('No migrations available in the inspected source.');
        }

        $legacy = [];
        $p02 = [];
        $tables = [];
        foreach ($files as $path) {
            $base = basename($path, '.php');
            if (preg_match('/^2026_10_24_2100\d{2}_p02_[a-z0-9_]+$/D', $base)) {
                $p02[] = $base;
                $text = file_get_contents($path);
                if (! is_string($text)) {
                    throw new RuntimeException('Cannot inspect P02 migration source.');
                }
                preg_match_all("/Schema::create\('([a-z_]+)'/", $text, $matches);
                foreach ($matches[1] as $table) {
                    $tables[] = $table;
                }
            } else {
                $legacy[] = $base;
            }
        }

        sort($legacy, SORT_STRING);
        sort($p02, SORT_STRING);
        sort($tables, SORT_STRING);
        $tables = array_values(array_unique($tables));
        if (count($p02) !== self::EXPECTED_P02_COUNT || count($tables) !== 16) {
            throw new RuntimeException('P02 migration source count/table declarations are not the reviewed baseline.');
        }

        return ['legacy' => $legacy, 'p02' => $p02, 'new_tables' => $tables];
    }

    public static function connectFromEnvironment(): PDO
    {
        if (PHP_SAPI !== 'cli' || ! extension_loaded('pdo_mysql')) {
            throw new RuntimeException('PHP CLI with pdo_mysql is required.');
        }
        $password = getenv('P02_TEST_DB_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('P02_TEST_DB_PASSWORD must be set locally; never paste it into chat.');
        }

        try {
            $pdo = new PDO(
                'mysql:host=127.0.0.1;port=3306;dbname=' . self::TEST_DATABASE . ';charset=utf8mb4',
                self::TEST_ACCOUNT,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } finally {
            unset($password);
        }

        self::assertIsolatedConnection($pdo);

        return $pdo;
    }

    public static function assertIsolatedConnection(PDO $pdo): void
    {
        $id = $pdo->query('SELECT DATABASE() AS schema_name, CURRENT_USER() AS account, @@port AS port')->fetch();
        if (! is_array($id) || $id['schema_name'] !== self::TEST_DATABASE
            || ! str_starts_with((string) $id['account'], self::TEST_ACCOUNT . '@')
            || (int) $id['port'] !== 3306) {
            throw new RuntimeException('Refusing non-isolated MySQL connection.');
        }

        $canAccessProduction = false;
        try {
            $pdo->exec('USE `ecommerce_landing_db`');
            $canAccessProduction = true;
        } catch (PDOException $exception) {
            if (! in_array($exception->getCode(), ['42000', '28000'], true)) {
                throw new RuntimeException('Original database access denial could not be verified.');
            }
        } finally {
            $pdo->exec('USE `p01_campaign_test`');
        }
        if ($canAccessProduction) {
            throw new RuntimeException('Test user can access the original database: STOP.');
        }

        $allowed = [
            '`p01\\_campaign\\_test`.*', '`p01_campaign_test`.*',
            'p01\\_campaign\\_test.*', 'p01_campaign_test.*',
        ];
        foreach ($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM) as $record) {
            $grant = (string) ($record[0] ?? '');
            if (stripos($grant, 'GRANT OPTION') !== false || stripos($grant, 'GRANT ALL PRIVILEGES') !== false) {
                throw new RuntimeException('Test user has excessive privileges.');
            }
            if (preg_match('/^GRANT\s+USAGE\s+ON\s+\*\.\*/i', $grant) === 1) {
                continue;
            }
            $on = stripos($grant, ' ON ');
            $to = $on === false ? false : stripos($grant, ' TO ', $on + 4);
            if ($to === false || ! in_array(trim(substr($grant, $on + 4, $to - $on - 4)), $allowed, true)) {
                throw new RuntimeException('Test user has an unreviewed privilege scope.');
            }
        }
    }

    /**
     * @return array{applied:list<string>,tables:list<string>,columns:array<string,array<string,array{type:string,precision:int,scale:int}>>}
     */
    public static function inspect(PDO $pdo): array
    {
        $tables = $pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchAll(PDO::FETCH_COLUMN);
        $tables = array_values(array_map('strval', $tables));
        sort($tables, SORT_STRING);

        if (! in_array('migrations', $tables, true)) {
            throw new RuntimeException('The test database has no Laravel migrations history. Refusing to infer it.');
        }

        $applied = array_values(array_map('strval', $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN)));
        sort($applied, SORT_STRING);

        $columns = [];
        $statement = $pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE '
            . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
        );
        foreach ($statement->fetchAll() as $row) {
            $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = [
                'type' => strtolower((string) $row['DATA_TYPE']),
                'precision' => (int) ($row['NUMERIC_PRECISION'] ?? 0),
                'scale' => (int) ($row['NUMERIC_SCALE'] ?? 0),
            ];
        }

        return ['applied' => $applied, 'tables' => $tables, 'columns' => $columns];
    }

    /**
     * @param array{legacy:list<string>,p02:list<string>,new_tables:list<string>} $source
     * @param array{applied:list<string>,tables:list<string>,columns:array<string,array<string,array{type:string,precision:int,scale:int}>>} $snapshot
     * @return array{status:'READY_TO_TEST_MIGRATE'|'ALREADY_APPLIED'|'BLOCKED',reason:string,legacy_applied:int,p02_applied:int,table_count:int}
     */
    public static function evaluate(array $source, array $snapshot): array
    {
        $applied = array_fill_keys($snapshot['applied'], true);
        $tables = array_fill_keys($snapshot['tables'], true);
        $known = array_fill_keys(array_merge($source['legacy'], $source['p02']), true);
        $legacyMissing = array_values(array_filter($source['legacy'], static fn ($file) => ! isset($applied[$file])));
        $unknownApplied = array_values(array_filter($snapshot['applied'], static fn ($file) => ! isset($known[$file])));
        $p02Applied = count(array_filter($source['p02'], static fn ($file) => isset($applied[$file])));

        $result = static function (string $status, string $reason) use ($source, $p02Applied, $applied, $snapshot): array {
            return [
                'status' => $status,
                'reason' => $reason,
                'legacy_applied' => count(array_intersect($source['legacy'], array_keys($applied))),
                'p02_applied' => $p02Applied,
                'table_count' => count($snapshot['tables']),
            ];
        };

        if ($legacyMissing !== [] || $unknownApplied !== []) {
            return $result('BLOCKED', 'Historical migration records differ from the current codebase. Review before any DDL.');
        }
        foreach (self::REQUIRED_LEGACY_TABLES as $table) {
            if (! isset($tables[$table])) {
                return $result('BLOCKED', 'A required historical table is absent.');
            }
        }
        if ($p02Applied > 0 && $p02Applied < self::EXPECTED_P02_COUNT) {
            return $result('BLOCKED', 'P02 is partially applied. Do not replay/rollback without a reviewed recovery.');
        }

        if ($p02Applied === 0) {
            foreach ($source['new_tables'] as $table) {
                if (isset($tables[$table])) {
                    return $result('BLOCKED', 'A P02 table exists without matching migration history.');
                }
            }
            foreach (self::P02_COLUMNS as $table => $columnNames) {
                foreach ($columnNames as $columnName) {
                    if (isset($snapshot['columns'][$table][$columnName])) {
                        return $result('BLOCKED', 'A P02 column exists without matching migration history.');
                    }
                }
            }
            return $result('READY_TO_TEST_MIGRATE', 'All historical migrations are recorded and no P02 artifacts exist.');
        }

        foreach ($source['new_tables'] as $table) {
            if (! isset($tables[$table])) {
                return $result('BLOCKED', 'P02 migration is recorded, but a required table is missing.');
            }
        }
        foreach (self::P02_COLUMNS as $table => $columnNames) {
            foreach ($columnNames as $columnName) {
                if (! isset($snapshot['columns'][$table][$columnName])) {
                    return $result('BLOCKED', 'P02 migration is recorded, but a required column is missing.');
                }
            }
        }
        foreach (self::EXACT_MONEY_COLUMNS as $table => $columnNames) {
            foreach ($columnNames as $columnName) {
                $column = $snapshot['columns'][$table][$columnName] ?? null;
                if ($column === null || $column['type'] !== 'decimal'
                    || $column['precision'] !== 14 || $column['scale'] !== 2) {
                    return $result('BLOCKED', 'P02 decimal money column precision/scale verification failed.');
                }
            }
        }

        return $result('ALREADY_APPLIED', 'All 11 P02 migrations and required tables/columns are present in test DB.');
    }

    /** Apply gate: do not ALTER any existing populated commerce test schema. */
    public static function assertNoCommerceData(PDO $pdo): void
    {
        foreach (['users', 'products', 'campaigns', 'orders', 'order_items', 'customers'] as $table) {
            $query = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');
            if ((int) $query->fetchColumn() !== 0) {
                throw new RuntimeException('The test schema contains commerce rows. Use a separately approved disposable clone.');
            }
        }
    }
}
