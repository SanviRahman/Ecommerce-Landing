// Dependency-free regression for extracted frontend JS helper functions.
// Checks escaping and URL scheme handling only; not browser/DB tests.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const js = readFileSync(new URL('../../public/frontend/js/home.js', import.meta.url), 'utf8');
const blade = readFileSync(new URL('../../resources/views/frontend/pages/home.blade.php', import.meta.url), 'utf8');
const helperStart = /(?:^|\n)[ \t]*function\s+escapeSummaryText\s*\(\s*value\s*\)\s*\{/m.exec(js);
assert.ok(helperStart, 'escapeSummaryText helper is present in public/frontend/js/home.js');
const start = helperStart.index;
const renderStart = /(?:^|\n)[ \t]*function\s+renderSummary\s*\(\s*\)\s*\{/m.exec(js.slice(start));
assert.ok(renderStart, 'renderSummary function is present after escapeSummaryText');
const helpers = js.slice(start, start + renderStart.index);
assert.match(helpers, /function\s+safeSummaryImage\s*\(/, 'safeSummaryImage helper is present');
const load = new Function('noImage', 'window', `${helpers}\nreturn { escapeSummaryText, safeSummaryImage };`);
const {escapeSummaryText, safeSummaryImage} = load('/frontend/images/no-image.svg', {location: {origin: 'https://example.test'}});

assert.equal(escapeSummaryText(`<img src=x onerror="alert('x')">`), '&lt;img src=x onerror=&quot;alert(&#39;x&#39;)&quot;&gt;');
assert.equal(escapeSummaryText('A&B'), 'A&amp;B');
assert.equal(escapeSummaryText(null), '');
assert.equal(safeSummaryImage('javascript:alert(1)'), 'https://example.test/frontend/images/no-image.svg');
assert.equal(safeSummaryImage('data:image/svg+xml,<svg onload=alert(1)>'), 'https://example.test/frontend/images/no-image.svg');
assert.equal(safeSummaryImage('/photos/product.png'), 'https://example.test/photos/product.png');
assert.ok(js.includes('${escapeSummaryText(item.name)}'), 'product names are escaped in summary markup');
assert.ok(js.includes('${safeSummaryImage(item.image)}'), 'summary image URLs are sanitized');
assert.ok(blade.includes("asset('frontend/js/home.js')"), 'Blade references extracted JS');
console.log('PASS summary escaping, URL guards and extracted asset reference');
