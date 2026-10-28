"""P44 standalone Chromium smoke with the actual versioned CSS and contact JS.
Pure synthetic fixture. No Laravel boot, database access, or external requests.
"""
from pathlib import Path
from urllib.parse import quote
from playwright.sync_api import sync_playwright
import json

root = Path(__file__).resolve().parents[2]
styles = ['storefront-preview.css', 'storefront-catalog.css', 'storefront-next.css',
          'storefront-premium.css', 'storefront-contact.css', 'storefront-p34.css',
          'storefront-p35.css', 'storefront-p36.css', 'storefront-p37.css',
          'storefront-p38.css', 'storefront-p39.css', 'storefront-p41.css',
          'storefront-p42.css', 'storefront-p43.css', 'storefront-p44.css']
style_html = '\n'.join('<style>\n'+(root/'public/frontend/css'/file).read_text()+'\n</style>' for file in styles)
photo_svg = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 800"><defs>
<linearGradient id="g" x1="0" x2="1"><stop stop-color="#12609a"/><stop offset="1" stop-color="#0733b0"/></linearGradient></defs>
<rect width="1600" height="800" fill="url(#g)"/>
<g stroke="#4aaaff" fill="none" stroke-width="4" opacity=".55"><path d="M1150,-50 Q850,200 1120,400 T1370,880"/>
<path d="M1340,-50 Q990,160 1240,390 T1520,860"/><path d="M1490,-50 Q1110,160 1360,400 T1670,860"/></g></svg>'''
photo = 'data:image/svg+xml,' + quote(photo_svg)

section_copy = ('At NextGenAIBD, we believe shopping should be simple, enjoyable, and convenient. '
                'Discover a carefully selected range of quality products, from fashion and electronics '
                'to beauty and everyday essentials. Our mission is to make your shopping experience smarter, '
                'easier, and more rewarding.')
item_copy = ("Explore our diverse collection of products designed for your everyday lifestyle. "
             "Whether you're upgrading your gadgets, refreshing your wardrobe, or shopping for daily "
             'essentials, NextGenAIBD brings your favorite finds together in one convenient place.')


def make_html(long=False):
    extra = (' '+section_copy+' '+item_copy)*2 if long else ''
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">{style_html}</head>
<body class="p34-storefront" style="margin:0;background:white;">
<div style="height:170px;padding:20px 24px;background:#f6f9f6">Header + site navigation (synthetic)</div>
<section class="sf-section" aria-label="Why Choose NextGenAIBD?" style="--sf-btn:#176a48;--sf-btn-text:#ffffff">
<div class="sf-wrapper" data-reveal>
<article class="p44-image-banner" aria-label="Everything You Need, All in One Place">
<img class="p44-image-banner__image" src="{photo}" alt="Collection image" loading="eager">
<div class="p44-image-banner__content">
<span class="p44-image-banner__eyebrow">Quality Products. Better Value. Smarter Shopping.</span>
<h2 class="p44-image-banner__title">Why Choose NextGenAIBD?</h2>
<p class="p44-image-banner__intro">{section_copy}{extra}</p>
<div class="p44-image-banner__detail">
<span class="p44-image-banner__eyebrow">Discover More</span>
<h3>Everything You Need, All in One Place</h3>
<p>{item_copy}{extra}</p>
<a class="sf-cta" href="#shop">Explore Products &rarr;</a>
</div></div></article></div></section>
<div id="shop" style="min-height:560px;padding:30px;background:#f5faf6">Storefront product collection</div>
<footer class="p19-footer" style="padding:32px 15px 150px">Storefront footer</footer>
<nav class="p19-mobile-nav p34-mobile-nav" aria-label="Mobile navigation">
<a href="#main">Home</a><a href="#main">Menu</a><a href="#main">Wishlist</a>
<a href="#main">Cart</a><a href="#main">Search</a><a href="#main">Account</a></nav>
<aside class="p10-contact p38-contact-dock" aria-label="Customer support contact shortcuts" data-p38-contact-dock>
<details class="p10-contact-menu p38-contact-menu">
<summary aria-label="Show WhatsApp, phone and email contact options"><span class="p38-contact-label">Contact</span><span class="p38-contact-plus">+</span></summary>
<nav aria-label="Contact methods" class="p10-contact-list p38-contact-list">
<a href="https://example.com" class="p38-contact-method p38-contact-whatsapp"><span class="p38-contact-method-icon">W</span>WhatsApp</a>
<a href="tel:+8801000000000" class="p38-contact-method p38-contact-call"><span class="p38-contact-method-icon">C</span>Call</a>
<a href="mailto:test@example.com" class="p38-contact-method p38-contact-email"><span class="p38-contact-method-icon">E</span>Email</a>
<a href="https://example.com" class="p38-contact-method p38-contact-messenger"><span class="p38-contact-method-icon">M</span>Messenger</a>
</nav></details></aside></body></html>'''


