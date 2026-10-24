<?php

declare(strict_types=1);

/**
 * Read-only P02 test database preflight. This script NEVER creates a database,
 * writes a row, runs a migration, reads the real e-commerce tables or boots
 * Laravel. Supply a secret only via P02_TEST_DB_PASSWORD in the shell environment.
 */

function fail(string $message): never
{
    fwrite(STDERR, "BLOCKED: {$message}\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    fail('This diagnostic must run only from the CLI.');
}
if (! extension_loaded('pdo_mysql')) {
    fail('PHP pdo_mysql extension is unavailable. Run using your XAMPP PHP CLI.');
}

$password = getenv('P02_TEST_DB_PASSWORD');
if ($password === false || strlen($password) < 1) {
    fail('Set P02_TEST_DB_PASSWORD in your local terminal (do not paste it into chat/logs).');
}

try {
    $db = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=p01_campaign_test;charset=utf8mb4',
        'p01_tester',
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 4,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $id = $db->query('SELECT DATABASE() AS db, CURRENT_USER() AS account, @@port AS port, VERSION() AS version')->fetch();
    if (! is_array($id) || $id['db'] !== 'p01_campaign_test'
        || ! str_starts_with((string) $id['account'], 'p01_tester@') || (int) $id['port'] !== 3306) {
        fail('Database/server account identity does not match the isolated P02 target.');
    }

    // MySQL account privileges on another database must be denied. USE changes
    // the session's schema ONLY; it neither queries tables nor modifies data.
    $mainDbAccessible = false;
    try {
        $db->exec('USE `ecommerce_landing_db`');
        $mainDbAccessible = true;
    } catch (PDOException $e) {
        if ($e->getCode() !== '42000') {
            // Access denied can have driver SQLSTATE 42000 or 28000;
            // other failures must not be mistaken for proof of isolation.
            if ($e->getCode() !== '28000') {
                fail('Could not prove isolation (unexpected USE error SQLSTATE).');
            }
        }
    }
    if ($mainDbAccessible) {
        fail('Test user can switch to ecommerce_landing_db. REVOKE access before testing.');
    }

    // Refuse broad/global grants even if the specific original schema was denied.
    // These are metadata-only queries; do not print grant strings or credentials.
    $grantRows = $db->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM);
    $allowedScopes = [
        '`p01\\_campaign\\_test`.*',
        '`p01_campaign_test`.*',
        'p01\\_campaign\\_test.*',
        'p01_campaign_test.*',
    ];
    foreach ($grantRows as $grantRow) {
        $grant = (string) ($grantRow[0] ?? '');
        if (stripos($grant, 'GRANT OPTION') !== false || stripos($grant, 'GRANT ALL PRIVILEGES') !== false) {
            fail('The test account has excessive privileges.');
        }
        if (preg_match('/\AGRANT\s+USAGE\s+ON\s+\*\.\*/i', $grant) === 1) {
            continue;
        }
        $on = stripos($grant, ' ON ');
        $to = $on === false ? false : stripos($grant, ' TO ', $on + 4);
        if ($to === false) {
            fail('Unexpected grant format on the test account.');
        }
        $scope = trim(substr($grant, $on + 4, $to - $on - 4));
        if (! in_array($scope, $allowedScopes, true)) {
            fail('The test account has a grant outside its exact test schema.');
        }
    }

    // Now the connection must still be scoped to the isolated DB for metadata.
    $db->exec('USE `p01_campaign_test`');
    $tables = (int) $db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();

    echo "PASS authenticated test schema: p01_campaign_test\n";
    echo "PASS restricted test account: p01_tester\n";
    echo "PASS shared local MariaDB/MySQL port 3306\n";
    echo "PASS access to original ecommerce_landing_db denied\n";
    echo "INFO running server: " . $id['version'] . "\n";
    echo "INFO existing tables in test schema: {$tables}\n";
    echo "READ-ONLY PREFLIGHT COMPLETE. No migration or database write performed.\n";
} catch (PDOException $e) {
    fail('MySQL preflight failed (SQLSTATE ' . $e->getCode() . '). Verify server, test user/schema and scoped grants.');
} finally {
    unset($password, $db);
}
