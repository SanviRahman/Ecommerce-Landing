<?php

declare(strict_types=1);

require_once __DIR__.'/../../app/Support/LocalStorefrontBrowseAccess.php';

use App\Support\LocalStorefrontBrowseAccess as Gate;

// Pure-PHP preflight matrix: DOES NOT boot Laravel or access a database.
$base = ['local', true, true, 'GET', 'storefront.home', true];
$checks = [
    'Approved active admin GET home' => [$base, true],
    'Approved active admin GET shop' => [['local', true, true, 'GET', 'storefront.shop', true], true],
    'Approved active admin GET category' => [['local', true, true, 'GET', 'storefront.category', true], true],
    'Approved active admin GET product' => [['local', true, true, 'GET', 'storefront.product', true], true],
    'Approved active admin GET help' => [['local', true, true, 'GET', 'storefront.help', true], true],
    'Approved active admin GET contact' => [['local', true, true, 'GET', 'storefront.contact', true], true],
    'No approval means deny' => [['local', true, false, 'GET', 'storefront.home', true], false],
    'Production environment denied' => [['production', true, true, 'GET', 'storefront.home', true], false],
    'Testing environment denied' => [['testing', true, true, 'GET', 'storefront.home', true], false],
    'Debug false denied' => [['local', false, true, 'GET', 'storefront.home', true], false],
    'Guest denied' => [['local', true, true, 'GET', 'storefront.home', false], false],
    'POST denied even for admin' => [['local', true, true, 'POST', 'storefront.home', true], false],
    'HEAD denied even for admin' => [['local', true, true, 'HEAD', 'storefront.home', true], false],
    'Cart GET denied' => [['local', true, true, 'GET', 'storefront.cart', true], false],
    'Checkout GET denied' => [['local', true, true, 'GET', 'storefront.checkout', true], false],
    'Account login GET denied' => [['local', true, true, 'GET', 'storefront.account.login', true], false],
    'Account profile GET denied' => [['local', true, true, 'GET', 'storefront.account.index', true], false],
    'Missing route denied' => [['local', true, true, 'GET', null, true], false],
    'Admin route not whitelisted' => [['local', true, true, 'GET', 'admin.storefront.home.preview', true], false],
];
$ok = 0;
foreach ($checks as $name => [$args, $expected]) {
    $actual = Gate::matchesContext(...$args);
    $pass = $actual === $expected;
    echo ($pass ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
    if ($pass) $ok++;
}
echo "RESULT: $ok/".count($checks).' P25 pure context gates '.($ok===count($checks)?'PASS':'FAIL')." (no Laravel/DB).\n";
exit($ok === count($checks) ? 0 : 1);
