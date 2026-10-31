/** Static, dependency-free verification for the public frontend asset extraction. */
import assert from 'node:assert/strict';
import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
const root = resolve(fileURLToPath(new URL('../../', import.meta.url)));
const read = path => readFileSync(join(root, path), 'utf8');
const frontend = 'resources/views/frontend';
const pages = [
  'layouts/master.blade.php',
  'pages/home.blade.php',
  'pages/success.blade.php',
  'pages/track-order.blade.php',
  'partials/footer.blade.php',
  'partials/header.blade.php',
  'partials/tracking-pixels.blade.php',
  'partials/datalayer-events.blade.php',
];
const views = pages.map(name => ({ name, source: read(`${frontend}/${name}`) }));
let tested = 0;
function check(condition, message) { assert.ok(condition, message); tested++; console.log('PASS', message); }
for (const { name, source } of views) {
  check(!/<style\b/i.test(source), `${name}: no embedded style block`);
  check(!/\bonerror\s*=/i.test(source), `${name}: no inline image error JavaScript`);
  if (name !== 'partials/tracking-pixels.blade.php') {
    check(!/<script\s*(?:type=\"text\/javascript\")?\s*>/i.test(source), `${name}: no inline executable script block`);
  }
  const regex = /asset\('([^']+\.(?:css|js|svg))'\)/g;
  for (const match of source.matchAll(regex)) {
    check(existsSync(join(root, 'public', match[1])), `${name}: public/${match[1]} exists`);
  }
}
const home = read(`${frontend}/pages/home.blade.php`);
const success = read(`${frontend}/pages/success.blade.php`);
const tracking = read(`${frontend}/partials/tracking-pixels.blade.php`);
const master = read(`${frontend}/layouts/master.blade.php`);
const eventBridge = read(`${frontend}/partials/datalayer-events.blade.php`);
check(home.includes('id="sfaHomeConfig"') && home.includes('JSON_HEX_TAG'), 'Home receives safely HTML-escaped dynamic data');
check(success.includes('id="sfaSuccessConfig"') && success.includes('JSON_HEX_TAG'), 'Purchase success receives safely HTML-escaped dynamic data');
check(tracking.includes('id="sfaPixelConfig"') && tracking.includes('JSON_HEX_TAG'), 'Pixel IDs are passed as data, not generated JS');
check(eventBridge.includes('id="sfaTrackingConfig"') && eventBridge.includes('JSON_HEX_TAG'), 'Page tracking bridge receives safe configuration');
check(master.indexOf("asset('frontend/css/buttons.css')") > master.indexOf("@stack('css')"), 'Green button CSS loads last');
check(master.includes("asset('vendor/jquery/jquery.min.js')") && master.includes("asset('vendor/bootstrap/js/bootstrap.bundle.min.js')"), 'jQuery and Bootstrap JS are served locally');
check(tracking.includes("$pixel->script_code"), 'Existing admin-configured dynamic GTM snippets preserved');
check(existsSync(join(root, 'public/frontend/images/no-image.svg')), 'Missing-image placeholder now exists');
check(read('public/frontend/css/buttons.css').includes(':focus-visible'), 'Button keyboard focus styling present');
check(home.includes('class="col-6 col-lg-3 col-md-4 col-sm-6 mb-4 product-grid-item"'), 'Product catalog uses 2 mobile and 4 desktop Bootstrap columns');
check(read('public/frontend/css/buttons.css').includes('prefers-reduced-motion'), 'Reduced motion styling present');
for (const name of readdirSync(join(root, 'public/frontend/js')).filter(x => x.endsWith('.js'))) {
  const result = spawnSync(process.execPath, ['--check', join(root, 'public/frontend/js', name)], { encoding: 'utf8' });
  check(result.status === 0, `Node JS syntax: ${name} ${result.stderr.trim()}`);
}
console.log(`Result: ${tested}/${tested} frontend asset checks pass (static checks only).`);
