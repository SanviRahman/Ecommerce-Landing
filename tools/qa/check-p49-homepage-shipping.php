<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = file_get_contents($root . '/routes/admin.php');
$sidebar = file_get_contents($root . '/config/adminlte.php');
$controller = @file_get_contents($root . '/app/Http/Controllers/Admin/HomepageShippingChargeController.php');
$view = @file_get_contents($root . '/resources/views/admin/homepage-shipping-charges/index.blade.php');
$model = file_get_contents($root . '/app/Models/ShippingCharge.php');
$checkout = file_get_contents($root . '/app/Support/P32LocalStorefrontAccess.php');

$checks = [
    'Global shipping uses the existing ShippingCharge model' => str_contains($model, 'whereNull(\'campaign_id\')'),
    'Checkout readiness expects active global charges' => str_contains($checkout, "whereNull('campaign_id')"),
    'Sidebar links to named Homepage Shipping Charges URL' => str_contains($sidebar, 'admin/homepage-shipping-charges'),
    'Admin-only route registration' => str_contains($routes, 'HomepageShippingChargeController::class') && str_contains($routes, "'role:admin'"),
    'Route exposes GET / POST / PATCH / DELETE' => str_contains($routes, "prefix('homepage-shipping-charges')") && str_contains($routes, "Route::get('/', [HomepageShippingChargeController::class, 'index'])") && str_contains($routes, "Route::delete('/{charge}', [HomepageShippingChargeController::class, 'destroy'])"),
    'Controller scopes global rows' => is_string($controller) && substr_count($controller, "whereNull('campaign_id')") >= 2,
    'Controller disallows foreign campaign row edits' => is_string($controller) && str_contains($controller, 'abort_unless('),
    'Controller validates both fee and label' => is_string($controller) && str_contains($controller, 'delivery_charge') && str_contains($controller, 'area_name'),
    'Controller soft-deletes records instead of hard delete' => is_string($controller) && str_contains($controller, '->delete()') && ! str_contains($controller, 'forceDelete'),
    'Controller ignores foreign campaign_id input' => is_string($controller) && ! str_contains($controller, "input('campaign_id')"),
    'Admin view contains CSRF-protected forms' => is_string($view) && str_contains($view, '@csrf'),
    'Admin view has shipping list, add and edit controls' => is_string($view) && str_contains($view, 'Add Delivery Area') && str_contains($view, 'Save Changes'),
    'No global-to-campaign copying in new controller' => is_string($controller) && ! str_contains($controller, 'Campaign::'),
];

$failures = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    $failures += ! $passed;
}
printf("P49 contract checks: %d/%d passed\n", count($checks) - $failures, count($checks));
exit($failures === 0 ? 0 : 1);
