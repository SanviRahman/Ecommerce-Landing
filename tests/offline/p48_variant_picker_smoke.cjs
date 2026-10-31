'use strict';
// Run the actual P61 product-picker script with a minimal DOM (no packages).
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,'../../public/frontend/js/storefront-catalog.js'), 'utf8');

function smoke(rows, rememberedId = '') {
  const purchase = [{disabled: true}, {disabled: true}];
  const choices = [...new Set(rows.map(row => String(row.selected?.[10])).filter(Boolean))]
    .map(value => ({value, checked:false, dataset:{optionId:'10'},
      addEventListener(type, fn) {this[type] = fn;}}));
  const selectedVariant = {value:rememberedId};
  const price = {textContent:'From ৳600'};
  const status = {textContent:''};
  const sku = {textContent:''};
  const qty = {max:'1000', dispatchEvent() {}};
  const sticky = {textContent:'From ৳600'};
  const picker = {
    dataset: {variantMatrix:JSON.stringify({options:[{id:10}],variants:rows}),p35ManagedProduct:'1'},
    querySelectorAll(query) {return query==='input[type="radio"][data-option-id]' ? choices : [];},
  };
  const ids = {'p07-selected-price':price, 'p07-variant-status':status,
    'p07-variant-sku':sku, 'p08-variant-id':selectedVariant, 'p08-product-quantity':qty};
  const document = {
    getElementById:id => ids[id] || null,
    querySelector:query => query==='[data-variant-matrix]' ? picker : query==='[data-p35-sticky-price]' ? sticky : null,
    querySelectorAll:query => query==='[data-p35-variant-required]' ? purchase : [],
  };
  vm.runInNewContext(source,{document, Event:class{constructor(type){this.type=type;}}});
  return {choices, selectedVariant, price, status, sku, qty, purchase};
}
const one = smoke([{id:45,selected:{10:900},price:630,stock:40,sku:'ALIVE-BLK'}]);
assert.equal(one.choices[0].checked,true);
assert.equal(one.selectedVariant.value,'45');
assert.equal(one.purchase[0].disabled,false);
assert.equal(one.purchase[1].disabled,false);
assert.equal(one.qty.max,'40');
assert.match(one.price.textContent,/630/);
const multiple = smoke([
  {id:45,selected:{10:900},price:630,stock:40,sku:'ALIVE-BLK'},
  {id:46,selected:{10:901},price:680,stock:14,sku:'ALIVE-WHT'},
]);
assert.equal(multiple.selectedVariant.value,'');
assert.equal(multiple.purchase[0].disabled,true);
multiple.choices[1].checked=true;
multiple.choices[1].change();
assert.equal(multiple.selectedVariant.value,'46');
assert.equal(multiple.purchase[0].disabled,false);
assert.equal(multiple.qty.max,'14');
const soldOut=smoke([{id:47,selected:{10:900},price:630,stock:0,sku:'NONE'}]);
assert.equal(soldOut.purchase[0].disabled,true);
assert.equal(soldOut.selectedVariant.value,'');
const restored = smoke([
  {id:45,selected:{10:900},price:630,stock:40,sku:'ALIVE-BLK'},
  {id:46,selected:{10:901},price:680,stock:14,sku:'ALIVE-WHT'},
], '46');
assert.equal(restored.choices[1].checked,true);
assert.equal(restored.selectedVariant.value,'46');
console.log('P61 variant picker: 4/4 scenarios passed (one SKU, multiple, sold-out, restored choice).');
