'use strict';
// No external packages: execute real storefront-catalog.js with a minimal DOM.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,'../../public/frontend/js/storefront-catalog.js'),'utf8');

function smoke(rows) {
  const button = () => ({disabled: true});
  const purchase = [button(), button()];
  const choice = {value: '', dataset: {optionId: '10'}, addEventListener(name, listener) {this[name]=listener;}};
  const select = {value: '', addEventListener(name, listener) {this[name]=listener;}};
  const price = {textContent:'From ৳600'};
  const status = {textContent:''};
  const sku = {textContent:''};
  const qty = {max:'1000', dispatchEvent() {}};
  const sticky = {textContent:'From ৳600'};
  const picker = {
    dataset: {variantMatrix:JSON.stringify({options:[{id:10}],variants:rows}),p35ManagedProduct:'1'},
    querySelectorAll: (query) => query==='select[data-option-id]' ? [choice] : [],
  };
  const ids = {'p07-selected-price':price, 'p07-variant-status':status,
    'p07-variant-sku':sku, 'p08-variant-id':select, 'p08-product-quantity':qty};
  const document = {
    getElementById: (id) => ids[id] || null,
    querySelector: (query) => query==='[data-variant-matrix]'?picker:query==='[data-p35-sticky-price]'?sticky:null,
    querySelectorAll: (query) => query==='[data-p35-variant-required]'?purchase:[],
  };
  vm.runInNewContext(source,{document, Event:class{constructor(type){this.type=type;}}});
  return {choice, select, price, status, qty, purchase};
}
const one = smoke([{id:45,selected:{10:900},price:630,stock:40,sku:'ALIVE-BLK'}]);
assert.equal(one.choice.value,'900');
assert.equal(one.select.value,'45');
assert.equal(one.purchase[0].disabled,false);
assert.equal(one.purchase[1].disabled,false);
assert.equal(one.qty.max,'40');
assert.match(one.price.textContent,/630/);
const multiple = smoke([
  {id:45,selected:{10:900},price:630,stock:40,sku:'ALIVE-BLK'},
  {id:46,selected:{10:901},price:680,stock:14,sku:'ALIVE-WHT'},
]);
assert.equal(multiple.select.value,'');
assert.equal(multiple.purchase[0].disabled,true);
multiple.choice.value='901';multiple.choice.change();
assert.equal(multiple.select.value,'46');
assert.equal(multiple.purchase[0].disabled,false);
const soldOut=smoke([{id:47,selected:{10:900},price:630,stock:0,sku:'NONE'}]);
assert.equal(soldOut.purchase[0].disabled,true);
console.log('P48 variant picker: 3/3 scenarios passed (single, multiple, sold-out).');
