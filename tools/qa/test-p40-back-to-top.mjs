/** Node-only DOM simulation: verifies scroll button behavior without a browser or network. */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { runInNewContext } from 'node:vm';
const script = readFileSync(resolve(import.meta.dirname, '../../public/frontend/js/back-to-top.js'), 'utf8');
function runCase(reduced) {
  const listeners = {};
  const docListeners = {};
  const classes = new Set();
  const button = {
    className: '', attributes: {}, tabIndex: -1,
    setAttribute(key, value) { this.attributes[key] = value; },
    addEventListener(type, fn) { this[`on_${type}`] = fn; },
    classList: { toggle(key, enabled) { if (enabled) classes.add(key); else classes.delete(key); } },
  };
  const scrollCalls = [];
  const document = {
    readyState: 'loading',
    querySelector: () => null,
    createElement: (tag) => { assert.equal(tag, 'button'); return button; },
    body: { appendChild(child) { assert.equal(child, button); this.appended = child; } },
    addEventListener(type, fn) { docListeners[type] = fn; },
  };
  const window = {
    scrollY: 0,
    addEventListener(type, fn) { listeners[type] = fn; },
    requestAnimationFrame(fn) { fn(); },
    matchMedia: () => ({ matches: reduced }),
    scrollTo(opts) { scrollCalls.push(opts); },
  };
  runInNewContext(script, { window, document });
  docListeners.DOMContentLoaded();
  assert.equal(button.attributes['aria-hidden'], 'true');
  assert.equal(button.tabIndex, -1);
  window.scrollY = 400;
  listeners.scroll();
  assert.equal(button.attributes['aria-hidden'], 'false');
  assert.equal(button.tabIndex, 0);
  assert.equal(classes.has('is-visible'), true);
  button.on_click();
  assert.equal(scrollCalls.length, 1);
  assert.equal(scrollCalls[0].top, 0);
  assert.equal(scrollCalls[0].behavior, reduced ? 'auto' : 'smooth');
  window.scrollY = 0;
  listeners.scroll();
  assert.equal(classes.has('is-visible'), false);
}
runCase(false);
runCase(true);
console.log('PASS P40 back-to-top DOM simulation: normal + reduced-motion cases');
