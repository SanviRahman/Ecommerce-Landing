const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(__dirname + '/../../public/frontend/js/storefront-p52.js', 'utf8');
function run(userAgent, mobileFlag) {
  const link = {
    dataset: { p55MobileUrl: 'whatsapp://send?phone=8801700000000' },
    href: 'https://api.whatsapp.com/send?phone=8801700000000',
    target: '_blank',
    setAttribute(k, v) { this[k] = v; },
    removeAttribute(k) { delete this[k]; },
  };
  const document = {
    querySelectorAll(selector) {
      assert.equal(selector, '[data-p55-whatsapp-direct]');
      return [link];
    },
    querySelector() { return null; },
  };
  vm.runInNewContext(source, {document, navigator:{userAgent, userAgentData:{mobile:mobileFlag}}, window:{addEventListener(){}}, URL});
  return link;
}
const mobile = run('Mozilla/5.0 (Linux; Android 14; Pixel 7)', true);
assert.equal(mobile.href, 'whatsapp://send?phone=8801700000000');
assert.equal(mobile.target, undefined);
const desktop = run('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', false);
assert.equal(desktop.href, 'https://api.whatsapp.com/send?phone=8801700000000');
assert.equal(desktop.target, '_blank');
console.log('P55 direct WhatsApp mobile/desktop: 2/2 passed');
