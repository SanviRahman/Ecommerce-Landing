<?php

declare(strict_types=1);

/** P14 pure permission matrix: no Laravel boot, database access or migration. */
require_once __DIR__ . '/../../app/Support/LocalHomepageAccess.php';

use App\Support\LocalHomepageAccess as Access;

$ok = 0;
$total = 0;
$base = [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
    'database' => 'ecommerce_landing_db', 'username' => 'root',
    'url' => null, 'unix_socket' => null, 'read' => null, 'write' => null,
];
$check = static function (string $name, bool $expected, string $env = 'local', bool $debug = true,
    string $default = 'mysql', array $database = [], bool $approved = true) use (&$ok, &$total, $base): void {
    $total++;
    $config = array_replace($base, $database);
    $result = Access::matchesConfiguration($env, $debug, $default, $config, $approved);
    if ($result === $expected) {
        $ok++;
        echo "PASS {$name}\n";
    } else {
        echo "FAIL {$name}\n";
        exit(1);
    }
};
$check('Reviewed local MySQL database with explicit opt-in', true);
$check('Default deny without approval', false, approved: false);
$check('Production environment always denied', false, env: 'production');
$check('Staging environment always denied', false, env: 'staging');
$check('Testing environment cannot use local developer permission', false, env: 'testing');
$check('APP_DEBUG=false denied even in local env', false, debug: false);
$check('SQLite default denied', false, default: 'sqlite');
$check('Non-MySQL driver denied', false, database: ['driver' => 'pgsql']);
$check('Isolated p01 DB cannot use this local path', false, database: ['database' => 'p01_campaign_test']);
$check('Other DB names denied', false, database: ['database' => 'other_db']);
$check('Remote host denied', false, database: ['host' => '192.168.1.2']);
$check('Untrusted localhost alias denied', false, database: ['host' => 'localhost']);
$check('Wrong port denied', false, database: ['port' => 3307]);
$check('DB URL override denied', false, database: ['url' => 'mysql://somewhere/db']);
$check('Unix socket override denied', false, database: ['unix_socket' => '/var/run/mysql.sock']);
$check('Split read connection denied', false, database: ['read' => ['host' => 'localhost']]);
$check('Split write connection denied', false, database: ['write' => ['host' => 'localhost']]);
$check('Missing username denied', false, database: ['username' => null]);
$check('Blank username denied', false, database: ['username' => '']);
$check('Whitespace-only username denied', false, database: ['username' => '   ']);
$check('Unexpected hostname structure denied', false, database: ['host' => ['127.0.0.1']]);
echo "RESULT: {$ok}/{$total} P14 pure local-permission cases PASS (no Laravel, MySQL or writes).\n";
