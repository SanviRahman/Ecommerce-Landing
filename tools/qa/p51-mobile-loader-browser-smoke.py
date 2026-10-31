#!/usr/bin/env python3
"""P51 synthetic Chromium checks: no Laravel/MySQL integration assertions.
Usage: python tools/qa/p51-mobile-loader-browser-smoke.py [root] [output_dir]
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import json
import sys

ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]
OUT = Path(sys.argv[2]).resolve() if len(sys.argv) > 2 else ROOT / 'tools/qa/results'
OUT.mkdir(parents=True, exist_ok=True)
CSS_FILES = ['storefront-preview.css','storefront-catalog.css','storefront-next.css',
             'storefront-premium.css','storefront-p34.css','storefront-p35.css',
             'storefront-p36.css','storefront-p37.css','storefront-p38.css',
             'storefront-p39.css','storefront-p41.css','storefront-p42.css',
             'storefront-p43.css','storefront-p44.css','storefront-p46.css',
             'storefront-p47.css','back-to-top.css','storefront-p50.css',
             'storefront-p51.css']
styles = '\n'.join('<style>' + (ROOT/'public/frontend/css'/f).read_text(encoding='utf8') + '</style>' for f in CSS_FILES)
# Embedded SVG is an illustration for a synthetic fixture, not real customer media.
svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="700"><defs><linearGradient id="g"><stop stop-color="#12543f"/><stop offset="1" stop-color="#76c7c1"/></linearGradient></defs><rect width="1600" height="700" fill="url(#g)"/><circle cx="1100" cy="330" r="200" fill="#a2f2e0" opacity=".55"/></svg>'
from urllib.parse import quote
hero_img = 'data:image/svg+xml,' + quote(svg)
cards = ''.join('''<article class="sf-tile sf-product p07-card" style="overflow:hidden">
  <a class="sf-product-image p07-card-link" href="#" aria-label="View Product {n}"><div class="placeholder-image" style="height:100%;display:grid;place-items:center;background:#e9f9ef"><span style="font-size:45px;color:#5bd694">▣</span></div></a>
  <div class="sf-product-copy p07-card-body"><p class="sf-product-category p07-card-category">Electronics</p><h3>Quality Product {n}</h3><p class="sf-price">৳630</p>
  <div class="sf-product-actions p34-product-actions"><a class="sf-secondary p19-cart-action" href="#" aria-label="Add to Cart">＋</a><a class="sf-cta p34-buy-button" href="#">View Product →</a></div></div></article>'''.format(n=n) for n in range(1,9))


def html(loading=False):
    return f'''<!doctype html><html lang="bn"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>NextGenAIBD — storefront QA mock</title>{styles}
<script src="/loader.js"></script>
{'<script src="/slow.js" defer></script>' if loading else ''}
</head><body class="p34-storefront">
<div class="p51-page-loader" data-p51-loader role="status" aria-live="polite" aria-label="Loading NextGenAIBD"><div class="p51-page-loader__inner"><span class="p51-page-loader__mark"><span class="p51-page-loader__initial">N</span></span><span class="p51-page-loader__brand">NextGenAIBD</span><span class="p51-page-loader__tagline">Shop smart · Live better</span><span class="p51-page-loader__track"><span></span></span><span class="p51-page-loader__status">Preparing your shopping experience…</span></div></div>
<header class="p07-header p19-header" data-sticky-header>
<div class="p19-benefit-bar"><div class="p07-container"><span>✓ Explore our collection</span><span>✓ Discover products</span><span>✓ Support when you need it</span><a href="#">Track Order</a></div></div>
<div class="p07-container p19-main-header"><a href="#" class="p07-brand p33-logo-brand"><span class="p07-brand-logo">N</span><span class="p33-brand-name">NextGenAIBD<small>Shop smart · Live better</small></span></a>
<form class="p07-search p19-search" id="p19-search-form" action="#" role="search"><input type="search" placeholder="Search products..." aria-label="Search products"><button type="submit">Search</button></form>
<div class="p19-header-actions p33-header-actions p34-header-actions"><a class="p19-account-link p33-account"><span class="p33-action-icon">♙</span></a><a class="p19-account-link p33-wishlist"><span class="p33-action-icon">♡</span></a><button class="p19-top-cart p33-cart-link" type="button"><span class="p33-action-icon">♧</span></button></div></div>
<nav class="p19-desktop-nav"><div class="p07-container p19-desktop-nav-inner">Home　 Shop　 New Arrivals　 Categories　 Help　 Contact</div></nav>
</header>
<main id="main"><section class="sf-section sf-section-hero"><div class="sf-wrapper"><div class="sf-hero-carousel" data-hero-carousel><div class="sf-hero-track" data-hero-track><div class="sf-hero-panel p36-hero-with-copy" data-hero-slide aria-hidden="false"><img class="p36-hero-banner" src="{hero_img}" alt=""><div class="sf-hero-copy"><span class="sf-eyebrow">Featured Collection</span><h1>Shop Smarter. Live Better.</h1><p>Discover our handpicked collection of products for every lifestyle. Great value, great service, and beautiful everyday essentials, all in one place.</p><a class="sf-cta" href="#">Shop Now →</a></div></div></div><div class="sf-carousel-controls" style="position:absolute;bottom:9px;left:50%;padding:8px 18px;border-radius:99px;color:white">● ● ●</div></div></div></section>
<section class="sf-section"><div class="sf-wrapper"><p class="sf-kicker">EXPLORE COLLECTIONS</p><div class="sf-title-line"><h2>Shop by Category</h2></div><p class="sf-lead">Shop our carefully selected categories and discover quality products for every need.</p><div class="p33-category-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px"><article class="p33-category-tile sf-tile"><div class="sf-square" style="height:150px;background:#eff8f0"></div><h3>Electronics</h3></article><article class="p33-category-tile sf-tile"><div class="sf-square" style="height:150px;background:#eff8f0"></div><h3>Fashion</h3></article></div></div></section>
<section class="sf-section"><div class="sf-wrapper"><p class="sf-kicker">DISCOVER SOMETHING SPECIAL EVERY DAY</p><div class="sf-title-line"><h2>Handpicked Favorites for You</h2></div><p class="sf-lead">Explore useful products for smarter, easier shopping.</p><div class="sf-grid sf-grid-products p46-featured-grid">{cards}</div><div class="p46-view-all-wrap"><a class="p46-view-all" href="#">View All Products</a></div></div></section>
<section class="p07-container"><div class="p19-account-layout"><aside class="p19-account-sidebar"><div class="p19-account-avatar">N</div><div class="p47-account-menu"><a href="#">Dashboard</a><a href="#">My Orders</a><a href="#">Profile Settings</a></div></aside><div class="p19-account-main"><h1>Dashboard</h1><div class="p19-account-stat-grid"><div><span>Total Orders</span><strong>0</strong></div><div><span>Active Orders</span><strong>0</strong></div><div><span>Completed Orders</span><strong>0</strong></div></div></div></div></section>
<section class="p07-container" style="margin-block:45px 100px"><div class="p19-checkout-form"><fieldset class="p19-step-panel"><legend>Shipping Information</legend><label for="fullname">Full Name</label><input id="fullname" type="text" placeholder="Your name"><label for="area">Delivery Area</label><select id="area"><option>Choose area</option></select><button type="button" class="p08-primary">Continue</button></fieldset></div></section>
</main>
<nav class="p07-mobile-nav p19-mobile-nav p34-mobile-nav"><a href="#"><span class="p34-bottom-icon">⌂</span><span class="p34-mobile-text">Home</span></a><button><span class="p34-bottom-icon">▦</span><span class="p34-mobile-text">Menu</span></button><a href="#"><span class="p34-bottom-icon">♡</span><span class="p34-mobile-text">Wishlist</span></a><button class="p34-bottom-cart"><span class="p34-bottom-icon">◉</span><span class="p34-mobile-text">Cart</span></button><button><span class="p34-bottom-icon">⌕</span><span class="p34-mobile-text">Search</span></button><a href="#"><span class="p34-bottom-icon">♙</span><span class="p34-mobile-text">Account</span></a></nav>
<script src="/hero.js" defer></script>
</body></html>'''


# A pure in-memory document avoids network access and can be exercised offline.
bootstrap = (ROOT/'public/frontend/js/storefront-p51-loader.js').read_text(encoding='utf8')
hero_js = (ROOT/'public/frontend/js/storefront-p36.js').read_text(encoding='utf8')
fixture = html().replace('<script src="/loader.js"></script>',
    '<script>window.__p51SawLoaderAtDomReady=false;document.addEventListener("DOMContentLoaded",()=>{window.__p51SawLoaderAtDomReady=document.documentElement.classList.contains("p51-booting");});</script><script>' + bootstrap + '</script>')
fixture = fixture.replace('<script src="/hero.js" defer></script>', '<script>' + hero_js + '</script>')
results = []
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width':390,'height':844})
    page.set_content(fixture, wait_until='domcontentloaded')
    assert page.evaluate('window.__p51SawLoaderAtDomReady'), 'Loader not armed during document parsing'
    page.locator('[data-p51-loader]').wait_for(state='hidden', timeout=3000)
    assert 'p51-ready' in page.locator('html').get_attribute('class')
    # Capture its on-screen appearance from the same CSS and markup.
    page.evaluate("document.documentElement.classList.add('p51-booting')")
    page.wait_for_timeout(300)
    assert page.locator('[data-p51-loader]').is_visible()
    page.screenshot(path=str(OUT/'P51-LOADER-390-SYNTHETIC.png'))
    page.evaluate("document.documentElement.classList.remove('p51-booting')")
    page.locator('[data-p51-loader]').wait_for(state='hidden', timeout=1500)
    print('PASS loader during DOM parsing and exits; simulated loader screenshot')
    page.close()

    nojs = browser.new_context(viewport={'width':390,'height':844}, java_script_enabled=False)
    page = nojs.new_page()
    page.set_content(fixture, wait_until='domcontentloaded')
    assert not page.locator('[data-p51-loader]').is_visible(), 'Loader stuck when JavaScript disabled'
    assert page.locator('#main').is_visible()
    print('PASS no-JavaScript fallback')
    nojs.close()

    for width, height in [(320,760),(360,800),(390,844),(430,900),(560,900),(760,900),(761,900),(1024,768),(1366,900)]:
        context = browser.new_context(viewport={'width':width,'height':height}, reduced_motion='reduce')
        page = context.new_page()
        errs=[]
        page.on('pageerror', lambda err: errs.append(str(err)))
        page.set_content(fixture, wait_until='domcontentloaded')
        page.locator('[data-p51-loader]').wait_for(state='hidden', timeout=3000)
        page.wait_for_timeout(130)
        metrics=page.evaluate('''() => {
          const css=s=>getComputedStyle(document.querySelector(s));
          const grid=document.querySelector('.p46-featured-grid');
          const children=[...grid.children];
          const y=children[0].getBoundingClientRect().top;
          const cols=children.filter(e=>Math.abs(e.getBoundingClientRect().top-y)<3).length;
          const search=document.querySelector('.p19-main-header > .p19-search');
          const title=document.querySelector('.sf-product-copy h3');
          const action=document.querySelector('.p34-buy-button');
          const hero=document.querySelector('.sf-hero-carousel');
          const heroCopy=document.querySelector('.sf-hero-copy');
          return {scrollWidth:document.documentElement.scrollWidth,
            width:innerWidth,cols,searchVisible:search.getClientRects().length>0,
            searchHeight:search.getBoundingClientRect().height,
            titleFont:parseFloat(css('.sf-product-copy h3').fontSize),
            actionHeight:action.getBoundingClientRect().height,
            heroHeight:hero.getBoundingClientRect().height,
            heroCopyHeight:heroCopy.getBoundingClientRect().height,
            headerHeight:document.querySelector('.p19-header').getBoundingClientRect().height,
            accountMenuHeight:document.querySelector('.p47-account-menu a').getBoundingClientRect().height,
            checkoutInputHeight:document.querySelector('.p19-checkout-form input').getBoundingClientRect().height,
            loaderVisible:css('.p51-page-loader').visibility==='visible'};
        }''')
        assert not errs, (width, 'JavaScript page errors', errs)
        assert metrics['scrollWidth']<=width+1, (width,'horizontal overflow',metrics)
        assert metrics['cols']==(2 if width<=900 else 4), (width,'wrong product grid',metrics)
        assert metrics['searchVisible'], (width,'search not available',metrics)
        assert not metrics['loaderVisible'], (width,'loader did not hide',metrics)
        if width<=760:
            assert metrics['titleFont']>=14, (width,'product title not readable',metrics)
            assert metrics['actionHeight']>=44, (width,'tap target too small',metrics)
            assert metrics['searchHeight']>=46, (width,'search too small',metrics)
            assert metrics['accountMenuHeight']>=44, (width,'account menu too small',metrics)
            assert metrics['checkoutInputHeight']>=48, (width,'checkout input too small',metrics)
            assert metrics['heroHeight']>=metrics['heroCopyHeight']-1, (width,'hero text cropped',metrics)
        if width==390:
            page.screenshot(path=str(OUT/'P51-MOBILE-390-SYNTHETIC.png'),full_page=False)
        if width==1366:
            page.screenshot(path=str(OUT/'P51-DESKTOP-1366-SYNTHETIC.png'),full_page=False)
        results.append(metrics)
        print('PASS responsive width',width,'columns',metrics['cols'],'header',round(metrics['headerHeight']))
        context.close()
    browser.close()
(OUT/'P51-BROWSER-RESULTS.json').write_text(json.dumps(results,indent=2), encoding='utf8')
print('P51 synthetic responsive + loader checks: PASS')
