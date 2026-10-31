"""P51 offline source contracts; no Laravel or MySQL required.
Run: python tests/offline/test_p51_mobile_loader.py [project_root]
"""
from pathlib import Path
import sys

ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]


def source(path):
    return (ROOT / path).read_text(encoding='utf-8')


loader_view = 'resources/views/storefront/partials/page-loader.blade.php'
loader_css = 'public/frontend/css/storefront-p51.css'
loader_js = 'public/frontend/js/storefront-p51-loader.js'
home = source('resources/views/storefront/pages/home.blade.php')
catalog = source('resources/views/storefront/layouts/catalog.blade.php')
for view in (home, catalog):
    assert "@include('storefront.partials.page-loader')" in view, 'Missing global storefront loader markup'
    assert "asset('frontend/css/storefront-p51.css')" in view, 'Missing P51 scoped stylesheet'
    assert "asset('frontend/js/storefront-p51-loader.js')" in view, 'Missing early loader bootstrap'
print('PASS loader integrated into separate home and shared catalog entrypoints')

loader_markup = source(loader_view)
assert r'\App\Support\StorefrontBranding::class' in loader_markup
assert '$loaderBranding->name()' in loader_markup and '$loaderBranding->logo()' in loader_markup
assert '{{ $loaderBrandName }}' in loader_markup
assert 'data-p51-loader' in loader_markup and 'role="status"' in loader_markup
print('PASS dynamic Site Settings logo/name and accessible loader state')

js = source(loader_js)
assert "classList.add('p51-booting')" in js
assert "classList.remove('p51-booting')" in js
assert 'DOMContentLoaded' in js and 'pageshow' in js
assert 'window.setTimeout' in js
print('PASS early loader boot, timely finish and back/forward cache exit')

css = source(loader_css)
assert 'html.p51-booting body.p34-storefront .p51-page-loader' in css
assert 'visibility: hidden' in css and 'pointer-events: none' in css
assert '@media (max-width: 760px)' in css and '@media (prefers-reduced-motion: reduce)' in css
assert 'repeat(2, minmax(0, 1fr))' in css
assert '.p19-main-header' in css and '.p19-checkout-form' in css and '.p19-account-layout' in css
assert 'body.p34-storefront' in css
print('PASS mobile scaling, 2-column product/category grids, reduced motion and storefront-only scope')

assert 'p50-checkout-intro' in source('resources/views/storefront/pages/checkout.blade.php')
assert 'show(1, {scroll: false, focus: false})' in source('public/frontend/js/storefront-next.js')
assert "route('storefront.shop')" in source('resources/views/storefront/partials/navigation.blade.php')
print('PASS checkout wizard, navigation and order handling left in place')
print('P51 source contracts: 5/5 PASS')
