"""Synthetic, read-only browser visual/interaction QA for P42. No Laravel/DB access."""
from pathlib import Path
from urllib.parse import quote
from playwright.sync_api import sync_playwright
import json

root = Path(__file__).resolve().parents[2]
css = ['storefront-preview.css','storefront-catalog.css','storefront-next.css','storefront-premium.css',
 'storefront-p34.css','storefront-p35.css','storefront-p36.css','storefront-p37.css',
 'storefront-p38.css','storefront-p39.css','storefront-p41.css','storefront-p42.css','back-to-top.css']
css_html = '\n'.join('<style>'+(root/'public/frontend/css'/name).read_text()+'</style>' for name in css)
svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 700"><rect width="1600" height="700" fill="#58b9ba"/><circle r="260" cx="1180" cy="290" fill="#a4e8dd"/><path fill="#28748a" d="M660 700 L900 380 L1230 700Z"/></svg>'
img_src = 'data:image/svg+xml,'+quote(svg)
slides = '\n'.join(f'''<div class="sf-hero-panel p36-hero-with-copy" data-hero-slide role="group">
 <div class="sf-hero-copy"><span class="sf-eyebrow">Featured Collection</span>
 <h1>{'Shop Smarter. Live Better.' if idx==0 else 'Discover More. Shop Better.'}</h1>
 <p>Discover the best of fashion, electronics, beauty, and everyday essentials at NextGenAIBD. Explore handpicked products, amazing value, and a smarter shopping experience—all in one place.{' This second slide has extra descriptive content to verify responsive carousel height and no clipping.' if idx==1 else ''}</p>
 <a class="sf-cta" href="#details">Shop Now →</a></div>
 <img class="p36-hero-banner" src="{img_src}" alt="Sample banner">
 </div>''' for idx in range(2))
html = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
{css_html}
</head><body class="p34-storefront" style="margin:0"><main id="main"><section class="sf-section sf-section-hero"><div class="sf-wrapper">
<div class="sf-hero-carousel" data-hero-carousel aria-roledescription="carousel"><div class="sf-hero-track" data-hero-track>{slides}</div>
<div class="sf-carousel-controls" data-hero-controls hidden><button type="button" data-hero-prev>‹</button><div class="sf-carousel-dots" data-hero-dots></div><button type="button" data-hero-next>›</button></div>
</div></div></section><div id="details" style="height:1700px;padding:40px;background:#fff">Below the hero</div></main>

</body></html>'''
with sync_playwright() as pw:
 browser = pw.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox', '--allow-file-access-from-files'])
 for width, height in [(320,720),(390,844),(760,800),(1024,800),(1366,900)]:
  page = browser.new_page(viewport={'width':width,'height':height},device_scale_factor=1)
  page.set_content(html, wait_until='load')
  for filename in ['storefront-next.js','storefront-p36.js','back-to-top.js']:
   page.add_script_tag(content=(root/'public/frontend/js'/filename).read_text())
  page.locator('.p36-hero-banner').first.wait_for(state='visible')
  page.wait_for_timeout(250)
  result = page.evaluate('''() => {
   const rect=(el)=>{const r=el.getBoundingClientRect();return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,height:r.height,width:r.width}};
   const active=document.querySelector('[data-hero-slide]:not([aria-hidden="true"])');
   const copy=active.querySelector('.sf-hero-copy');
   const label=copy.querySelector('.sf-eyebrow');
   const heading=copy.querySelector('h1');
   const button=copy.querySelector('.sf-cta');
   const pic=active.querySelector('.p36-hero-banner');
   const carousel=document.querySelector('.sf-hero-carousel');
   const control=carousel.querySelector('[data-hero-controls]');
   return {viewport:innerWidth,carousel:rect(carousel),slide:rect(active),copy:rect(copy),label:rect(label),heading:rect(heading),button:rect(button),image:rect(pic),controls:rect(control),scrollHeight:document.documentElement.scrollHeight,labelDisplay:getComputedStyle(label).display};
  }''')
  print(json.dumps(result, ensure_ascii=False))
  if width<=760:
   assert result['labelDisplay'] != 'none'
   assert result['label']['top'] >= result['copy']['top']-1, 'label clipped at top'
   assert result['heading']['top'] >= result['copy']['top']-1, 'heading clipped at top'
   assert result['button']['bottom'] <= result['carousel']['bottom']+1, 'CTA clipped by carousel'
   assert result['button']['bottom'] <= result['controls']['top'] + 2, 'CTA covered by carousel buttons'
   assert result['image']['bottom'] <= result['label']['top'] + 1, 'image should remain completely visible above text'
  page.evaluate('window.scrollTo(0, 740)')
  page.wait_for_timeout(400)
  up=page.locator('[data-back-to-top]')
  assert up.count()==1 and up.evaluate("el=>el.classList.contains('is-visible')"), 'Back to top visible after scroll'
  up.click()
  page.wait_for_timeout(800)
  assert page.evaluate('window.scrollY') < 90, 'Back to top scrolls upward'
  if width==320:
   initial_height = result['carousel']['height']
   page.locator('[data-hero-next]').click()
   page.wait_for_timeout(650)
   assert page.locator('[data-hero-slide][aria-hidden="false"] .sf-eyebrow').count()==1
   switched_height = page.locator('.sf-hero-carousel').bounding_box()['height']
   assert switched_height >= initial_height, 'Longer slide should expand the mobile carousel'
   second_button = page.locator('[data-hero-slide][aria-hidden="false"] .sf-cta').bounding_box()
   assert second_button['y']+second_button['height'] <= page.locator('.sf-hero-carousel').bounding_box()['y']+switched_height, 'Second CTA never clipped' 
  page.close()
 browser.close()
print('P42 responsive Chromium smoke PASS: 5 widths, hero visibility, CTA, button scroll, slider')
