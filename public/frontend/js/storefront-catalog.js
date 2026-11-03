/* Read-only storefront catalog UI. All cart prices and inventory remain server-authoritative. */
(() => {
  'use strict';
  document.querySelectorAll('[data-auto-submit]').forEach((field) => {
    field.addEventListener('change', () => field.form?.requestSubmit());
  });
  document.querySelectorAll('[data-gallery-src]').forEach((button) => {
    button.addEventListener('click', () => {
      const photo = document.querySelector('.p07-main-photo');
      if (!photo) return;
      photo.src = button.dataset.gallerySrc || photo.src;
      photo.alt = button.dataset.galleryAlt || photo.alt;
    });
  });

  const picker = document.querySelector('[data-variant-matrix]');
  if (!picker) return;
  const matcher = window.SfaVariantMatcher;
  if (!matcher) return; // Fail closed if the selection helper could not load.
  let data;
  try { data = JSON.parse(picker.dataset.variantMatrix || '{}'); } catch { return; }
  if (!Array.isArray(data.options) || !Array.isArray(data.variants)) return;
  const inputs = [...picker.querySelectorAll('input[type="radio"][data-option-id]')];
  const optionIds = data.options.map((option) => String(option.id));
  const price = document.getElementById('p07-selected-price');
  const status = document.getElementById('p07-variant-status');
  const sku = document.getElementById('p07-variant-sku');
  const cartVariant = document.getElementById('p08-variant-id');
  const originalPrice = price?.textContent || '';
  const qty = document.getElementById('p08-product-quantity');
  const stickyPrice = document.querySelector('[data-p35-sticky-price]');
  let activeRow = null;

  const labelFor = (optionId, valueId) => {
    const option = data.options.find((item) => String(item.id) === String(optionId));
    const value = option?.values?.find((item) => String(item.id) === String(valueId));
    return value?.label ?? String(valueId);
  };
  const syncPurchase = (canSubmit, stock = null) => {
    document.querySelectorAll('[data-p35-variant-required]').forEach((button) => {
      button.disabled = !canSubmit || picker.dataset.p35ManagedProduct !== '1';
    });
    if (qty) {
      qty.max = String(stock === null ? 1000 : Math.max(1, Math.min(1000, stock)));
      qty.dispatchEvent(new Event('p35-stock-changed'));
    }
  };
  const checkRow = (row) => {
    inputs.forEach((input) => {
      input.checked = Boolean(row) && matcher.matches(row, input.dataset.optionId, input.value);
    });
  };
  const render = (row, adjusted = []) => {
    activeRow = row;
    if (cartVariant) cartVariant.value = '';
    if (!row) {
      if (status) status.textContent = 'No active variants are configured for this product yet.';
      if (price) price.textContent = originalPrice;
      if (sku) sku.textContent = '';
      if (stickyPrice) stickyPrice.textContent = originalPrice;
      syncPurchase(false);
      return;
    }
    const livePrice = Number(row.price) || 0;
    const priceText = livePrice > 0 ? '৳' + livePrice.toLocaleString('en-BD') : 'Price unavailable';
    if (price) price.textContent = priceText;
    if (stickyPrice) stickyPrice.textContent = priceText;
    const physical = Math.max(0, Number(row.physical_stock ?? row.stock) || 0);
    const sellable = Math.max(0, Number(row.stock) || 0);
    const adjustment = adjusted.length
      ? 'Adjusted ' + adjusted.map((id) => {
        const option = data.options.find((item) => String(item.id) === String(id));
        return (option?.name || 'Option') + ' to ' + labelFor(id, row.selected?.[id]);
      }).join(', ') + ' to match your choice. '
      : '';
    const stockText = physical === 0 ? 'Currently out of stock.'
      : 'Total stock: ' + physical + (sellable === 0 ? ' (temporarily reserved by pending orders).' : '.');
    if (status) status.textContent = adjustment + stockText;
    if (sku) sku.textContent = 'SKU: ' + row.sku;
    const canBuy = matcher.purchasable(row);
    if (cartVariant && canBuy) cartVariant.value = String(row.id);
    syncPurchase(canBuy, sellable);
  };

  // Only values belonging to a real active SKU are clickable. The user may
  // choose any such value regardless of which other dimensions are selected.
  inputs.forEach((input) => {
    input.disabled = !data.variants.some((row) => matcher.matches(row, input.dataset.optionId, input.value));
    input.addEventListener('change', () => {
      if (!input.checked || input.disabled) return;
      const before = activeRow ? matcher.selectionOf(activeRow, optionIds) : {};
      const next = matcher.forChange(data.variants, optionIds, before, input.dataset.optionId, input.value);
      if (!next) {
        checkRow(activeRow);
        return;
      }
      const adjusted = optionIds.filter((id) => id !== input.dataset.optionId &&
        before[id] != null && String(before[id]) !== String(next.selected?.[id]));
      checkRow(next);
      render(next, adjusted);
    });
  });

  const selected = matcher.initial(data.variants, cartVariant?.value);
  checkRow(selected);
  render(selected);
})();
