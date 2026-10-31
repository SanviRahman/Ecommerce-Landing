"""P43 isolated Chromium smoke: real project CSS/JS, no Laravel or database writes."""
from pathlib import Path
from urllib.parse import quote
from playwright.sync_api import sync_playwright
import json

root = Path(__file__).resolve().parents[2]
css_files = [
    'storefront-preview.css','storefront-catalog.css','storefront-next.css',
    'storefront-premium.css','storefront-p34.css','storefront-p35.css',
    'storefront-p36.css','storefront-p37.css','storefront-p38.css',
    'storefront-p39.css','storefront-p41.css','storefront-p42.css',
    'storefront-p43.css', 'back-to-top.css',
]
css = '\n'.join(f'<style>\n{(root / "public/frontend/css" / file).read_text()}\n</style>' for file in css_files)
# A representative wide banner image embedded locally (no external assets).
svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 700"><defs><linearGradient id="g" x1="0" x2="1"><stop stop-color="#397b8e"/><stop offset="1" stop-color="#7adce5"/></linearGradient></defs><rect width="1600" height="700" fill="url(#g)"/><circle r="240" cx="1200" cy="200" fill="#b4f2ec"/><circle r="150" cx="1160" cy="570" fill="#56b0be"/></svg>'
img = 'data:image/svg+xml,'+quote(svg)
text_a = 'Discover the best of fashion, electronics, beauty, and everyday essentials at NextGenAIBD. Explore handpicked products, amazing value, and a smarter shopping experience—all in one place.'
text_b = 'Discover smart gadgets, stylish essentials and everyday favorites at NextGenAIBD. Explore handpicked products, exceptional value, and an easy shopping experience designed around your lifestyle. Browse our carefully curated selection.'
slides = []
for title, desc in [('Shop Smarter. Live Better.', text_a), ('Discover More. Shop Better.', text_b)]:
    slides.append(f'''<div class="sf-hero-panel p36-hero-with-copy" data-hero-slide role="group">
    <div class="sf-hero-copy"><span class="sf-eyebrow">Featured Collection</span><h1>{title}</h1><p>{desc}</p><a class="sf-cta" href="#details">Shop Now →</a>
    <div class="p39-hero-benefits"><span>Delivery options</span><span>Customer support</span></div></div>
    <img class="p36-hero-banner" src="{img}" alt="Banner"></div>''')
slides.append(f'<div class="sf-hero-panel p36-hero-image-only" data-hero-slide><img class="p36-hero-banner" src="{img}" alt="Just an image"></div>')
html = f'''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">{css}</head>
<body class="p34-storefront" style="margin:0"><section class="sf-section sf-section-hero"><div class="sf-wrapper">
<div class="sf-hero-carousel" data-hero-carousel><div class="sf-hero-track" data-hero-track>{''.join(slides)}</div>
<div class="sf-carousel-controls" data-hero-controls hidden><button data-hero-prev>‹</button><div class="sf-carousel-dots" data-hero-dots></div><button data-hero-next>›</button></div></div></div></section>
<button class="p35-mobile-cart-peek" style="display:none" aria-label="Cart">🛒</button>
<div id="details" style="height:1700px;background:white">Next section</div></body></html>'''

analyze = '''() => {
    const rect = element => {
      const r=element.getBoundingClientRect();
      return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,height:r.height,width:r.width};
    };
    const active=document.querySelector('[data-hero-slide][aria-hidden="false"]');
    const copy=active?.querySelector('.sf-hero-copy');
    const image=active?.querySelector('.p36-hero-banner');
    const elements=copy ? Object.fromEntries([...copy.children].filter(e=>getComputedStyle(e).display!=='none').map(e=>[e.tagName.toLowerCase()==='span'?'label':e.tagName.toLowerCase(),rect(e)])) : {};
    const controls=document.querySelector('.sf-carousel-controls');
    return {width:innerWidth, carousel:rect(document.querySelector('.sf-hero-carousel')),
      active:active?.className, panel:rect(active), image:rect(image),
      copy: copy?rect(copy):null, copyPos:copy?getComputedStyle(copy).position:'none',
      imagePos:getComputedStyle(image).position, items:elements,
      controls:rect(controls), scrollWidth:document.documentElement.scrollWidth,
      cart:rect(document.querySelector('.p35-mobile-cart-peek'))};
}'''

