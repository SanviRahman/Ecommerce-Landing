/** Lightweight, progressively enhanced helper for the existing admin SKU form. */
(function () {
    'use strict';

    const DIMENSIONS = ['color', 'size', 'weight', 'piece_count'];

    function applyTemplateValues(template) {
        const values = (template && template.values) || {};
        return Object.fromEntries(DIMENSIONS.map((code) => [code, String(values[code] || '')]));
    }

    function keyForSelection(selection) {
        const values = applyTemplateValues({ values: selection });
        return DIMENSIONS.map((code) => `${code}:${values[code]}`).join('|');
    }

    function findDuplicate(selection, existing) {
        const key = keyForSelection(selection);
        return (Array.isArray(existing) ? existing : []).find(
            (variant) => keyForSelection(variant.values) === key
        ) || null;
    }

    function init() {
        const form = document.getElementById('variant-quick-add-form');
        if (!form) return;

        const copyFrom = form.querySelector('#variant-copy-from');
        const optionInputs = Array.from(form.querySelectorAll('[data-variant-option]'));
        const saveButton = form.querySelector('[data-save-variant]');
        const status = form.querySelector('[data-variant-selection-status]');
        let existing = [];
        try {
            existing = JSON.parse(form.dataset.existingVariants || '[]');
        } catch (_) {
            // Invalid inline data must not skip server-side duplicate validation.
        }

        function currentSelection() {
            return Object.fromEntries(optionInputs.map((input) => [input.dataset.variantOption, input.value]));
        }

        function checkSelection() {
            const selection = currentSelection();
            const anySelected = optionInputs.some((input) => input.value !== '');
            const incomplete = optionInputs.some((input) => input.required && !input.value);
            const duplicate = anySelected && !incomplete ? findDuplicate(selection, existing) : null;
            if (saveButton) saveButton.disabled = !anySelected || incomplete || Boolean(duplicate);
            if (!status) return;

            status.classList.remove('text-danger', 'text-success', 'text-muted');
            if (duplicate) {
                status.classList.add('text-danger');
                status.textContent = `This combination already exists (${duplicate.sku}). Change at least one option, such as Piece Count.`;
            } else if (incomplete || !anySelected) {
                status.classList.add('text-muted');
                status.textContent = incomplete
                    ? 'Choose a value for every option used by this product.'
                    : 'Choose at least one option to create a variant.';
            } else {
                status.classList.add('text-success');
                status.textContent = 'New combination selected. Enter its own price and exact stock, then save.';
            }
        }

        function copyTemplate() {
            const chosen = copyFrom && copyFrom.selectedOptions[0];
            if (!chosen) return;
            const template = existing.find((variant) => String(variant.id) === String(chosen.value));
            if (!template) return;
            const values = applyTemplateValues(template);
            optionInputs.forEach((input) => {
                const wanted = values[input.dataset.variantOption] || '';
                input.value = wanted;
                // An inactive option value must never be submitted as if valid.
                if (input.value !== wanted) input.value = '';
            });
            checkSelection();
        }

        optionInputs.forEach((input) => input.addEventListener('change', checkSelection));
        if (copyFrom) copyFrom.addEventListener('change', copyTemplate);
        if (copyFrom && copyFrom.value && form.dataset.oldInput !== '1') copyTemplate();
        checkSelection();
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { keyForSelection, findDuplicate, applyTemplateValues };
    }
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
        else init();
    }
})();
