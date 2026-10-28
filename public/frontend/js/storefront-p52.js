/* P52: accessible WhatsApp fallback and local avatar preview. Never alters cart/order code. */
(() => {
  'use strict';
  const dialog = document.querySelector('[data-p52-whatsapp-dialog]');
  if (dialog) {
    document.querySelectorAll('[data-p52-whatsapp]').forEach((link) => {
      link.addEventListener('click', (event) => {
        if (typeof dialog.showModal !== 'function') return; // native https link fallback
        event.preventDefault();
        document.querySelector('[data-p38-contact-dock] details')?.removeAttribute('open');
        dialog.showModal();
      });
    });
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog.querySelector('[data-p52-copy-number]')?.addEventListener('click', async (event) => {
      const number = event.currentTarget.getAttribute('data-p52-copy-number');
      const status = dialog.querySelector('[data-p52-whatsapp-status]');
      if (!status || !number) return;
      try {
        if (!navigator.clipboard?.writeText) throw new Error('Clipboard not supported');
        await navigator.clipboard.writeText(number);
        status.textContent = 'Number copied. Open WhatsApp and paste it into a chat.';
      } catch (_) {
        status.textContent = 'Select and copy the number above to contact us.';
      }
    });
  }

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
