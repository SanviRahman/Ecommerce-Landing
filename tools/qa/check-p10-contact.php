<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Support/StorefrontContactLinks.php';

use App\Support\StorefrontContactLinks;

$checks = 0;
$test = static function (string $name, bool $ok) use (&$checks): void {
    if (! $ok) {
        fwrite(STDERR, 'FAIL ' . $name . PHP_EOL);
        exit(1);
    }
    $checks++;
    echo 'PASS ' . $name . PHP_EOL;
};

$links = StorefrontContactLinks::fromFields([]);
$test('Missing configuration renders no invented support actions', $links === []);

$links = StorefrontContactLinks::fromFields([
    'whatsapp_number' => '01700 000 001', 'messenger_link' => 'https://m.me/example.business',
    'email' => 'hello@example.org', 'phone' => '+880 1700 000 002',
]);
$test('Only four configured action types displayed', count($links) === 4);
$test('Local BD WhatsApp number receives country prefix', $links[0]['href'] === 'https://wa.me/8801700000001');
$test('Approved Messenger page is preserved', $links[1]['href'] === 'https://m.me/example.business' && $links[1]['external']);
$test('Email becomes a mailto link', $links[2]['href'] === 'mailto:hello@example.org' && ! $links[2]['external']);
$test('International phone becomes a telephone link', $links[3]['href'] === 'tel:+8801700000002');

$links = StorefrontContactLinks::fromFields([
    'whatsapp_number' => 'javascript:alert(1)',
    'messenger_link' => 'https://m.me.evil.example/merchant',
    'email' => 'not-an-email', 'phone' => 'tel:javascript:alert(1)',
]);
$test('Invalid and disguised targets are all refused', $links === []);
$test('HTTP messenger target refused', StorefrontContactLinks::fromFields(['messenger_link' => 'http://m.me/shop']) === []);
$test('Messenger URL with embedded credentials refused', StorefrontContactLinks::fromFields(['messenger_link' => 'https://user:password@m.me/shop']) === []);
$test('Direct messenger thread allowed', StorefrontContactLinks::fromFields(['messenger_link' => 'https://www.messenger.com/t/shop'])[0]['href'] === 'https://www.messenger.com/t/shop');
$test('Facebook message thread allowed', StorefrontContactLinks::fromFields(['messenger_link' => 'https://www.facebook.com/messages/t/page'])[0]['label'] === 'Messenger');
$test('Generic Facebook pages are not treated as Messenger chat', StorefrontContactLinks::fromFields(['messenger_link' => 'https://facebook.com/random-page']) === []);
$test('Bangladesh country code not duplicated', StorefrontContactLinks::fromFields(['whatsapp_number' => '+8801700000001'])[0]['href'] === 'https://wa.me/8801700000001');
$test('Phone with bad characters is refused', StorefrontContactLinks::fromFields(['phone' => '+8801700000002;1234']) === []);
$test('Missing or undersized telephone refused', StorefrontContactLinks::fromFields(['phone' => '123']) === []);
$test('Untrusted URL fragment refused', StorefrontContactLinks::fromFields(['messenger_link' => 'https://m.me/shop#javascript:bad']) === []);

printf("RESULT: %d/%d P10 configured-contact checks PASS (pure PHP, no MySQL).\n", $checks, $checks);
