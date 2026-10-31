<?php

declare(strict_types=1);

/**
 * P30 - TEST-ONLY, narrowly-scoped synthetic storefront readiness fixtures.
 * Does not read .env or Laravel data/config; uses P02's hard-coded restricted DB gate.
 * Only inserts into an empty p01_campaign_test dataset after explicit approval.
 * No schema changes, imports, production reads, deletion, reset or seeding framework.
 */

require_once dirname(__DIR__) . '/qa/P02TestSchemaGate.php';
require_once dirname(__DIR__, 2) . '/app/Support/HomeSectionSchema.php';

const P30_APPROVAL = 'YES_P01_SYNTHETIC_FIXTURES_ONLY';
const P30_ACCOUNT_MIGRATION = '2026_10_26_100001_create_storefront_accounts';

const P30_PRODUCTS = [
    ['slug' => 'p30-qa-ceramic-mug', 'name' => 'P30 QA Ceramic Mug (Demo)', 'code' => 'P30-QA-MUG', 'price' => 299, 'stock' => 12, 'category' => 0],
    ['slug' => 'p30-qa-kitchen-set', 'name' => 'P30 QA Kitchen Set (Demo)', 'code' => 'P30-QA-SET', 'price' => 649, 'stock' => 8, 'category' => 0],
    ['slug' => 'p30-qa-cotton-bag', 'name' => 'P30 QA Cotton Bag (Demo)', 'code' => 'P30-QA-BAG', 'price' => 199, 'stock' => 15, 'category' => 1],
    ['slug' => 'p30-qa-notebook', 'name' => 'P30 QA Notebook (Demo)', 'code' => 'P30-QA-BOOK', 'price' => 129, 'stock' => 20, 'category' => 1],
];

/** Tables requiring a pristine synthetic data surface before creation. */
const P30_EMPTY_TABLES = [
    'categories', 'products', 'product_variants', 'shipping_charges',
    'storefront_pages', 'storefront_revisions', 'homepage_sections',
    'homepage_section_products', 'homepage_section_categories',
    'homepage_section_items', 'homepage_audit_events',
    'campaigns', 'orders', 'order_items', 'carts', 'cart_items',
    'checkout_requests', 'customers', 'storefront_accounts',
    'inventory_movements',
];

/** Expected row counts in a newly installed dataset (no checkout yet). */
const P30_EXPECTED = [
    'categories' => 2,
    'products' => 4,
    'product_variants' => 4,
    'shipping_charges' => 2,
    'storefront_pages' => 1,
    'storefront_revisions' => 1,
    'homepage_sections' => 3,
    'homepage_section_categories' => 2,
    'homepage_section_products' => 4,
    'homepage_section_items' => 0,
    'homepage_audit_events' => 1,
];

/** Pure state decision; simple, deliberate and testable. */
function p30State(array $counts, bool $fullFixtureSignature): string
{
    foreach (P30_EMPTY_TABLES as $name) {
        if (! isset($counts[$name]) || ! is_int($counts[$name]) || $counts[$name] < 0) {
            return 'BLOCKED';
        }
    }
    if ($fullFixtureSignature) {
        foreach (P30_EXPECTED as $name => $expected) {
            if ($counts[$name] !== $expected) {
                return 'BLOCKED';
            }
        }
        return 'ALREADY_APPLIED';
    }
    foreach (P30_EMPTY_TABLES as $name) {
        if ($counts[$name] !== 0) {
            return 'BLOCKED';
        }
    }
    return 'READY';
}

function p30SelfTest(): void
{
    $empty = array_fill_keys(P30_EMPTY_TABLES, 0);
    $assert = static function (bool $value, string $label): void {
        if (! $value) {
            throw new RuntimeException('P30 self-test failed: ' . $label);
        }
        echo 'PASS P30 ' . $label . PHP_EOL;
    };
    $assert(p30State($empty, false) === 'READY', 'empty isolated dataset is ready');
    $assert(p30State($empty, true) === 'BLOCKED', 'partial installation cannot be accepted');
    $nonempty = $empty;
    $nonempty['products'] = 1;
    $assert(p30State($nonempty, false) === 'BLOCKED', 'preexisting products stop installation');
    $nonempty = $empty;
    $nonempty['orders'] = 1;
    $assert(p30State($nonempty, false) === 'BLOCKED', 'preexisting orders stop installation');
    $installed = array_merge($empty, P30_EXPECTED);
    $assert(p30State($installed, true) === 'ALREADY_APPLIED', 'repeat run is idempotent');
    $installed['product_variants'] = 3;
    $assert(p30State($installed, true) === 'BLOCKED', 'damaged installed dataset stops');
    unset($empty['categories']);
    $assert(p30State($empty, false) === 'BLOCKED', 'missing required count fails closed');
    echo "RESULT: 7/7 P30 pure safety cases PASS (no DB).\n";
}

