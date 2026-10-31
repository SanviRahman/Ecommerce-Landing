/** P34 static storefront contract QA: no DB, network, migrations, or .env access. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const source = file => fs.readFileSync(path.join(root, file), 'utf8');
const nav = source('resources/views/storefront/partials/navigation.blade.php');
const card = source('resources/views/storefront/partials/product-card.blade.php');
const homeCard = source('resources/views/storefront/partials/home-product-card.blade.php');
const icon = source('resources/views/storefront/partials/icon.blade.php');
const css = source('public/frontend/css/storefront-p34.css');
const js = source('public/frontend/js/storefront-premium.js');
const next = source('public/frontend/js/storefront-next.js');
const home = source('resources/views/storefront/pages/home.blade.php');
const layout = source('resources/views/storefront/layouts/catalog.blade.php');
const product = source('resources/views/storefront/pages/product.blade.php');
const checks = [
 ['Only storefront layouts opt in to the new theme', () => [home,layout].every(s => s.includes('<body class="p34-storefront">') && s.includes("asset('frontend/css/storefront-p34.css')"))],
 ['New stylesheet is scoped to storefront body', () => css.includes('.p34-storefront{') && !css.includes('html,body{overflow-x:hidden}')],
 ['Dark forest variables override P33 blue', () => css.includes('--p33-blue:#126342') && css.includes('--p33-navy:#0b3729')],
 ['Search, categories, footer and hero accents green', () => css.includes('.p19-search button{') && css.includes('.p19-mega-menu>summary{') && css.includes('.p19-newsletter{') && css.includes('.sf-hero-copy{')],
 ['Native SVG icons are fixed/allowlisted, no extra dependency', () => icon.includes('@switch($name)') && icon.includes("@case('cart-plus')") && icon.includes("@case('heart')") && icon.includes('aria-hidden="true"')],
 ['Responsive mobile top header shows actions', () => css.includes('.p33-header-actions{display:flex!important') && nav.includes('p34-header-actions')],
 ['Mobile bottom navigation contains real Cart, Wishlist, Account', () => nav.includes('p34-mobile-nav') && nav.includes('p34-bottom-cart') && nav.includes("route('storefront.wishlist')") && nav.includes("route('storefront.account.index')")],
 ['Mobile menu keeps working open controls', () => nav.includes('data-menu-open') && nav.includes('data-search-open') && next.includes("$$('[data-menu-open]')")],
 ['Wishlist not duplicated in desktop category row', () => !nav.slice(nav.indexOf('<nav class="p19-desktop-nav"'),nav.indexOf('</header>')).includes("route('storefront.wishlist')")],
 ['Server count only, no made-up Cart total', () => nav.includes('data-cart-summary-url') && nav.includes('data-cart-count') && nav.includes('data-wishlist-count') && next.includes('await fetch(cartEndpoint')],
 ['Desktop floating cart preserved, mobile overlap eliminated', () => nav.includes('class="p19-floating-cart"') && css.includes('.p19-floating-cart{display:none!important')],
 ['Both product card templates use accessible cart-plus icon', () => [card,homeCard].every(s => s.includes("'name'=>'cart-plus'") && s.includes('aria-label="Add {{ $product->name }} to cart"'))],
 ['Buy Now submits actual checkout intent for eligible SKU', () => [card,homeCard].every(s => s.includes('name="intent" value="checkout"') && s.includes('Buy Now') && s.includes("'is_default'" ) === false)],
 ['Unready cart/checkout are never bypassed by CSS', () => [card,homeCard].every(s => s.includes('$canCheckout') && s.includes('$canAdd') && s.includes('config(\'storefront.p08_checkout_test_enabled\''))],
 ['Purchase options still link to product picker', () => [card,homeCard].every(s => s.includes('Choose Options') && s.includes("route('storefront.product'"))],
 ['Product page keeps CSRF and trusted submission', () => product.includes('@csrf') && product.includes("route('storefront.cart.add')") && product.includes('p08-product-order-form')],
 ['Wishlist save/remove retains POST and CSRF', () => [card,homeCard].every(s => s.includes("route('storefront.wishlist.toggle')") && s.includes('@csrf'))],
 ['Mobile carousel hides scrollbars but retains touch/scroll', () => css.includes('scrollbar-width:none') && css.includes('::-webkit-scrollbar{display:none') && css.includes('-webkit-overflow-scrolling:touch')],
 ['Product shelves keep 4 desktop / 2 mobile columns', () => source('public/frontend/css/storefront-premium.css').includes('grid-auto-columns:calc((100% - 72px)/4)') && css.includes('grid-auto-columns:calc((100% - 14px)/2)')],
 ['Published admin category shelves scroll too; preview stays static', () => home.includes('sf-dynamic-category {{ ($isPreview ?? false)') && home.includes('@unless($isPreview ?? false) data-product-carousel @endunless') && js.includes("track.closest('.p33-auto-section, .sf-section')")],
 ['Carousel auto-advances right to left only in viewport', () => js.includes('window.setInterval') && js.includes('scroll(1)') && js.includes('isVisible') && js.includes('document.hidden')],
 ['Auto-scroll pauses for user and reduced motion', () => js.includes('if (reduced) return') && js.includes('hovered || focused || pointerDown') && js.includes('touchstart')],
 ['Manual carousel arrows preserved', () => home.includes('data-carousel-prev') && home.includes('data-carousel-next') && js.includes("[data-carousel-prev]")],
 ['Desktop mega menu closes on outside click and Escape', () => nav.includes('data-categories-menu') && js.includes("event.key === 'Escape'") && js.includes('!categoriesMenu.contains(event.target)')],
 ['Reduced motion CSS has no animated interactions', () => css.includes('@media(prefers-reduced-motion:reduce)')],
];
let passed = 0;
for (const [label, run] of checks) {
 let result = false;
 try {result = run();} catch (error) {console.error('ERROR',label,error.message);}
 if (!result) {console.error('FAIL',label);process.exitCode=1;}
 else {console.log('PASS',label);passed++;}
}
console.log(`RESULT: ${passed}/${checks.length} P34 responsive/static contracts PASS (no Laravel/DB/browser)`);
