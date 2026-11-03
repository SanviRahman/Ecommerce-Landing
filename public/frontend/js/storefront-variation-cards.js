/* Grouped live SKU selectors for storefront homepage, shop and category cards. */
(() => {
  'use strict';
  document.querySelectorAll('[data-card-variant-matrix]').forEach((picker) => {
    let matrix;
    try { matrix = JSON.parse(picker.dataset.cardVariantMatrix || '{}'); } catch { return; }
    if (!Array.isArray(matrix.variants) || !Array.isArray(matrix.options)) return;
    const card = picker.closest('article');
    if (!card) return;
    const radios = [...picker.querySelectorAll('input[data-option-id]')];
    const ids = [...new Set(radios.map((radio) => radio.dataset.optionId))];
    const form = card.querySelector('form[data-async-cart]');
    const variantInput = form?.querySelector('input[name="product_variant_id"]');
    const priceNode = card.querySelector('.sf-price, .p07-card-prices strong');
    const statusNode = picker.querySelector('[data-card-variant-status]');
    const actions = form ? [...form.querySelectorAll('button[type="submit"]')] : [];
    const first = matrix.variants.find((row) => Number(row.stock) > 0 && Number(row.price) > 0)
      || matrix.variants[0];
    if (first) radios.forEach((radio) => {
      radio.checked = Number(first.selected?.[radio.dataset.optionId]) === Number(radio.value);
    });
    const redraw = () => {
      const chosen = {};
      for (const id of ids) {
        const radio = radios.find((item) => item.dataset.optionId === id && item.checked);
        if (!radio) return;
        chosen[id] = Number(radio.value);
      }
      const row = matrix.variants.find((variant) => ids.every((id) =>
        Number(variant.selected?.[id]) === chosen[id]));
      if (variantInput) variantInput.value = row && Number(row.stock) > 0 ? String(row.id) : '';
      if (row) {
        if (priceNode) priceNode.textContent = '৳' + Number(row.price).toLocaleString('en-BD');
        if (statusNode) statusNode.textContent = Number(row.stock) > 0
          ? 'Available: ' + Number(row.stock) : 'Out of stock';
      } else if (statusNode) {
        statusNode.textContent = 'This combination has not been configured for this product.';
      }
      const canSubmit = Boolean(row && Number(row.price) > 0 && Number(row.stock) > 0);
      actions.forEach((button) => { button.disabled = !canSubmit; });
    };
    radios.forEach((radio) => radio.addEventListener('change', redraw));
    redraw();
  });
})();
