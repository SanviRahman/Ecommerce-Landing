<?php

/**
 * Fast, dependency-free source safeguards. This checks code patterns only,
 * NOT runtime correctness, migrations, HTTP authorization or concurrency.
 * Run: php tools/qa/verify-source.php
 */
$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = [
    'GET maintenance routes are read-only preview' => substr_count($read('routes/command.php'), 'Route::get(') === 1
        && str_contains($read('routes/command.php'), "Route::get('/database-migrations'"),
    'HTTP migrate/seed routes removed' => ! preg_match("~Route::post\\('/(?:migrate|seed|migrate-fresh|migrate-fresh-seed)'~", $read('routes/command.php')),
    'Maintenance routes remain POST' => substr_count($read('routes/command.php'), 'Route::post(') === 13,
    'Database migration POST is confirmation gated' => str_contains($read('routes/command.php'), "Route::post('/database-migrations'")
        && str_contains($read('app/Http/Controllers/Admin/DatabaseMigrationController.php'), "Hash::check")
        && str_contains($read('app/Http/Controllers/Admin/DatabaseMigrationController.php'), "--force"),
    'Campaign global-product fallback removed' => ! str_contains($read('app/Http/Controllers/Admin/CampaignOrderController.php'), 'Product::query()'),
    'Campaign membership validation present' => str_contains($read('app/Http/Controllers/Admin/CampaignOrderController.php'), 'One or more selected products are not available in this campaign.'),
    'Order existing item IDs scoped and distinct' => str_contains($read('app/Http/Controllers/Admin/OrderController.php'), 'distinct:strict'),
    'External UUID omission preserved' => str_contains($read('app/Http/Requests/Api/StoreExternalOrderRequest.php'), "'sync_uuid' => \$this->input('sync_uuid'),"),
    'Success token path redacted' => str_contains($read('app/Http/Middleware/LogRequestLifecycle.php'), "'success/[REDACTED]'"),
    'Active account gate' => str_contains($read('routes/admin.php'), "['auth', 'role:admin,employee']"),
    'Admin static default password removed' => ! str_contains($read('database/seeders/AdminUserSeeder.php'), "Hash::make('password')"),
    'Employee static password removed' => ! str_contains($read('database/seeders/EmployeeSeeder.php'), "Hash::make('12345678')"),
    'Frontend summary escapes data' => str_contains($read('public/frontend/js/home.js'), 'function escapeSummaryText(value)')
        && str_contains($read('public/frontend/js/home.js'), '${escapeSummaryText(item.name)}')
        && str_contains($read('public/frontend/js/home.js'), '${safeSummaryImage(item.image)}')
        && str_contains($read('resources/views/frontend/pages/home.blade.php'), "asset('frontend/js/home.js')"),
    'CSRF protected maintenance forms' => str_contains($read('resources/views/vendor/adminlte/partials/sidebar/menu-item-link.blade.php'), '@csrf'),
    'CSRF protected courier sync forms' => str_contains($read('resources/views/admin/orders/index.blade.php'), 'sync-webhook-form'),
];
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (! $passed) ++$failed;
}
echo sprintf("Result: %d/%d source checks pass.\n", count($checks) - $failed, count($checks));
exit($failed ? 1 : 0);
