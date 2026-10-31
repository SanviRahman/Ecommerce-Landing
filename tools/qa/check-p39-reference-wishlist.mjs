/** P39 source contracts. No app boot, MySQL, data writes or secrets. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const nav = read('resources/views/storefront/partials/navigation.blade.php');
const contactCSS = read('public/frontend/css/storefront-p39.css');
const reviewJS = read('public/frontend/js/storefront-p39.js');
const reviewBlade = read('resources/views/storefront/partials/home-reviews.blade.php');
const home = read('resources/views/storefront/pages/home.blade.php');
const accountState = read('app/Support/StorefrontWishlistState.php');
const wishController = read('app/Http/Controllers/Frontend/StorefrontWishlistController.php');
const cards = [read('resources/views/storefront/partials/product-card.blade.php'),read('resources/views/storefront/partials/home-product-card.blade.php')];
const product = read('resources/views/storefront/pages/product.blade.php');
const checks = [
  ['Contact text fixed against legacy transform', contactCSS.includes('.p38-contact-menu[open]>summary .p38-contact-label') && contactCSS.includes('transform:none!important')],
  ['Contact plus rotates independently', contactCSS.includes('.p38-contact-menu[open]>summary .p38-contact-plus') && contactCSS.includes('rotate:45deg')],
  ['Contact popup motion supports reduced-motion', contactCSS.includes('@keyframes p39-contact-pop') && contactCSS.includes('@media(prefers-reduced-motion:reduce)')],
  ['Contact methods still config-derived', read('resources/views/storefront/partials/contact-widget.blade.php').includes('StorefrontContactLinks::class')],
  ['Active category contrast includes white-on-forest', contactCSS.includes('.p19-parent-category[open] > summary') && contactCSS.includes('color:#fff!important')],
  ['Category links identify current slug', nav.includes('aria-current="page"') && nav.includes("request()->route('slug')")],
  ['Category icons are safe fixed SVGs', nav.includes('$categoryIcons') && read('resources/views/storefront/partials/icon.blade.php').includes("@case('leaf')")],
  ['Review track has carousel metadata', reviewBlade.includes('data-p39-review-carousel') && reviewBlade.includes('aria-roledescription="carousel"')],
  ['Review dots are actual controls', reviewBlade.includes('data-review-dots') && reviewJS.includes('dot.addEventListener(\'click\'' )],
  ['Review autoplay advances sequentially', reviewJS.includes('window.setInterval') && reviewJS.includes('goto(index + 1)')],
  ['Review autoplay pauses appropriately', ['prefers-reduced-motion','visibilitychange','mouseenter','focusin','touchstart'].every((t) => reviewJS.includes(t))],
  ['Review dots mark current position', reviewJS.includes("dot.setAttribute('aria-current'")],
  ['Reviews do not duplicate old handlers', read('public/frontend/js/storefront-next.js').includes("hasAttribute('data-p39-review-carousel')")],
  ['Reference composition leaves backend review read path', home.includes('p39-review-intro') && read('resources/views/storefront/partials/home-reviews.blade.php').includes("$section['items']")],
  ['Discover image has compact border and proper crop', contactCSS.includes('.sf-image-text>img') && contactCSS.includes('object-fit:cover')],
  ['Premium type scales without touching admin CSS', contactCSS.includes('.p34-storefront .sf-section h2') && contactCSS.includes('font-size:clamp')],
  ['Customer guard is independent of admin', accountState.includes("Auth::guard('storefront')") && !accountState.includes("Auth::guard('web')")],
  ['Customer active state enforced', accountState.includes('$customer->is_active')],
  ['Anonymous wishlist cannot persist', accountState.includes('if ($customer === null)') && accountState.includes('return [];')],
  ['Wishlist session is per customer, not shared anonymous', accountState.includes('storefront_wishlist_account_') && accountState.includes('$customer->getKey()') && !accountState.includes("'storefront_wishlist_ids'")],
  ['Controller rejects guest before changes', wishController.includes('if (! $wishlist->authenticated())') && wishController.includes('requireCustomerLogin()')],
  ['Controller uses owner-safe state', wishController.includes('$wishlist->ids($request)') && wishController.includes('$wishlist->put($request')],
  ['Product and homepage heart clicks are auth-aware', cards.every(s => s.includes('@if($wishSignedIn)') && s.includes('p39-guest-heart') && s.includes("route('storefront.account.login')"))],
  ['Product detail guest heart links to login', product.includes('p39-login-wishlist') && product.includes("route('storefront.account.login')")],
  ['Nav wishlist count belongs to storefront customer', nav.includes('$wishlistState->count()') && nav.includes('$wishlistHref')],
  ['Login explains authentication requirement', read('resources/views/storefront/account/login.blade.php').includes('p39-wishlist-signin-note')],
  ['CSS loaded by homepage and catalog', home.includes('storefront-p39.css') && read('resources/views/storefront/layouts/catalog.blade.php').includes('storefront-p39.css')],
  ['JS loaded by homepage and catalog', home.includes('storefront-p39.js') && read('resources/views/storefront/layouts/catalog.blade.php').includes('storefront-p39.js')],
  ['No new migration was introduced', !fs.readdirSync(path.join(root, 'database/migrations')).some(f => /p39|wishlist/i.test(f))],
];
for (const [label, pass] of checks) console.log(`${pass ? 'PASS' : 'FAIL'} ${label}`);
const fail=checks.filter(([,passed])=>!passed).length;
console.log(`RESULT: ${checks.length-fail}/${checks.length} P39 source checks ${fail?'FAILED':'PASS'} (not live browser/MySQL)`);
if (fail) process.exitCode=1;
