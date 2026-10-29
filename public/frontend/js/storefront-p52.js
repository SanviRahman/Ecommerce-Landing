/* P52: accessible WhatsApp fallback and local avatar preview. Never alters cart/order code. */
(() => {
  'use strict';
  // P55: Open WhatsApp directly (like tel: for Call) rather than show a modal
  // or embed wa.me in an iframe. Real mobile OS handles the whatsapp:// scheme;
  // desktop and responsive simulators use an external https browser tab.
  const mobileDevice = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '')
    || Boolean(navigator.userAgentData?.mobile);
  document.querySelectorAll('[data-p55-whatsapp-direct]').forEach((link) => {
    if (mobileDevice) {
      const appHref = link.dataset.p55MobileUrl || '';
      if (/^whatsapp:\/\/send\?phone=[1-9][0-9]{7,14}$/.test(appHref)) {
        link.setAttribute('href', appHref);
        link.removeAttribute('target');
      }
    }
    // Do not preventDefault/window.open: native navigation must be triggered
    // by the customer's direct click, not through a framed intermediary.
  });

  const photoInput = document.querySelector('[data-p52-photo-input]');
  const preview = document.querySelector('[data-p52-photo-preview]');
  if (!photoInput || !preview) return;
  let previousUrl = null;
  photoInput.addEventListener('change', () => {
    if (previousUrl) URL.revokeObjectURL(previousUrl);
    previousUrl = null;
    const file = photoInput.files?.[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
      photoInput.value = '';
      return;
    }
    previousUrl = URL.createObjectURL(file);
    const img = document.createElement('img');
    img.src = previousUrl;
    img.alt = 'Selected profile photo preview';
    preview.replaceChildren(img);
  });
  window.addEventListener('pagehide', () => {
    if (previousUrl) URL.revokeObjectURL(previousUrl);
  });
})();
