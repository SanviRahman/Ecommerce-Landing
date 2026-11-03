/* Campaign/homepage SKU previews only. Legacy campaign orders are not variant-aware. */
(() => {
  'use strict';
  document.querySelectorAll('[data-campaign-variant-matrix]').forEach((picker) => {
    let matrix;
    try { matrix = JSON.parse(picker.dataset.campaignVariantMatrix || '{}'); } catch { return; }
    const rows = Array.isArray(matrix.variants) ? matrix.variants : [];
    if (!rows.length) return;
    const radios = [...picker.querySelectorAll('input[data-option-id]')];
    const ids = [...new Set(radios.map((radio) => radio.dataset.optionId))];
    const productId = picker.dataset.campaignProductId;
    const first = rows.find((row) => Number(row.stock) > 0 && Number(row.price) > 0) || rows[0];
    if (first) radios.forEach((radio) => {
      radio.checked = Number(first.selected?.[radio.dataset.optionId]) === Number(radio.value);
    });
    const statusNode = picker.querySelector('[data-campaign-choice-status]');
    const onChange = () => {
      const selected = {};
      for (const id of ids) {
        const radio = radios.find((node) => node.dataset.optionId === id && node.checked);
        if (!radio) return;
        selected[id] = Number(radio.value);
      }
      const row = rows.find((candidate) => ids.every((id) =>
        Number(candidate.selected?.[id]) === selected[id]));
      if (statusNode) statusNode.textContent = !row
        ? 'This combination is not configured.'
        : Number(row.stock) > 0 ? 'Available stock: ' + Number(row.stock) : 'Out of stock';
      const canBuy = Boolean(row && Number(row.stock) > 0 && Number(row.price) > 0);
      document.querySelectorAll('[data-campaign-variant-link-for="' + productId + '"]').forEach((anchor) => {
        // A stale URL must never send a different SKU than the chips display.
        const target = new URL(anchor.href, window.location.origin);
        if (canBuy) target.searchParams.set('variant', String(row.id));
        else target.searchParams.delete('variant');
        target.hash = 'p07-variant-picker';
        anchor.href = target.href;
        anchor.hidden = !canBuy;
      });
      document.querySelectorAll('[data-campaign-price-for="' + productId + '"]').forEach((node) => {
        const text = row && Number(row.price) > 0
          ? '৳' + Number(row.price).toLocaleString('en-BD') : 'Unavailable';
        // Preserve old-price markup in campaign product-grid cards.
        if (node.classList.contains('product-price')) {
          const firstText = [...node.childNodes].find((child) => child.nodeType === 3);
          if (firstText) firstText.textContent = text + ' ';
        } else {
          node.textContent = text.startsWith('৳') ? '৳ ' + text.slice(1) : text;
        }
      });
    };
    // The product card itself is a separate legacy selection target. Clicking
    // on preview chips must not accidentally add an unpriced line to that order.
    picker.addEventListener('click', (event) => event.stopPropagation());
    picker.addEventListener('keydown', (event) => event.stopPropagation());
    radios.forEach((radio) => radio.addEventListener('change', () => {
      document.querySelectorAll('[data-campaign-variant-matrix][data-campaign-product-id="' + productId + '"]').forEach((other) => {
        if (other === picker) return;
        const otherRadio = other.querySelector('input[data-option-id="' + radio.dataset.optionId + '"][value="' + radio.value + '"]');
        if (otherRadio && !otherRadio.disabled) otherRadio.checked = true;
      });
      document.querySelectorAll('[data-campaign-variant-matrix][data-campaign-product-id="' + productId + '"]').forEach((other) => {
        other.dispatchEvent(new Event('sfa-preview-refresh'));
      });
    }));
    picker.addEventListener('sfa-preview-refresh', onChange);
    onChange();
  });
})();
