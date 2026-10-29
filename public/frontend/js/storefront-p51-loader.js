/* P52 - Branded 5.5s navigation reveal, as requested. No network dependencies.
 * Runs in head: no-script sessions never receive a blocking overlay.
 * BFCache restores close immediately. Watchdog prevents indefinite blocking.
 */
(() => {
  'use strict';
  const root = document.documentElement;
  const start = window.performance?.now?.() ?? Date.now();
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches === true;
  const minimum = reducedMotion ? 250 : 1000;
  let released = false;
  let queued = false;
  let finishTimer = null;
  root.classList.add('p51-booting');

  function finish() {
    if (released) return;
    released = true;
    if (finishTimer !== null) window.clearTimeout(finishTimer);
    root.classList.remove('p51-booting');
    root.classList.add('p51-ready');
  }

  function ready() {
    if (queued || released) return;
    queued = true;
    const elapsed = (window.performance?.now?.() ?? Date.now()) - start;
    finishTimer = window.setTimeout(finish, Math.max(0, minimum - elapsed));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready, { once: true });
  } else {
    ready();
  }
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) finish();
  });
  // Broken assets or blocked deferred scripts must never trap the visitor.
  window.setTimeout(finish, 8000);
})();
