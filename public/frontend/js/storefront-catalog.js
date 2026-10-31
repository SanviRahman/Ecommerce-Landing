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
  const inputs = [...picker.querySelectorAll('input[type="radio"][data-option-id]')];
  const optionIds = [...new Set(inputs.map((input) => input.dataset.optionId))];
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
  const checkedSelection = () => {
    const selected = {};
    for (const id of optionIds) {
      const input = inputs.find((node) => node.dataset.optionId === id && node.checked);
      if (!input) return null;
      selected[id] = Number(input.value);
    }
    return selected;
  };
  const chooseVariant = (variant) => {
    inputs.forEach((input) => {
      input.checked = Number(variant.selected?.[input.dataset.optionId]) === Number(input.value);
    });
  };
  const update = () => {
    const selected = checkedSelection();
    if (cartVariant) cartVariant.value = '';
    if (!selected) {
      if (status) status.textContent = 'Select all options to view exact price and stock.';
      if (price) price.textContent = originalPrice;
      if (sku) sku.textContent = '';
      if (stickyPrice) stickyPrice.textContent = originalPrice;
      syncPurchase(false);
      return;
    }
    const variant = data.variants.find((row) => optionIds.every((id) =>
      Number(row.selected?.[id]) === selected[id]
    ));
    if (!variant) {
      if (status) status.textContent = 'This combination is not available.';
      if (price) price.textContent = originalPrice;
      if (sku) sku.textContent = '';
      if (stickyPrice) stickyPrice.textContent = originalPrice;
      syncPurchase(false);
      return;
    }
    if (price) price.textContent = '৳' + Number(variant.price).toLocaleString('en-BD');
    // Physical count stays unchanged while orders are Processing. The `stock`
    // field is the separately enforced sellable quantity after reservations.
    const physicalStock = Math.max(0, Number(variant.physical_stock ?? variant.stock) || 0);
    const sellableStock = Math.max(0, Number(variant.stock) || 0);
    if (status) status.textContent = physicalStock === 0
      ? 'Currently out of stock'
      : 'Total stock: ' + physicalStock + (sellableStock === 0
          ? ' (temporarily reserved by pending orders)' : '');
    if (sku) sku.textContent = 'SKU: ' + variant.sku;
    if (cartVariant && Number(variant.stock) > 0) cartVariant.value = String(variant.id);
    if (stickyPrice) stickyPrice.textContent = price?.textContent || originalPrice;
    syncPurchase(Number(variant.stock) > 0, Number(variant.stock));
  };
  // A validation redirect restores the exact option chips, not an unrelated SKU.
  const rememberedId = cartVariant?.value;
  const remembered = data.variants.find((row) => String(row.id) === rememberedId);
  if (remembered) chooseVariant(remembered);
  if (!remembered && optionIds.length) {
    const available = data.variants.filter((row) => Number(row.stock) > 0 && Number(row.price) > 0);
    if (available.length === 1) chooseVariant(available[0]);
  }
  update();
  inputs.forEach((input) => input.addEventListener('change', update));
})();
