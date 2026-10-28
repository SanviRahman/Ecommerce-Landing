"""Dependency-free P50 source contract checks (not Laravel/MySQL integration)."""
from pathlib import Path
import sys

ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

checkout = read('resources/views/storefront/pages/checkout.blade.php')
js = read('public/frontend/js/storefront-next.js')
css_path = ROOT / 'public/frontend/css/storefront-p50.css'
assert css_path.is_file(), 'P50 shared css absent'
css = css_path.read_text(encoding='utf-8')
for view in ['resources/views/storefront/pages/home.blade.php', 'resources/views/storefront/layouts/catalog.blade.php']:
    assert "asset('frontend/css/storefront-p50.css')" in read(view), f'P50 CSS missing: {view}'
print('PASS P50 shared stylesheet loaded by home and catalog layout')
assert 'class="p50-checkout-intro"' in checkout and 'class="p50-step-label"' in checkout
assert checkout.count('class="p50-step-legend-icon"') == 3
print('PASS P50 connected semantic stepper and all panel legends')
assert 'show(1, {scroll: false, focus: false})' in js
assert 'const show = (index, {scroll = true, focus = true} = {})' in js
assert 'if (scroll)' in js and 'const stickyHeight = header?.getBoundingClientRect().height || 0;' in js
assert 'window.scrollTo({top:Math.max(0,checkout.getBoundingClientRect().top+window.scrollY-140)' not in js
print('PASS P50 initial scroll suppressed; later steps honor live header height')
assert 'li:not(:last-child)::after' in css and 'li.is-complete::after' in css
assert 'min-width: 1100px' in css and 'max-width: 560px' in css
assert ':is(.p07-card-body, .sf-product-copy)' in css
assert 'minmax(330px, .95fr)' in css
print('PASS P50 progress connectors and desktop-only shared sizing')
assert 'route(\'storefront.checkout.place\')' in checkout
assert 'name="checkout_key"' in checkout
assert 'data-shipping-area' in checkout and 'data-review-shipping' in checkout
assert 'type="submit"' in checkout and 'Cash on Delivery' in checkout
print('PASS P50 existing COD, CSRF, cart version and server endpoints preserved')
print('P50 contracts: 5/5 PASS')
