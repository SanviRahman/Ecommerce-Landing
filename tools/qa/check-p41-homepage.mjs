// P41 source contract checks. These are NOT HTTP, database or browser tests.
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
const src = (path) => existsSync(path) ? readFileSync(path, 'utf8') : '';
const controller = src('app/Http/Controllers/Frontend/StorefrontCatalogController.php');
const view = src('resources/views/storefront/pages/home.blade.php');
const reviews = src('resources/views/storefront/partials/home-reviews.blade.php');
const editor = src('resources/views/admin/storefront/homepage.blade.php');
const admin = src('app/Http/Controllers/Admin/HomepageBuilderController.php');
const js = src('public/frontend/js/home-builder-sort.js');
const theme = src('public/frontend/css/storefront-p41.css');
const catalogLayout = src('resources/views/storefront/layouts/catalog.blade.php');
const checks = [
  ['no unconfigured category shelves in controller', () => assert.doesNotMatch(controller, /\$autoShelves|\$autoCategories/)],
  ['no out-of-band homepage content', () => assert.doesNotMatch(view, /\$autoShelves|\$autoCategories|p33-stats|p33-track-section|p33-trust-strip|p37-auto-reviews|p33-auto-section/)],
  ['reviews are not deferred out of revision order', () => assert.doesNotMatch(view, /\$publicReviewsRendered|@continue\(!\(\$isPreview/)],
  ['reviews use pinned active IDs in both modes', () => assert.doesNotMatch(reviews, /\$publicReviews\s*\?\?/)],
  ['editor advertises live reorder and safe fallback', () => assert.match(editor, /publish_after_sort/)],
  ['admin live reorder uses transactional publication', () => assert.match(admin, /publishAfterSort|publish_after_sort/)],
  ['drag requests live publication', () => assert.match(js, /publish_after_sort/)],
  ['preview + public use same sections array', () => assert.match(view, /@forelse\(\$revision\['sections'\] as \$section\)/)],
  ['lower fixed floating cart', () => assert.match(theme, /p19-floating-cart.*top:\s*5[5-9]%/s)],
  ['lower fixed contact dock', () => assert.match(theme, /p38-contact-dock.*top:\s*calc\(5[5-9]%/s)],
  ['p41 style on home + catalog', () => { assert.match(view, /storefront-p41\.css/); assert.match(catalogLayout, /storefront-p41\.css/); }],
];
let fails = 0;
for (const [name, check] of checks) {
  try { check(); console.log(`PASS ${name}`); }
  catch { fails += 1; console.error(`FAIL ${name}`); }
}
console.log(`P41: ${checks.length - fails}/${checks.length} source checks passed`);
if (fails) process.exitCode = 1;