def rect(page, selector):
    return page.locator(selector).first.evaluate('e=>{const b=e.getBoundingClientRect();return {top:b.top,bottom:b.bottom,left:b.left,right:b.right,width:b.width,height:b.height}}')

with sync_playwright() as pw:
    browser = pw.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    results=[]
    for width,height in [(320,568),(360,640),(390,760),(430,800),(650,760),(760,700),(1024,768),(1366,900)]:
        page=browser.new_page(viewport={'width':width,'height':height},device_scale_factor=1,reduced_motion='reduce')
        page.set_content(make_html(),wait_until='load')
        page.add_script_tag(content=(root/'public/frontend/js/storefront-p38.js').read_text())
        page.wait_for_timeout(80)
        image=rect(page,'.p44-image-banner__image')
        banner=rect(page,'.p44-image-banner')
        content=rect(page,'.p44-image-banner__content')
        label=rect(page,'.p44-image-banner__eyebrow')
        cta=rect(page,'.p44-image-banner__detail .sf-cta')
        assert image['height']>=banner['height']-1 and abs(image['top']-banner['top'])<2,(width,'image cover')
        assert content['top']>=banner['top']-2 and content['bottom']<=banner['bottom']+2,(width,'text clipped')
        assert label['top']>=banner['top']+1 and cta['bottom']<=banner['bottom']-1,(width,'heading/cta clipped')
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 2'),(width,'horizontal page overflow')
        # Contact menu is viewport-sticky, including when document has scrolled.
        initial=rect(page,'[data-p38-contact-dock]')
        page.evaluate('window.scrollTo(0,document.body.scrollHeight)')
        page.wait_for_timeout(50)
        after=rect(page,'[data-p38-contact-dock]')
        assert abs(initial['bottom']-after['bottom'])<2,(width,'dock is not fixed')
        page.locator('[data-p38-contact-dock] summary').click()
        page.wait_for_timeout(70)
        assert page.locator('details.p38-contact-menu').evaluate('(e)=>e.open'),(width,'contact did not open')
        popup=rect(page,'.p38-contact-list')
        nav=rect(page,'.p19-mobile-nav')
        if width<=760:
            assert popup['bottom'] < nav['top']-8,(width,'popup under bottom nav',popup,nav)
            assert popup['top']>=6,(width,'popup clipped at viewport top',popup)
            assert popup['left']>=0 and popup['right']<=width,(width,'popup offscreen',popup)
            assert after['bottom']<nav['top']-15,(width,'contact button too low')
            for link in page.locator('.p38-contact-method').all():
                r=link.bounding_box()
                cx,cy=r['x']+r['width']/2,r['y']+r['height']/2
                hit=page.evaluate('([x,y])=>document.elementFromPoint(x,y)?.closest("a.p38-contact-method")?.textContent?.trim() || null',[cx,cy])
                assert hit is not None,(width,link.inner_text(),'method blocked by mobile nav')
        else:
            assert popup['top']>=0 and popup['bottom']<=height,(width,'desktop contact popup clipped')
        # Outside click and Escape must preserve P38 JS behavior.
        page.keyboard.press('Escape')
        assert not page.locator('details.p38-contact-menu').evaluate('(e)=>e.open'),(width,'escape did not close')
        page.locator('[data-p38-contact-dock] summary').click()
        page.mouse.click(20, 75)
        assert not page.locator('details.p38-contact-menu').evaluate('(e)=>e.open'),(width,'outside click did not close')
        if width==390:
            page.evaluate('window.scrollTo(0,600)')
            page.locator('[data-p38-contact-dock] summary').click()
            page.screenshot(path='/mnt/data/P44-MOBILE-PREVIEW-390.png',full_page=False)
        if width==1366:
            page.evaluate('window.scrollTo(0,0)')
            page.screenshot(path='/mnt/data/P44-DESKTOP-PREVIEW-1366.png',full_page=False)
        results.append({'width':width,'height':height,'banner_height':round(banner['height']),'popup_top':round(popup['top']),'popup_bottom':round(popup['bottom']),'nav_top':round(nav['top'])})
        page.close()
    # Extreme content still fits; the image is a true background, not a stacked separate panel.
    stress=browser.new_page(viewport={'width':320,'height':568},reduced_motion='reduce')
    stress.set_content(make_html(long=True),wait_until='load')
    b=rect(stress,'.p44-image-banner'); c=rect(stress,'.p44-image-banner__content')
    assert c['bottom']<=b['bottom']+1 and c['top']>=b['top']-1,('long text clipped',b,c)
    assert stress.evaluate('document.documentElement.scrollWidth <= innerWidth + 2'),'stress horizontally overflowed'
    print('P44 browser tests PASS:',len(results),'widths, all methods, scroll sticky, responsive image overlay, keyboard close, long text')
    print(json.dumps(results,indent=2))
    stress.close()
    browser.close()
