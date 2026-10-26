<?php

declare(strict_types=1);

/**
 * P16 Reviews navigation contract (offline, no Laravel/DB bootstrap).
 * Run: php tools/qa/check-p16-reviews-sidebar.php
 */

$root = dirname(__DIR__, 2);
$config = require $root . '/config/adminlte.php';
$menu = $config['menu'] ?? [];
$routes = file_get_contents($root . '/routes/admin.php') ?: '';
$controller = file_get_contents($root . '/app/Http/Controllers/Admin/ReviewController.php') ?: '';
$gates = file_get_contents($root . '/app/Providers/AppServiceProvider.php') ?: '';
$form = file_get_contents($root . '/resources/views/admin/reviews/partials/form.blade.php') ?: '';

$matches = [];
$reviewPosition = null;
$landingPosition = null;
$marketingPosition = null;
foreach ($menu as $position => $item) {
    if (($item['header'] ?? null) === 'LANDING PAGE') {
        $landingPosition = $position;
    }
    if (($item['header'] ?? null) === 'MARKETING') {
        $marketingPosition = $position;
    }
    if (($item['url'] ?? null) === 'admin/reviews') {
        $matches[] = $item;
        $reviewPosition = $position;
    }
}

$review = count($matches) === 1 ? $matches[0] : [];
$checks = [
    'Exactly one Customer Reviews menu entry' => count($matches) === 1
        && ($review['text'] ?? null) === 'Customer Reviews',
    'Review menu points to existing index URL' => ($review['url'] ?? null) === 'admin/reviews'
        && str_contains($routes, "Route::prefix('reviews')")
        && str_contains($routes, "[ReviewController::class, 'index']"),
    'Review menu uses existing admin-only Gate' => ($review['can'] ?? null) === 'admin-only'
        && str_contains($gates, "Gate::define('admin-only'"),
    'Review menu active highlight covers review routes' => ($review['active'] ?? null) === ['admin/reviews*'],
    'Review menu uses existing Font Awesome icon style' => ($review['icon'] ?? null) === 'fas fa-fw fa-star',
    'Review menu appears under Landing Page section' => is_int($landingPosition)
        && is_int($reviewPosition)
        && is_int($marketingPosition)
        && $landingPosition < $reviewPosition
        && $reviewPosition < $marketingPosition,
    'Existing Homepage Builder access Gate retained' => str_contains($gates, "Gate::define('isolated-homepage-builder'")
        && (bool) array_filter($menu, static fn (array $item): bool =>
            ($item['url'] ?? null) === 'admin/storefront/home'
            && ($item['can'] ?? null) === 'isolated-homepage-builder'),
    'Review CRUD retains controller admin authorization' => str_contains($controller, 'private function adminOnly(): void')
        && str_contains($controller, "abort(403, 'Unauthorized access.')"),
    'Existing form supports optional global reviews' => str_contains($form, '<option value=""')
        && preg_match('/>\s*General\s*\/\s*Store Review(?:\s*\(Homepage Builder\))?\s*<\/option>/i', $form) === 1
        && str_contains($controller, "'campaign_id' => ['nullable', 'exists:campaigns,id']"),
];

$passed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $passed += $ok ? 1 : 0;
}
echo "RESULT: {$passed}/" . count($checks) . ' P16 Reviews sidebar contracts ' .
    ($passed === count($checks) ? 'PASS' : 'FAIL') . ' (offline, no DB).' . PHP_EOL;
exit($passed === count($checks) ? 0 : 1);
