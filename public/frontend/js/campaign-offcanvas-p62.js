/* P62: campaign mobile/tablet off-canvas, preserving desktop Bootstrap layout.
   Menu links are the original campaign anchors; no routing/order changes. */
(() => {
  'use strict';
  const doc = document;
  const menu = doc.querySelector('[data-campaign-menu]');
  const opener = doc.querySelector('[data-campaign-menu-toggle]');
  const closer = doc.querySelector('[data-campaign-menu-close]');
  const backdrop = doc.querySelector('[data-campaign-menu-backdrop]');
  if (!menu || !opener || !closer || !backdrop) return;

  const mobile = window.matchMedia('(max-width: 991.98px)');
  let returnFocus = null;
  let previousOverflow = null;
  const isOpen = () => menu.classList.contains('is-open');
  const focusables = () => [...menu.querySelectorAll('a[href], button:not(:disabled)')]
    .filter(el => el.getClientRects().length > 0);

  const close = ({restoreFocus = true} = {}) => {
    const wasOpen = isOpen();
    menu.classList.remove('is-open');
    backdrop.hidden = true;
    opener.setAttribute('aria-expanded', 'false');
    opener.setAttribute('aria-label', 'Open campaign navigation');
    if (wasOpen) {
      doc.body.style.overflow = previousOverflow ?? '';
      previousOverflow = null;
    }
    if (restoreFocus && wasOpen && returnFocus?.isConnected) returnFocus.focus();
    returnFocus = null;
    menu.removeAttribute('role');
    menu.removeAttribute('aria-modal');
    menu.removeAttribute('aria-labelledby');
    menu.inert = mobile.matches;
    if (mobile.matches) menu.setAttribute('aria-hidden', 'true');
    else menu.removeAttribute('aria-hidden');
  };

  const open = () => {
    if (!mobile.matches || isOpen()) return;
    returnFocus = doc.activeElement;
    previousOverflow = doc.body.style.overflow;
    menu.inert = false;
    menu.removeAttribute('aria-hidden');
    menu.setAttribute('role', 'dialog');
    menu.setAttribute('aria-modal', 'true');
    menu.setAttribute('aria-labelledby', 'front-offcanvas-title');
    menu.classList.add('is-open');
    backdrop.hidden = false;
    opener.setAttribute('aria-expanded', 'true');
    opener.setAttribute('aria-label', 'Close campaign navigation');
    doc.body.style.overflow = 'hidden';
    closer.focus();
  };

  const syncViewport = () => {
    if (isOpen()) close({restoreFocus: false});
    menu.inert = mobile.matches;
    if (mobile.matches) menu.setAttribute('aria-hidden', 'true');
    else menu.removeAttribute('aria-hidden');
  };

  doc.documentElement.classList.add('p62-campaign-nav-ready');
  syncViewport();
  opener.addEventListener('click', () => isOpen() ? close() : open());
  closer.addEventListener('click', () => close());
  backdrop.addEventListener('click', () => close());
  menu.querySelectorAll('a[href]').forEach(link => link.addEventListener('click', () => {
    // Links to #order-section, review, tracking etc. retain their destination.
    close({restoreFocus: false});
  }));
  doc.addEventListener('keydown', (event) => {
    if (!isOpen()) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const targets = focusables();
    if (!targets.length) { event.preventDefault(); return; }
    const first = targets[0];
    const last = targets[targets.length - 1];
    if (event.shiftKey && doc.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && doc.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  if (typeof mobile.addEventListener === 'function') {
    mobile.addEventListener('change', syncViewport);
  } else if (typeof mobile.addListener === 'function') {
    mobile.addListener(syncViewport); // legacy Safari
  }
  window.addEventListener('pagehide', () => close({restoreFocus: false}));
  window.addEventListener('pageshow', (event) => { if (event.persisted) syncViewport(); });
})();