function p30GetCounts(PDO $pdo): array
{
    $counts = [];
    foreach (P30_EMPTY_TABLES as $table) {
        // Table names are constants; never built from client input.
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
    return $counts;
}

/** Detect only the named P30 fixture, never presume unrelated rows are ours. */
function p30HasCompleteSignature(PDO $pdo): bool
{
    $sum = static function (string $sql) use ($pdo): int {
        return (int) $pdo->query($sql)->fetchColumn();
    };
    if ($sum("SELECT COUNT(*) FROM categories WHERE slug IN ('p30-qa-kitchen', 'p30-qa-essentials') AND status=1 AND deleted_at IS NULL") !== 2
        || $sum("SELECT COUNT(*) FROM products WHERE slug IN ('p30-qa-ceramic-mug', 'p30-qa-kitchen-set', 'p30-qa-cotton-bag', 'p30-qa-notebook') AND inventory_version=1 AND status=1 AND deleted_at IS NULL") !== 4
        || $sum("SELECT COUNT(*) FROM shipping_charges WHERE campaign_id IS NULL AND area_name IN ('P30 QA Dhaka (Test)', 'P30 QA Outside Dhaka (Test)') AND status=1 AND deleted_at IS NULL") !== 2
        || $sum("SELECT COUNT(*) FROM storefront_pages p JOIN storefront_revisions r ON r.id=p.published_revision_id WHERE p.slug='home' AND p.mode='storefront' AND r.status='published' AND r.storefront_page_id=p.id") !== 1
    ) {
        return false;
    }
    return $sum("SELECT COUNT(*) FROM homepage_sections s JOIN storefront_revisions r ON r.id=s.storefront_revision_id JOIN storefront_pages p ON p.published_revision_id=r.id WHERE p.slug='home' AND p.mode='storefront' AND s.is_enabled=1 AND s.type IN ('category_grid','product_list','category_products')") === 3;
}

function p30ValidateSchema(PDO $pdo, string $root): void
{
    $source = P02TestSchemaGate::sourceInventory($root);
    $snapshot = P02TestSchemaGate::inspect($pdo);
    if (P02TestSchemaGate::evaluate($source, $snapshot)['status'] !== 'ALREADY_APPLIED') {
        throw new RuntimeException('Isolated baseline migration/schema review is not green.');
    }
    if (! in_array(P30_ACCOUNT_MIGRATION, $snapshot['applied'], true)
        || ! in_array('storefront_accounts', $snapshot['tables'], true)
        || ! isset($snapshot['columns']['orders']['storefront_account_id'])) {
        throw new RuntimeException('P28 isolated account migration is not fully applied.');
    }
    foreach (P30_EMPTY_TABLES as $table) {
        if (! in_array($table, $snapshot['tables'], true)) {
            throw new RuntimeException('Required isolated test table is missing: ' . $table);
        }
    }
}

function p30Insert(PDO $pdo, string $table, array $row): int
{
    // SQL fields/tables are entirely source-defined; values are prepared parameters.
    $names = array_keys($row);
    $columns = implode(', ', array_map(static fn (string $n): string => '`' . $n . '`', $names));
    $tokens = implode(', ', array_fill(0, count($names), '?'));
    $statement = $pdo->prepare('INSERT INTO `' . $table . '` (' . $columns . ') VALUES (' . $tokens . ')');
    if (! $statement->execute(array_values($row))) {
        throw new RuntimeException('Synthetic row INSERT refused.');
    }
    return (int) $pdo->lastInsertId();
}

function p30Uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $h = bin2hex($b);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4)
        . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

