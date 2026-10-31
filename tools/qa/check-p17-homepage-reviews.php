<?php

declare(strict_types=1);

/**
 * P17 offline contract: review eligibility must remain global/active, while the
 * existing admin form provides an explicit, discoverable Global Review path.
 * No Laravel boot, MySQL access, credentials or DB writes.
 */
$root = dirname(__DIR__, 2);
$read = static function (string $name) use ($root): string {
    $data = @file_get_contents($root . DIRECTORY_SEPARATOR . $name);
    if (! is_string($data)) {
        fwrite(STDERR, "Missing source: $name\n");
        exit(2);
    }
    return $data;
};

$controller = $read('app/Http/Controllers/Admin/ReviewController.php');
$reviewForm = $read('resources/views/admin/reviews/partials/form.blade.php');
$reviewIndex = $read('resources/views/admin/reviews/index.blade.php');
$reviewTable = $read('resources/views/admin/reviews/partials/table.blade.php');
$builderView = $read('resources/views/admin/storefront/homepage.blade.php');
$builderController = $read('app/Http/Controllers/Admin/HomepageBuilderController.php');
$builderService = $read('app/Services/Storefront/HomepageBuilder.php');
$adminRoutes = $read('routes/admin.php');
$seeder = $read('database/seeders/ReviewSeeder.php');

$tests = [
    'Admin Review create/update remain admin-only' =>
        substr_count($controller, '$this->adminOnly();') >= 6,
    'Review form retains CSRF and PUT method' =>
        str_contains($reviewForm, '@csrf') && str_contains($reviewForm, "@method('PUT')"),
    'Existing Reviews CRUD routes remain present' =>
        str_contains($adminRoutes, "Route::get('/', [ReviewController::class, 'index'])") &&
        str_contains($adminRoutes, "Route::put('/{review}', [ReviewController::class, 'update'])"),
    'Builder only lists active global reviews' =>
        str_contains($builderController, "Review::query()->whereNull('campaign_id')->where('status', true)"),
    'Builder rejects campaigns on item creation and publish' =>
        str_contains($builderService, "->whereNull('campaign_id')->where('status', true)->exists()") &&
        str_contains($builderService, "Choose an active public/global review."),
    'Preview does not show campaign-specific reviews' =>
        str_contains($builderController, "->whereNull('campaign_id')->get()->keyBy('id')"),
    'Review seeder intentionally has sample global and campaign rows (not executed)' =>
        str_contains($seeder, "'campaign_id' => null") &&
        str_contains($seeder, "'campaign_id' => \$campaignId"),
    'Review create form explicitly selects General/Storefront' =>
        str_contains($reviewForm, '@selected(! $isEdit || $review->campaign_id === null)'),
    'Review form explains campaign/global eligibility and impact' =>
        str_contains($reviewForm, 'Only active General / Store Review') &&
        str_contains($reviewForm, 'campaign landing page'),
    'Create/update normalize blank campaign before nullable validation' =>
        substr_count($controller, "\$request->merge(['campaign_id' => \$request->filled('campaign_id')") === 2,
    'Blank campaign selection stored as null for both create and update' =>
        substr_count($controller, "'campaign_id' => \$request->filled('campaign_id') ? (int) \$request->input('campaign_id') : null") === 2,
    'Review Management supports explicit global-only filter' =>
        str_contains($controller, "\$request->input('campaign_id') === 'global'") &&
        str_contains($controller, "\$query->whereNull('campaign_id')"),
    'Review list offers accessible Global/Store filter and preserves query' =>
        str_contains($reviewIndex, 'value="global"') &&
        str_contains($reviewIndex, "request('campaign_id') === 'global'"),
    'Review rows distinguish Global and Campaign scopes' =>
        str_contains($reviewTable, 'Global / Storefront') &&
        str_contains($reviewTable, 'Campaign only'),
    'Builder empty state offers authorized Review Management action' =>
        str_contains($builderView, '@if($reviews->isEmpty())') &&
        str_contains($builderView, "route('admin.reviews.index', ['campaign_id' => 'global'])") &&
        str_contains($builderView, 'No active Global / Store Review'),
    'Builder disabled Add Item avoids impossible review submit' =>
        str_contains($builderView, "@disabled(\$section['type']==='reviews' && \$reviews->isEmpty())"),
];

$pass = 0;
foreach ($tests as $label => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL '), $label, PHP_EOL;
    $pass += (int) $ok;
}
echo "RESULT: $pass/", count($tests), " P17 review/Builder source contracts ",
    $pass === count($tests) ? 'PASS' : 'FAIL', " (no Laravel/MySQL).\n";
exit($pass === count($tests) ? 0 : 1);
