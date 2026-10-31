#!/usr/bin/env python3
"""Synthetic CSS/layout smoke: no Laravel/PHP/HTTP integration is asserted."""
from pathlib import Path
from playwright.sync_api import sync_playwright
import re

root = Path(__file__).resolve().parents[2]
blade = (root / 'resources/views/admin/homepage-shipping-charges/index.blade.php').read_text()
style = re.search(r'<style>(.*?)</style>', blade, re.S).group(1)
adminlte = (root / 'public/vendor/adminlte/dist/css/adminlte.min.css').read_text()
fa = (root / 'public/vendor/fontawesome-free/css/all.min.css').read_text()
form = '''
<div class="row align-items-end">
 <div class="form-group col-lg-5"><label>Delivery Area</label><input class="form-control" placeholder="Inside Dhaka" value="Inside Dhaka"></div>
 <div class="form-group col-lg-3"><label>Delivery Charge (BDT)</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">৳</span></div><input class="form-control" value="70"></div></div>
 <div class="form-group col-lg-2"><label>Availability</label><select class="form-control"><option>Active</option></select></div>
 <div class="form-group col-lg-2"><button class="btn p49-btn-primary btn-block">Add Area</button></div>
</div>'''
html = f'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>{adminlte}</style><style>{fa}</style><style>{style}</style><style>
body{{background:#f4f6f9}}.qa-sidebar{{position:fixed;top:0;left:0;bottom:0;width:225px;background:#343a40;color:white;padding:24px 14px;font-size:14px}}
.qa-sidebar .entry{{padding:12px 10px;display:block;color:#d6e3e0}}.qa-sidebar .entry.selected{{background:#007bff;border-radius:6px;color:white}}
.qa-main{{margin-left:225px;padding:30px 26px}}.qa-main h1{{color:#1b3930}}@media(max-width:760px){{.qa-sidebar{{display:none}}.qa-main{{margin-left:0;padding:15px 12px}}}}
</style></head><body>
<aside class="qa-sidebar"><h3>AdminLTE</h3><div class="entry">Site Settings</div><div class="entry selected">Shipping Charge for Homepage</div><div class="entry">Order Tracking Settings</div></aside>
<main class="qa-main"><div class="p49-titlebar"><h1>Shipping Charge for Homepage</h1><p class="text-muted">Manage delivery areas and charges for the main Storefront Checkout.</p></div>
<div class="p49-home-shipping pb-4">
<div class="p49-hero mb-4"><div class="p49-hero-symbol"><i class="fas fa-shipping-fast"></i></div><div><div class="p49-eyebrow">STOREFRONT DELIVERY SETTINGS</div><h2>Set shipping charges for your Homepage</h2><p class="mb-0">Customer selects an active delivery area at Checkout. The charge is calculated on the server. Campaign delivery charges stay independent.</p></div></div>
<div class="row p49-metrics"><div class="col-sm-4 mb-3"><div class="p49-metric"><span class="p49-metric-icon text-success"><i class="fas fa-check-circle"></i></span><div><strong>2</strong><span>Active delivery areas</span></div></div></div><div class="col-sm-4 mb-3"><div class="p49-metric"><span class="p49-metric-icon"><i class="fas fa-pause-circle"></i></span><div><strong>0</strong><span>Inactive areas</span></div></div></div><div class="col-sm-4 mb-3"><div class="p49-metric"><span class="p49-metric-icon text-success"><i class="fas fa-shield-alt"></i></span><div><strong class="p49-metric-status">Configured</strong><span>Homepage shipping availability</span></div></div></div></div>
<div class="p49-note mb-4"><i class="fas fa-info-circle mr-2"></i><span><strong>Scope protection:</strong> Only shipping rows without a Campaign are managed here. Campaign-specific charges stay independent.</span></div>
<div class="card p49-panel mb-4"><div class="card-header p49-panel-header"><h3 class="card-title">Add Delivery Area</h3><span class="p49-panel-hint">Homepage Checkout</span></div><div class="card-body">{form}</div></div>
<div class="card p49-panel"><div class="card-header p49-panel-header"><h3 class="card-title">Homepage Delivery Areas</h3><span class="p49-panel-hint">2 total</span></div><div class="card-body"><div class="p49-area-row"><div class="d-flex justify-content-between align-items-center flex-wrap mb-3"><div class="p49-area-name">Inside Dhaka</div><span class="badge badge-success p49-status">Active</span></div>{form}<div class="p49-row-footer"><span>Homepage only</span></div></div><div class="p49-area-row"><div class="d-flex justify-content-between align-items-center flex-wrap mb-3"><div class="p49-area-name">Outside Dhaka</div><span class="badge badge-success p49-status">Active</span></div>{form}</div></div></div>
</div></main></body></html>'''
page_file = root / 'tools/qa/.p49-layout-synthetic.html'
page_file.write_text(html)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in [320,390,768,1366]:
        page = browser.new_page(viewport={'width':width,'height':850}, device_scale_factor=1)
        page.set_content(html, wait_until='load')
        page.wait_for_timeout(90)
        result=page.evaluate('''() => ({
            overflow:document.documentElement.scrollWidth>innerWidth+1,
            headingVisible:(()=>{let r=document.querySelector('.p49-hero h2').getBoundingClientRect();return r.width>0&&r.left>=0&&r.right<=innerWidth+1;})(),
            controls:document.querySelectorAll('input.form-control').length,
            addButton:document.querySelector('.p49-btn-primary').getBoundingClientRect().width>0,
        })''')
        assert not result['overflow'], (width, result)
        assert result['headingVisible'] and result['controls']==6 and result['addButton'], (width,result)
        if width in (390,1366):
            target=Path('/mnt/data')/f'P49-HOMEPAGE-SHIPPING-{width}-SYNTHETIC.png'
            page.screenshot(path=str(target),full_page=True)
            print(f'Screenshot: {target}')
        print(f'PASS synthetic responsive viewport {width}px: no horizontal overflow; heading/forms visible')
        page.close()
    browser.close()
page_file.unlink(missing_ok=True)