function p30MakeFixtures(PDO $pdo): void
{
    $now = gmdate('Y-m-d H:i:s');
    $categories = [];
    foreach ([
        ['slug' => 'p30-qa-kitchen', 'name' => 'Kitchen QA Demos'],
        ['slug' => 'p30-qa-essentials', 'name' => 'Essentials QA Demos'],
    ] as $i => $cat) {
        $categories[] = p30Insert($pdo, 'categories', [
            'name' => $cat['name'], 'slug' => $cat['slug'], 'parent_id' => null,
            'is_front_view' => 1, 'status' => 1, 'sort_order' => $i,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $products = [];
    foreach (P30_PRODUCTS as $i => $demo) {
        $id = p30Insert($pdo, 'products', [
            'category_id' => $categories[$demo['category']], 'name' => $demo['name'],
            'slug' => $demo['slug'], 'product_code' => $demo['code'],
            'new_price' => $demo['price'], 'purchase_price' => 0,
            'stock' => $demo['stock'], 'has_variants' => 0, 'inventory_version' => 1,
            'status' => 1, 'is_feature' => 1, 'is_free_delivery' => 0,
            'short_description' => 'Synthetic P30 test-only item. Not for real customers or fulfillment.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $products[] = $id;
        p30Insert($pdo, 'product_variants', [
            'product_id' => $id, 'sku' => $demo['code'] . '-DEFAULT',
            'option_signature' => 'default', 'stock' => $demo['stock'],
            'is_default' => 1, 'is_active' => 1, 'sort_order' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    foreach ([
        ['name' => 'P30 QA Dhaka (Test)', 'charge' => 60],
        ['name' => 'P30 QA Outside Dhaka (Test)', 'charge' => 120],
    ] as $shipping) {
        p30Insert($pdo, 'shipping_charges', [
            'campaign_id' => null, 'area_name' => $shipping['name'],
            'delivery_charge' => $shipping['charge'], 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $pageId = p30Insert($pdo, 'storefront_pages', [
        'slug' => 'home', 'mode' => 'storefront', 'lock_version' => 1,
        'draft_revision_id' => null, 'published_revision_id' => null,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $revision = p30Insert($pdo, 'storefront_revisions', [
        'storefront_page_id' => $pageId, 'revision_number' => 1,
        'status' => 'draft', 'schema_version' => 1,
        'settings' => json_encode([
            'schema_version' => 1, 'theme' => \App\Support\HomeSectionSchema::DEFAULT_THEME,
        ], JSON_THROW_ON_ERROR),
        'created_by' => null, 'updated_by' => null, 'published_by' => null,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $sectionKinds = [
        ['type' => 'category_grid', 'title' => 'Browse QA Categories', 'source' => 'manual', 'category' => null, 'sort' => 'manual'],
        ['type' => 'product_list', 'title' => 'Featured QA Products', 'source' => 'manual', 'category' => null, 'sort' => 'manual'],
        ['type' => 'category_products', 'title' => 'Kitchen QA Collection', 'source' => 'category', 'category' => $categories[0], 'sort' => 'newest'],
    ];
    $sectionIds = [];
    foreach ($sectionKinds as $index => $section) {
        $sectionIds[] = p30Insert($pdo, 'homepage_sections', [
            'storefront_revision_id' => $revision, 'section_key' => p30Uuid(),
            'type' => $section['type'], 'title' => $section['title'],
            'subtitle' => 'Isolated demo content - not a live storefront',
            'description' => null, 'is_enabled' => 1, 'show_desktop' => 1,
            'show_mobile' => 1, 'sort_order' => $index, 'source_type' => $section['source'],
            'category_id' => $section['category'], 'include_descendants' => 0,
            'product_sort' => $section['sort'], 'product_limit' => 8,
            'layout' => json_encode(['desktop_columns' => 4, 'mobile_columns' => 2], JSON_THROW_ON_ERROR),
            'styles' => '{}', 'settings' => '{}',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    foreach ($categories as $index => $id) {
        p30Insert($pdo, 'homepage_section_categories', [
            'homepage_section_id' => $sectionIds[0], 'category_id' => $id,
            'sort_order' => $index, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    foreach ($products as $index => $id) {
        p30Insert($pdo, 'homepage_section_products', [
            'homepage_section_id' => $sectionIds[1], 'product_id' => $id,
            'sort_order' => $index, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    // Published revision becomes externally visible only at transaction commit.
    $status = $pdo->prepare("UPDATE storefront_revisions SET status='published', published_at=?, updated_at=? WHERE id=? AND status='draft'");
    $status->execute([$now, $now, $revision]);
    if ($status->rowCount() !== 1) {
        throw new RuntimeException('Synthetic revision could not be marked as published.');
    }
    $pin = $pdo->prepare('UPDATE storefront_pages SET published_revision_id=?, updated_at=? WHERE id=? AND published_revision_id IS NULL');
    $pin->execute([$revision, $now, $pageId]);
    if ($pin->rowCount() !== 1) {
        throw new RuntimeException('Synthetic homepage revision could not be pinned.');
    }
    p30Insert($pdo, 'homepage_audit_events', [
        'storefront_page_id' => $pageId, 'storefront_revision_id' => $revision,
        'actor_id' => null, 'event' => 'qa_fixture_publish',
        'from_version' => 0, 'to_version' => 1,
        'summary' => json_encode(['source' => 'P30', 'synthetic_only' => true], JSON_THROW_ON_ERROR),
        'created_at' => $now,
    ]);
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}
$mode = $argv[1] ?? '--check';
try {
    if ($mode === '--self-test' && count($argv) === 2) {
        p30SelfTest();
        exit(0);
    }
    if (! in_array($mode, ['--check', '--apply'], true) || count($argv) !== 2) {
        throw new RuntimeException('Use --check, --apply or --self-test.');
    }
    $root = dirname(__DIR__, 2);
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    p30ValidateSchema($pdo, $root);
    $counts = p30GetCounts($pdo);
    $signature = p30HasCompleteSignature($pdo);
    $state = p30State($counts, $signature);
    echo 'P30 ISOLATED DATA STATUS: ' . $state . PHP_EOL;
    foreach (['products', 'product_variants', 'categories', 'shipping_charges',
        'storefront_pages', 'storefront_revisions', 'orders'] as $table) {
        echo 'INFO test_' . $table . '=' . $counts[$table] . PHP_EOL;
    }
    if ($mode === '--check') {
        echo "READ-ONLY. No fixtures/data modified.\n";
        exit($state === 'BLOCKED' ? 3 : 0);
    }
    if ($state === 'ALREADY_APPLIED') {
        echo "PASS P30 fixtures already installed. No writes repeated.\n";
        exit(0);
    }
    if ($state !== 'READY') {
        throw new RuntimeException('Existing or partial data found. Refusing to overwrite or mix synthetic fixtures.');
    }
    if (getenv('P30_FIXTURE_APPROVED') !== P30_APPROVAL) {
        throw new RuntimeException('Explicit P30 synthetic-data approval missing; no INSERT attempted.');
    }
    // Serialize competing fixture setup attempts. No DDL, no other data modified.
    $lockName = 'p30_p01_synthetic_data_lock';
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Other P30 setup is active; no writes started.');
    }
    try {
        P02TestSchemaGate::assertIsolatedConnection($pdo);
        p30ValidateSchema($pdo, $root);
        $fresh = p30GetCounts($pdo);
        if (p30State($fresh, p30HasCompleteSignature($pdo)) !== 'READY') {
            throw new RuntimeException('Readiness changed during setup; no writes permitted.');
        }
        $pdo->beginTransaction();
        try {
            p30MakeFixtures($pdo);
            $after = p30GetCounts($pdo);
            if (p30State($after, p30HasCompleteSignature($pdo)) !== 'ALREADY_APPLIED') {
                throw new RuntimeException('Post-insert fixture contract failed; rolling back.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    } finally {
        $unlock = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $unlock->execute([$lockName]);
    }
    echo "PASS P30: 2 synthetic categories, 4 products + 4 default variants, 2 global shipping areas, 1 published homepage (3 sections).\n";
    echo "PASS P30: ONLY p01_campaign_test modified; no production database, migration, .env or source file changed.\n";
} catch (Throwable $e) {
    // No exception detail that could reveal DSN, credential, or customer data.
    if ($e instanceof RuntimeException) {
        fwrite(STDERR, 'BLOCKED P30: ' . $e->getMessage() . PHP_EOL);
    } else {
        fwrite(STDERR, 'BLOCKED P30: isolated fixture setup failed (' . get_class($e) . ').' . PHP_EOL);
    }
    exit(2);
}
