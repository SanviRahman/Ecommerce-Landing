<?php

declare(strict_types=1);

/**
 * P14 READ ONLY. No migration callbacks, ALTER/CREATE/INSERT, or seeding.
 * Bootstrap the app so the same .env/config identity gate used by migrations
 * can be verified against the REAL local MySQL connection before any DDL.
 */

use App\Support\LocalHomepageAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "BLOCKED: Install locked Composer dependencies before preflight.\n");
    exit(2);
}

require $autoload;
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    // Strict config + real SELECT DATABASE()/CURRENT_USER(); no secrets echoed.
    LocalHomepageAccess::assertActualMigrationDatabase();

    $baseTables = ['migrations', 'users', 'categories', 'products', 'orders', 'order_items'];
    foreach ($baseTables as $table) {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException('Required pre-P02 schema is missing: ' . $table);
        }
    }

    foreach ([
        'categories' => ['status'],
        'products' => ['status'],
        'orders' => ['order_status', 'sub_total', 'shipping_charge', 'cod_charge', 'total_amount'],
        'order_items' => ['unit_price', 'total_price', 'discount_amount'],
    ] as $table => $columns) {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                throw new RuntimeException('Expected legacy column is missing: ' . $table . '.' . $column);
            }
        }
    }

    $lastLegacyMigration = '2026_09_05_223235_add_device_identifier_to_orders_table';
    if (! DB::table('migrations')->where('migration', $lastLegacyMigration)->exists()) {
        throw new RuntimeException('Latest reviewed legacy migration is missing from the history.');
    }

    $names = array_map(
        static fn (string $file): string => basename($file, '.php'),
        glob(__DIR__ . '/../../database/migrations/2026_10_24_2100*_p02_*.php') ?: []
    );
    if (count($names) !== 11 || count(array_unique($names)) !== 11) {
        throw new RuntimeException('Unexpected P02 migration file inventory.');
    }

    $ran = DB::table('migrations')->whereIn('migration', $names)->pluck('migration')->all();
    $newTables = [
        'product_options', 'product_option_values', 'product_option_assignments',
        'product_variants', 'product_variant_values', 'inventory_movements',
        'checkout_requests', 'storefront_pages', 'storefront_revisions',
        'homepage_sections', 'homepage_section_products', 'homepage_section_categories',
        'homepage_section_items', 'homepage_audit_events', 'carts', 'cart_items',
    ];
    $existingNewTables = array_values(array_filter(
        $newTables,
        static fn (string $name): bool => Schema::hasTable($name)
    ));

    if (count($ran) === 0 && count($existingNewTables) === 0) {
        echo "PASS P14 local schema/credential identity verified (read-only).\n";
        echo "PASS Reviewed legacy schema and latest migration present.\n";
        echo "PASS 11/11 P02 migrations pending, no partial P02 tables.\n";
        echo "RESULT: READY_FOR_REVIEWED_LOCAL_MIGRATE; no DB write performed.\n";
        exit(0);
    }

    if (count($ran) === 11 && count($existingNewTables) === count($newTables)) {
        echo "PASS P14 local schema/credential identity verified (read-only).\n";
        echo "RESULT: ALREADY_APPLIED (11 migrations, 16 tables); no DB write performed.\n";
        exit(0);
    }

    throw new RuntimeException('Partial P02 migration history or schema artifacts detected; manual review required.');
} catch (Throwable $e) {
    // Never print credentials/DSNs/traces; review only the safe, generic reason.
    $reason = $e instanceof RuntimeException ? $e->getMessage() : 'Database preflight failed unexpectedly.';
    fwrite(STDERR, "BLOCKED P14 LOCAL MIGRATION: {$reason}\nNo migration, seeding, or database changes were performed.\n");
    exit(2);
}
