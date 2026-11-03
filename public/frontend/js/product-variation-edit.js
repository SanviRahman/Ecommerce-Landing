/** Keep the Edit variation dropdowns in sync with the selected, existing SKU. */
(() => {
    'use strict';

    document.addEventListener('change', (event) => {
        const skuSelect = event.target;
        if (!(skuSelect instanceof HTMLSelectElement) || skuSelect.id !== 'managed-stock-sku') return;
        const form = skuSelect.closest('form');
        const sku = skuSelect.selectedOptions[0];
        if (!form || !sku) return;

        const stock = form.querySelector('#base-product-stock');
        const expectedStock = form.querySelector('#expected-variant-stock');
        if (stock) stock.value = sku.dataset.currentStock ?? '0';
        if (expectedStock) expectedStock.value = sku.dataset.currentStock ?? '0';

        const expectedSignature = form.querySelector('#expected-variation-signature');
        if (expectedSignature) expectedSignature.value = sku.dataset.variationSignature ?? '';
        form.querySelectorAll('[data-edit-variant-option]').forEach((dropdown) => {
            const code = dropdown.dataset.editVariantOption;
            const value = sku.getAttribute('data-option-' + code) || '';
            dropdown.value = value;
            // If a referenced old option has been removed from the manager,
            // never pretend it is an empty selection.
            if (dropdown.value !== value) dropdown.value = '';
        });
    });
})();
