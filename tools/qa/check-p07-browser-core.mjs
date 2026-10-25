import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import path from 'node:path';
const script = fs.readFileSync(path.resolve(import.meta.dirname, '../../public/frontend/js/storefront-catalog.js'), 'utf8');
const listeners = new Map();
const element = (id, props = {}) => ({
  id, value: props.value || '', dataset: props.dataset || {}, textContent: props.textContent || '',
  form: props.form || null, addEventListener(name, fn) { listeners.set(`${id}:${name}`, fn); },
});
const a = element('a', {dataset:{optionId:'10'}});
const b = element('b', {dataset:{optionId:'20'}});
const price = element('price', {textContent:'From ৳999'});
const status = element('status');
const sku = element('sku');
const gallery = element('gallery', {dataset:{gallerySrc:'/safe/gallery.jpg', galleryAlt:'Alternate photo'}});
const photo = element('photo', {textContent:'', dataset:{}, src:'/main.jpg', alt:'Main photo'});
const form = {submitted:0, requestSubmit(){this.submitted++;}};
const sort = element('sort', {form});
const productMatrix = {
  options: [{id:10, name:'Color'},{id:20, name:'Size'}],
  variants: [
    {id:1,sku:'RED-M',price:1499,stock:2,selected:{10:11,20:21}},
    {id:2,sku:'BLUE-L',price:1800,stock:0,selected:{10:12,20:22}},
  ],
};
const picker = {dataset:{variantMatrix:JSON.stringify(productMatrix)},querySelectorAll(){return [a,b]}};
const document = {
  querySelectorAll(sel){return sel==='[data-auto-submit]'?[sort]:sel==='[data-gallery-src]'?[gallery]:[];},
  querySelector(sel){return sel==='[data-variant-matrix]'?picker:sel==='.p07-main-photo'?photo:null;},
  getElementById(id){return ({'p07-selected-price':price,'p07-variant-status':status,'p07-variant-sku':sku})[id]||null;},
};
vm.runInNewContext(script,{document, JSON, Number});
const cases = [
  ['Variant change handlers are installed', () => Boolean(listeners.get('a:change')) && Boolean(listeners.get('b:change'))],
  ['Option selections default to incomplete', () => {a.value='11';listeners.get('a:change')();return status.textContent.includes('Select all');}],
  ['Exact variant selection sets server-provided price', () => {b.value='21';listeners.get('b:change')();return price.textContent.includes('1,499') && sku.textContent.includes('RED-M');}],
  ['Server-provided stock available is displayed', () => status.textContent==='Available: 2'],
  ['Unavailable combination resets stale price', () => {b.value='22';listeners.get('b:change')();return price.textContent==='From ৳999' && status.textContent.includes('not available');}],
  ['Valid out-of-stock variant is labelled correctly', () => {a.value='12';listeners.get('a:change')();return price.textContent.includes('1,800') && status.textContent==='Currently out of stock';}],
  ['Clearing selection restores initial price', () => {a.value='';listeners.get('a:change')();return price.textContent==='From ৳999';}],
  ['Gallery selection updates image safely', () => {listeners.get('gallery:click')();return photo.src==='/safe/gallery.jpg'&&photo.alt==='Alternate photo';}],
  ['Sort changes submit existing form', () => {listeners.get('sort:change')();return form.submitted===1;}],
  ['JS never inserts client HTML', () => !script.includes('innerHTML') && !script.includes('insertAdjacentHTML')],
];
let pass=0;
for(const [name,fn] of cases){let okay=false;try{okay=Boolean(fn());}catch{};console.log(`${okay?'PASS':'FAIL'} ${name}`);if(okay)pass++;}
console.log(`RESULT: ${pass}/${cases.length} P07 JS DOM unit tests ${pass===cases.length?'PASS':'FAIL'} (no browser/database)`);
if(pass!==cases.length)process.exitCode=1;
