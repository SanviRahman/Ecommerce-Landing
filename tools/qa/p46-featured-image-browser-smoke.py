"""P46 isolated layout smoke test with actual storefront CSS, synthetic data only.
Does not boot Laravel, use live media, or touch database.
"""
from pathlib import Path
from urllib.parse import quote
from playwright.sync_api import sync_playwright
import json

root = Path(__file__).resolve().parents[2]
files=['storefront-preview.css','storefront-catalog.css','storefront-next.css','storefront-premium.css',
       'storefront-contact.css','storefront-p34.css','storefront-p35.css','storefront-p36.css',
       'storefront-p37.css','storefront-p38.css','storefront-p39.css','storefront-p41.css',
       'storefront-p42.css','storefront-p43.css','storefront-p44.css','storefront-p46.css']
styles='\n'.join('<style>\n'+(root/'public/frontend/css'/f).read_text()+'\n</style>' for f in files)
photo = 'data:image/svg+xml,'+quote('''<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="800"><defs><linearGradient id="g" x1="0" x2="1"><stop stop-color="#00694d"/><stop offset="1" stop-color="#042d83"/></linearGradient></defs><rect width="1600" height="800" fill="url(#g)"/><g fill="none" stroke="#5691ff" stroke-width="8" opacity=".6"><path d="M1250,-200 Q850,230 1310,660 T1760,1100"/><path d="M1450,-240 Q1060,270 1530,640 T1960,1050"/></g></svg>''')
placeholder='data:image/svg+xml,'+quote('''<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240"><rect width="240" height="240" fill="#e8f9ef"/><rect x="70" y="67" width="100" height="100" rx="11" fill="none" stroke="#77dca3" stroke-width="7"/><path d="M81 158 L109 117 L133 139 L157 114 L165 160 Z" fill="#60d68b"/></svg>''')
products=''.join(f'''<article class="sf-tile sf-product p34-product-card"><a class="sf-product-image" href="#item{i}"><img src="{placeholder}" alt="Product {i}"></a><div class="sf-product-copy"><p class="sf-product-category">Collection</p><h3>Product {i}</h3><div class="sf-price">৳1,250</div><div class="sf-product-actions p34-product-actions"><a class="sf-secondary" href="#">+</a><a class="sf-cta p34-buy-button" href="#item{i}">View Product</a></div></div></article>''' for i in range(1,9))

def make_html():
 return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">{styles}</head>
<body class="p34-storefront" style="margin:0;background:white">
<div style="height:170px;background:#f2f6f3;padding:24px">Representative site navigation</div>
<main id="main">
<section class="sf-section" aria-label="Handpicked Favorites" style="--sf-btn:#176a48;--sf-btn-text:#fff">
<div class="sf-wrapper"><p class="sf-kicker">Discover something special every day</p>
<div class="sf-title-line"><h2>Handpicked Favorites for You</h2><span class="sf-title-decoration"></span></div>
<p class="sf-lead">Explore a handpicked collection of quality products.</p>
<div class="sf-grid sf-grid-products p46-featured-grid">{products}</div>
<div class="p46-view-all-wrap"><a class="p46-view-all" href="/storefront/shop">View All Products <span>→</span></a></div>
</div></section>
<section class="sf-section p46-image-text" aria-label="Why Choose NextGenAIBD?" style="--sf-btn:#176a48;--sf-btn-text:#fff">
<div class="sf-wrapper">
<div class="p46-image-intro"><p class="sf-kicker">Quality Products. Better Value. Smarter Shopping.</p>
<div class="sf-title-line"><h2>Why Choose NextGenAIBD?</h2><span class="sf-title-decoration"></span></div>
<p class="sf-lead">At NextGenAIBD, we believe shopping should be simple, enjoyable, and convenient. Discover a carefully selected range of quality products, from fashion and electronics to beauty and everyday essentials. Our mission is to make your shopping experience smarter, easier, and more rewarding.</p></div>
<article class="p44-image-banner"><img class="p44-image-banner__image" src="{photo}" alt="Abstract background">
<div class="p44-image-banner__content"><div class="p44-image-banner__detail"><span class="p44-image-banner__eyebrow">Discover More</span>
<h3>Everything You Need, All in One Place</h3>
<p>Explore our diverse collection of products designed for your everyday lifestyle. Whether you're upgrading your gadgets, refreshing your wardrobe, or shopping for daily essentials, NextGenAIBD brings your favorite finds together in one convenient place.</p>
<a class="sf-cta" href="/storefront/shop">Explore Products →</a></div></div></article>
</div></section></main></body></html>'''

def rect(page,css):
 return page.locator(css).first.evaluate('(el) => {const r=el.getBoundingClientRect();return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,width:r.width,height:r.height}}')

with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
 results=[]
 for w,h in [(320,680),(360,760),(390,844),(430,844),(760,850),(900,850),(1024,900),(1366,900)]:
  page=browser.new_page(viewport={'width':w,'height':h},device_scale_factor=1,reduced_motion='reduce')
  page.set_content(make_html(),wait_until='load')
  cards=page.locator('.p46-featured-grid>.sf-product')
  assert cards.count()==8,(w,'not eight product cards')
  bounds=[card.bounding_box() for card in cards.all()]
  columns=4 if w>900 else 2
  for i in range(0,8,columns):
   ys=[round(x['y']) for x in bounds[i:i+columns]]
   assert max(ys)-min(ys)<3,(w,'nonuniform row positions',ys)
  if w>900:
   assert abs(bounds[4]['y']-bounds[0]['y'])>100,(w,'missing second row')
  cta=rect(page,'.p46-view-all')
  last=rect(page,'.p46-featured-grid>.sf-product:last-child')
  assert cta['top']>last['bottom']+15,(w,'view all button not after cards',cta,last)
  assert page.locator('.p46-view-all').get_attribute('href')=='/storefront/shop',(w,'view all link')
  intro=rect(page,'.p46-image-intro')
  banner=rect(page,'.p44-image-banner')
  assert intro['bottom']<banner['top'],(w,'section intro overlays photo')
  content=rect(page,'.p44-image-banner__content')
  assert content['top']>=banner['top']-1 and content['bottom']<=banner['bottom']+1,(w,'item overlay clipped')
  assert page.locator('.p46-image-intro h2').count()==1,(w,'section heading missing')
  assert page.locator('.p44-image-banner h2').count()==0,(w,'section heading repeats on image')
  assert page.locator('.p44-image-banner .sf-cta').is_visible(),(w,'item CTA unavailable')
  assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 2'),(w,'horizontal overflow')
  if w in (390,1366):
   page.locator('.p46-image-text').scroll_into_view_if_needed()
   page.screenshot(path=f'/mnt/data/P46-IMAGE-TEXT-{w}.png',full_page=False)
   page.locator('.p46-featured-grid').scroll_into_view_if_needed()
   page.screenshot(path=f'/mnt/data/P46-FEATURED-{w}.png',full_page=False)
  results.append({'viewport':w,'columns':columns,'rows':8//columns,'intro_above_image':True,'button_after_grid':True,'no_overflow':True})
  page.close()
 print('P46 synthetic Chromium checks:',json.dumps(results,indent=2))
 browser.close()