with sync_playwright() as pw:
    browser = pw.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
    for width, height in [(320,710),(360,740),(390,844),(430,900),(560,840),(760,900),(761,900),(1024,850),(1366,900)]:
        page=browser.new_page(viewport={'width':width,'height':height}, device_scale_factor=1, reduced_motion='reduce')
        page.set_content(html, wait_until='load')
        for name in ['storefront-next.js','storefront-p36.js','back-to-top.js']:
            page.add_script_tag(content=(root / 'public/frontend/js' / name).read_text())
        page.wait_for_timeout(140)
        r=page.evaluate(analyze)
        print('VIEW',width,'height',round(r['carousel']['height']), 'imgPos', r['imagePos'], 'copyPos',r['copyPos'])
        assert r['scrollWidth'] <= width+1, f'horizontal overflow at {width}'
        if width<=760:
            assert r['imagePos']=='absolute' and r['copyPos']=='absolute',f'image/text not overlay at {width}'
            assert abs(r['image']['top']-r['panel']['top'])<=2, 'image top not aligned'
            assert abs(r['image']['bottom']-r['panel']['bottom'])<=2, 'image not full-height behind overlay'
            assert r['items']['label']['top'] >= r['panel']['top']+1, 'Featured Collection clipped'
            for key in ['h1','p','a']:
                el = r['items'][key]
                assert el['top'] >= r['panel']['top']-1 and el['bottom'] <= r['panel']['bottom']+1, f'{key} clipped at {width}: {el}, {r["panel"]}'
                assert el['bottom'] <= r['controls']['top']-5, f'{key} overlaps controls at {width}: {el}, {r["controls"]}'
            assert r['items']['p']['right'] < width - 62, f'description overlaps right floating cart at {width}'
            # Check the longer second slide, and image-only third slide.
            page.locator('[data-hero-next]').click()
            page.wait_for_timeout(80)
            r2=page.evaluate(analyze)
            for key in ['label','h1','p','a']:
                if key not in r2['items']: continue
                assert r2['items'][key]['top'] >= r2['panel']['top']-1 and r2['items'][key]['bottom'] <= r2['controls']['top']-5, f'second {key} clipped at {width}'
            page.locator('[data-hero-next]').click()
            page.wait_for_timeout(80)
            r3=page.evaluate(analyze)
            assert r3['copy'] is None and r3['carousel']['height']>100, f'image only blank at {width}'
            # Resize back to the text slide and verify height recalculation.
            page.locator('[data-hero-prev]').click()
            page.wait_for_timeout(80)
            if width==320:
                page.screenshot(path=str(root.parent/'p43-mobile-320.png'),full_page=False)
            if width==390:
                page.screenshot(path=str(root.parent/'p43-mobile-390.png'),full_page=False)
        else:
            assert r['imagePos']=='absolute' and r['copyPos']=='absolute',f'desktop hero changed at {width}'
            if width==1366:
                page.screenshot(path=str(root.parent/'p43-desktop-1366.png'), full_page=False)
        page.close()
    # Stress case: long admin-managed hero copy on narrow 320 px screens.
    stress = browser.new_page(viewport={'width':320,'height':700}, device_scale_factor=1, reduced_motion='reduce')
    stress.set_content(html.replace(text_a, text_a * 3), wait_until='load')
    for name in ['storefront-next.js','storefront-p36.js']:
        stress.add_script_tag(content=(root / 'public/frontend/js' / name).read_text())
    stress.wait_for_timeout(140)
    extended = stress.evaluate(analyze)
    print('STRESS 320px very long copy: height', round(extended['carousel']['height']))
    assert extended['items']['label']['top'] >= extended['panel']['top'] - 1
    assert extended['items']['a']['bottom'] <= extended['controls']['top'] - 5, 'long text CTA clipped'
    assert extended['image']['height'] == extended['panel']['height'], 'long text image still covers full panel'
    stress.close()
    browser.close()
print('P43 Chromium browser smoke PASS: 9 viewports, 3 slides, stress-copy, image/text overlay, CTA/control visibility')
