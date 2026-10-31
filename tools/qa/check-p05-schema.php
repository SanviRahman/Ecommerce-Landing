<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Support/HomeSectionSchema.php';
use App\Support\HomeSectionSchema as Schema;

$pass = 0;
$check = static function (string $label, bool $result) use (&$pass): void {
    if (! $result) {
        fwrite(STDERR, 'FAIL ' . $label . "\n");
        exit(1);
    }
    echo 'PASS ' . $label . "\n";
    $pass++;
};
$throws = static function (callable $callback): bool {
    try {
        $callback();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
};
$check('Exactly five typed V1 components', count(Schema::TYPES) === 5 && isset(Schema::TYPES['hero_slider']));
$check('Unknown theme token rejected', $throws(fn () => Schema::normalizeColors(['danger_css' => '#FFFFFF'])));
$check('Valid colors normalized to uppercase', Schema::normalizeColors(['background' => '#aaBBcc']) === ['background' => '#AABBCC']);
$check('Blank section color explicitly inherits', Schema::normalizeColors(['background' => '']) === ['background' => null]);
$check('Raw CSS injection rejected', $throws(fn () => Schema::normalizeColors(['background' => 'red;position:fixed'])));
$check('Protected/internal traversal URLs refused', $throws(fn () => Schema::normalizeUrl('/admin/orders'))
    && $throws(fn () => Schema::normalizeUrl('/%61dmin/orders'))
    && $throws(fn () => Schema::normalizeUrl('/shop/../admin')));
$check('HTTPS external links allowed', Schema::normalizeUrl('https://example.org/item?ref=1') === 'https://example.org/item?ref=1');
$check('Internal absolute path allowed', Schema::normalizeUrl('/shop/featured') === '/shop/featured');
$check('Relative and protocol-relative URLs refused', $throws(fn () => Schema::normalizeUrl('//evil.test'))
    && $throws(fn () => Schema::normalizeUrl('javascript:alert(1)')));
$check('HTTP and embedded whitespace refused', $throws(fn () => Schema::normalizeUrl('http://example.org'))
    && $throws(fn () => Schema::normalizeUrl("https://example.org\nX-Injected:1")));
Schema::validateThemeContrast(Schema::DEFAULT_THEME);
$check('Default green storefront theme readable', true);
$check('Low-contrast primary button rejected', $throws(fn () => Schema::validateThemeContrast([
    'button_background' => '#FFFFFF', 'button_text' => '#FFFFFF',
])));
$check('Two different checked colors have symmetric contrast', abs(Schema::contrast('#FFFFFF', '#000000') - 21.0) < 0.1);
echo "RESULT: $pass/$pass P05 pure schema checks PASS (no DB).\n";
