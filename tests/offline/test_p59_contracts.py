from pathlib import Path
import re
import sys

root=Path(sys.argv[1]) if len(sys.argv)>1 else Path(__file__).resolve().parents[2]
read=lambda p:(root/p).read_text(encoding='utf-8')
nav=read('resources/views/storefront/partials/navigation.blade.php')
css=read('public/frontend/css/storefront-p54.css')
gate=read('app/Support/P33CatalogAdminAccess.php')
activation=read('app/Services/Catalog/VariableStorefrontActivation.php')
manager=read('app/Services/Catalog/VariantCatalogManager.php')
layout=read('resources/views/storefront/layouts/catalog.blade.php')
register=read('resources/views/storefront/account/register.blade.php')
login=read('resources/views/storefront/account/login.blade.php')
profile=read('resources/views/storefront/account/index.blade.php')
variants=read('resources/views/admin/products/variants.blade.php')
product_controller=read('app/Http/Controllers/Admin/ProductController.php')
product_show=read('resources/views/admin/products/show.blade.php')
checks={
'list and product detail share variant gate': 'variantAdminAvailable()' in product_controller and "'variantAdminAvailable' => $this->variantAdminAvailable()" in product_controller and '@if($variantAdminAvailable ?? false)' in product_show,
'production variant gate verifies actual DB': "P58ProductionStorefrontAccess::class" in gate and 'assertApprovedDatabase(request(), false)' in gate,
'admin role remains mandatory': "isAdmin()" in gate and 'is_active' in gate,
'production legacy inventory protection': "config('app.env') === 'production'" in manager and 'order_items' in manager,
'activation rechecks production readiness': "P58ProductionStorefrontAccess::class" in activation and 'checkoutReady()' in activation,
'variant page names production activation': 'Validate &amp; Activate for Storefront' in variants,
'header mobile menu exists': 'class="p59-header-menu" data-menu-open' in nav,
'bottom wishlist removed': 'class="p33-mobile-wishlist"' not in nav.split('<nav class="p07-mobile-nav')[1],
'header wishlist retained': 'class="p19-account-link p32-nav-wishlist p33-wishlist"' in nav,
'mobile search remains available from bottom': 'data-search-open' in nav,
'mobile main-header search hidden': re.search(r'\.p19-main-header\s*>\s*\.p19-search\s*\{\s*display:\s*none\s*!important', css) is not None,
'mobile menu visible css': 'body.p34-storefront .p59-header-menu' in css,
'topbar nowrap style': '.p19-benefit-bar .p07-container' in css and 'white-space:nowrap' in css,
'compact storefront sections': '.sf-section:not(.sf-section-hero)' in css and '.p33-auto-section' in css,
'css cache busted': "storefront-p54.css') }}?v=p59" in layout,
'login all fields placeholder': all(re.search(r'<input(?=[^>]*id="'+f+r'")(?=[^>]*placeholder=)[^>]*>',login) for f in ('p19-login-email','p19-login-password')),
'register all fields placeholder': all(re.search(r'<input(?=[^>]*id="'+f+r'")(?=[^>]*placeholder=)[^>]*>',register) for f in ('p19-signup-name','p19-signup-email','p19-signup-password','p19-signup-confirm')),
'profile name placeholder': 'id="p19-profile-name" name="name" placeholder=' in profile,
}
for name,ok in checks.items(): print(('PASS' if ok else 'FAIL'),name)
print(f'TOTAL {sum(checks.values())}/{len(checks)}')
sys.exit(0 if all(checks.values()) else 1)
