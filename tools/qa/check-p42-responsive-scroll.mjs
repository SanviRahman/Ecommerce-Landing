/** P42 source regression checks. No Laravel/database connection or writes. */
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
const root = resolve(import.meta.dirname, '../..');
const read = relative => readFileSync(resolve(root, relative), 'utf8');
const home = read('resources/views/storefront/pages/home.blade.php');
const catalog = read('resources/views/storefront/layouts/catalog.blade.php');
const campaign = read('resources/views/frontend/layouts/master.blade.php');
const app = read('resources/views/layouts/app.blade.php');
const readiness = read('resources/views/admin/storefront/readiness.blade.php');
const admin = read('config/adminlte.php');
const topJs = read('public/frontend/js/back-to-top.js');
const topCss = read('public/frontend/css/back-to-top.css');
const heroCss = read('public/frontend/css/storefront-p42.css');
const heroJs = read('public/frontend/js/storefront-p36.js');
let n = 0;
function check(condition, label) {assert.ok(condition,label); n++; console.log('PASS',label);}
for(const [name, content] of Object.entries({homepage:home,catalog,campaign,app,readiness})) {
  check(content.includes('back-to-top.js') && content.includes('back-to-top.css'), `${name} includes accessible scroll assets`);
}
check(admin.includes("'StorefrontBackToTop'") && admin.includes("'active' => true"), 'AdminLTE plugin remains active');
check(topJs.includes("document.body.appendChild(button)") && topJs.includes('data-back-to-top'), 'button appended once, not duplicate');
check(topJs.includes('window.scrollTo') && topJs.includes('prefers-reduced-motion') && topJs.includes('scrollY > 320'), 'smooth scroll + motion preference + threshold preserved');
check(topCss.includes('body.p34-storefront .sf-back-to-top') && topCss.includes('sf-up-arrow-float'), 'existing animated arrow styling and mobile placement preserved');
check(home.includes('storefront-p42.css') && home.includes('storefront-p36.js'), 'mobile hero patch loaded after base hero style');
check(heroCss.includes('@media (max-width: 760px)') && heroCss.includes('p36-hero-with-copy'), 'P42 original mobile hero rule scoped to text slides');
check(heroCss.includes('max-height: none !important') && heroCss.includes('overflow: visible !important') && heroCss.includes('flex-direction: column-reverse !important'), 'P42 stacked fallback retained for older deployments; P43 overrides it');
check(heroCss.includes('.sf-eyebrow') && heroCss.includes('color:'), 'featured collection badge receives contrast styling');
if (home.includes('storefront-p43.css')) {
  // P43 explicitly replaces the P42 stacked mobile layout with image/text overlay.
  // Retain the P42 scroll/back-to-top assertions; update only superseded sizing checks.
  const overlayCss = read('public/frontend/css/storefront-p43.css');
  check(overlayCss.includes('p36-hero-with-copy') && overlayCss.includes('object-fit: cover !important'),
    'P43 override: text stays over the image on mobile');
  check(heroJs.includes('contentHeight + gap') && heroJs.includes('paddingBottom'),
    'P43 override: mobile hero height fits the text and slider controls');
} else {
  check(heroJs.includes('copy.scrollHeight') && heroJs.includes('image.naturalHeight / image.naturalWidth'),
    'P42: stacked hero height includes un-cropped image and copy');
}
check((heroJs.includes("attributeFilter:['aria-hidden']") || heroJs.includes("attributeFilter: ['aria-hidden']")) && heroJs.includes("'resize', sync"), 'slider height adapts on slide switch and viewport resize');
console.log(`P42: ${n} source checks PASS (not browser/MySQL validation)`);
