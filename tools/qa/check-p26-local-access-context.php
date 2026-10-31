<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Support/LocalStorefrontBrowseAccess.php';
require __DIR__ . '/../../app/Support/P26IsolatedCommerceQaAccess.php';

use App\Support\LocalStorefrontBrowseAccess;
use App\Support\P26IsolatedCommerceQaAccess;

$cases = [
    ['approved isolated loopback QA', true, P26IsolatedCommerceQaAccess::matchesContext('local', true, '127.0.0.1', '127.0.0.1', 8088)],
    ['production QA denied', false, P26IsolatedCommerceQaAccess::matchesContext('production', true, '127.0.0.1', '127.0.0.1', 8088)],
    ['testing environment QA denied', false, P26IsolatedCommerceQaAccess::matchesContext('testing', true, '127.0.0.1', '127.0.0.1', 8088)],
    ['flag disabled QA denied', false, P26IsolatedCommerceQaAccess::matchesContext('local', false, '127.0.0.1', '127.0.0.1', 8088)],
    ['non-loopback remote IP denied', false, P26IsolatedCommerceQaAccess::matchesContext('local', true, '192.168.1.3', '127.0.0.1', 8088)],
    ['public hostname denied', false, P26IsolatedCommerceQaAccess::matchesContext('local', true, '127.0.0.1', 'public.example', 8088)],
    ['original local server port 8000 denied', false, P26IsolatedCommerceQaAccess::matchesContext('local', true, '127.0.0.1', '127.0.0.1', 8000)],
    ['arbitrary port denied', false, P26IsolatedCommerceQaAccess::matchesContext('local', true, '127.0.0.1', '127.0.0.1', 9000)],
    ['IPv6 loopback allowed for isolated', true, P26IsolatedCommerceQaAccess::matchesContext('local', true, '::1', '::1', 8088)],
    ['read-only active admin approved GET', true, LocalStorefrontBrowseAccess::matchesContext('local', true, true, 'GET', 'storefront.home', true)],
    ['read-only guest denied', false, LocalStorefrontBrowseAccess::matchesContext('local', true, true, 'GET', 'storefront.home', false)],
    ['read-only POST denied', false, LocalStorefrontBrowseAccess::matchesContext('local', true, true, 'POST', 'storefront.home', true)],
    ['read-only cart GET denied', false, LocalStorefrontBrowseAccess::matchesContext('local', true, true, 'GET', 'storefront.cart', true)],
    ['read-only customer account denied', false, LocalStorefrontBrowseAccess::matchesContext('local', true, true, 'GET', 'storefront.account.login', true)],
];
$passed=0;
foreach ($cases as [$label,$expected,$actual]) {
    $ok=$actual===$expected;
    echo ($ok?'PASS ':'FAIL ').$label."\n";
    if ($ok) $passed++;
}
echo "RESULT: {$passed}/".count($cases)." P26 context checks ".($passed===count($cases)?'PASS':'FAIL')." (pure PHP)\n";
exit($passed===count($cases)?0:1);
