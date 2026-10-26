/* P07: read-only catalog behaviors. No cart, payment, stock writes, or analytics. */
(() => {
  'use strict';
  document.querySelectorAll('[data-auto-submit]').forEach((field) => {
    field.addEventListener('change', () => field.form?.requestSubmit());
  });
  document.querySelectorAll('[data-gallery-src]').forEach((button) => {
    button.addEventListener('click', () => {
      const photo = document.querySelector('.p07-main-photo');
      if (!photo) return;
      // URL comes from a server-rendered media collection; no HTML injection.
      photo.src = button.dataset.gallerySrc || photo.src;
      photo.alt = button.dataset.galleryAlt || photo.alt;
    });
  });
  const picker = document.querySelector('[data-variant-matrix]');
  if (!picker) return;
  let data;
  try { data = JSON.parse(picker.dataset.variantMatrix || '{}'); } catch { return; }
  if (!Array.isArray(data.options) || !Array.isArray(data.variants)) return;
  const selectors = [...picker.querySelectorAll('select[data-option-id]')];
  const price = document.getElementById('p07-selected-price');
  const status = document.getElementById('p07-variant-status');
  const sku = document.getElementById('p07-variant-sku');
  const cartVariant = document.getElementById('p08-variant-id');
  const originalPrice = price?.textContent || '';
  const qty = document.getElementById('p08-product-quantity');
  const stickyPrice = document.querySelector('[data-p35-sticky-price]');
  const syncPurchase = (canSubmit, stock = null) => {
    document.querySelectorAll('[data-p35-variant-required]').forEach((button) => {
      button.disabled = !canSubmit || picker.dataset.p35ManagedProduct !== '1';
    });
    if (qty) {
      qty.max = String(stock === null ? 1000 : Math.max(1, Math.min(1000, stock)));
      qty.dispatchEvent(new Event('p35-stock-changed'));
    }
  };
  const update = () => {
    const selected = {};
    if (cartVariant) cartVariant.value = ''; 
    for (const field of selectors) {
      if (!field.value) {
        if (status) status.textContent = 'Select all options to view exact price and stock.';
        if (price) price.textContent = originalPrice;
        if (sku) sku.textContent = '';
        if (stickyPrice) stickyPrice.textContent = originalPrice;
        syncPurchase(false);
        return;
      }
      selected[field.dataset.optionId] = Number(field.value);
    }
    const variant = data.variants.find((row) =>
      selectors.every((field) => Number(row.selected?.[field.dataset.optionId]) === selected[field.dataset.optionId])
    );
    if (!variant) {
      if (status) status.textContent = 'This combination is not available.';
      if (price) price.textContent = originalPrice;
      if (sku) sku.textContent = '';
      if (stickyPrice) stickyPrice.textContent = originalPrice;
      syncPurchase(false);
      return;
    }
    if (price) price.textContent = '৳' + Number(variant.price).toLocaleString('en-BD');
    if (status) status.textContent = Number(variant.stock) > 0
      ? 'Available: ' + Number(variant.stock)
      : 'Currently out of stock';
    if (sku) sku.textContent = 'SKU: ' + variant.sku;
    if (cartVariant && Number(variant.stock) > 0) cartVariant.value = String(variant.id);
    if (stickyPrice) stickyPrice.textContent = price?.textContent || originalPrice;
    syncPurchase(Number(variant.stock) > 0, Number(variant.stock));
  };
  // Preserve the previously selected SKU after Laravel redirects back with
  // validation errors. Option picker remains the source of displayed price.
  if (cartVariant?.value) {
    const remembered = data.variants.find((row) => String(row.id) === cartVariant.value);
    if (remembered) {
      selectors.forEach((field) => {
        field.value = String(remembered.selected?.[field.dataset.optionId] ?? '');
      });
    }
  }
  update();
  selectors.forEach((field) => field.addEventListener('change', update));
  cartVariant?.addEventListener('change', () => {
    const chosen = data.variants.find((row) => String(row.id) === cartVariant.value);
    if (!chosen) return;
    selectors.forEach((field) => {
      field.value = String(chosen.selected?.[field.dataset.optionId] ?? '');
    });
    update();
  });
})();
