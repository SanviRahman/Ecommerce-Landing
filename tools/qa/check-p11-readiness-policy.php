<?php

declare(strict_types=1);

require_once __DIR__ . '/P11ReadinessPolicy.php';

$defaults = [
    'active_legacy_products' => 0,
    'active_managed_products' => 3,
    'managed_stock_mismatches' => 0,
    'invalid_simple_defaults' => 0,
    'invalid_variable_options' => 0,
    'broken_published_revisions' => 0,
    'published_homepages' => 1,
    'global_shipping_areas' => 2,
];
$cases = [
    'Representative staging data can only reach staging-candidate status' => [$defaults, 'STAGING_DATA_CANDIDATE_ONLY', 0],
    'Legacy active inventory blocks release review' => [['active_legacy_products' => 1], 'BLOCKED_DATA_READINESS', 1],
    'Managed stock mismatch blocks release review' => [['managed_stock_mismatches' => 1], 'BLOCKED_DATA_READINESS', 1],
    'Missing default SKU blocks release review' => [['invalid_simple_defaults' => 1], 'BLOCKED_DATA_READINESS', 1],
    'Incomplete variable SKU blocks release review' => [['invalid_variable_options' => 1], 'BLOCKED_DATA_READINESS', 1],
    'Broken published revision blocks release review' => [['broken_published_revisions' => 1], 'BLOCKED_DATA_READINESS', 1],
    'Empty managed catalog blocks release review' => [['active_managed_products' => 0], 'BLOCKED_DATA_READINESS', 1],
    'Missing global shipping blocks release review' => [['global_shipping_areas' => 0], 'BLOCKED_DATA_READINESS', 1],
    'Missing published homepage blocks release review' => [['published_homepages' => 0], 'BLOCKED_DATA_READINESS', 1],
    'Combined stock and shipping blockers stay visible' => [['managed_stock_mismatches' => 2, 'global_shipping_areas' => 0], 'BLOCKED_DATA_READINESS', 2],
    'Negative metrics are invalid' => [['active_managed_products' => -1], 'INVALID', 0],
    'Missing metrics are invalid' => [['global_shipping_areas' => null], 'INVALID', 0],
];
$pass = 0;
foreach ($cases as $label => [$changes, $wantStatus, $wantCount]) {
    try {
        $result = P11ReadinessPolicy::evaluate(array_replace($defaults, $changes));
        $ok = $result['status'] === $wantStatus && count($result['blockers']) === $wantCount;
    } catch (InvalidArgumentException) {
        $ok = $wantStatus === 'INVALID';
    }
    if (! $ok) {
        fwrite(STDERR, 'FAIL ' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS ' . $label . PHP_EOL;
    $pass++;
}
echo 'RESULT: P11 readiness policy ' . $pass . '/' . count($cases) . " PASS (no database).\n";
