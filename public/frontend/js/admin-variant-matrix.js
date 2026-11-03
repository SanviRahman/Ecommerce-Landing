/* Admin-only, progressively enhanced cross-product SKU builder. */
(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', api.init, { once: true });
        else api.init();
    }
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';
    const CODES = ['color', 'size', 'weight', 'piece_count'];
    const keyFor = (values) => CODES.map((code) => code + ':' + String(values?.[code] ?? '')).join('|');

    function missingCombinations(groups, existing) {
        const result = [];
        let combinations = [{}];
        for (const group of groups) {
            const next = [];
            for (const partial of combinations) {
                for (const value of group.values) next.push({ ...partial, [group.code]: String(value.id) });
            }
            combinations = next;
        }
        const seen = new Set((Array.isArray(existing) ? existing : []).map((item) => keyFor(item.values)));
        for (const choice of combinations) {
            if (!seen.has(keyFor(choice))) result.push(choice);
        }
        return result;
    }

    function init() {
        const form = document.getElementById('bulk-variant-form');
        if (!form) return;
        const boxes = [...form.querySelectorAll('input[data-bulk-option]')];
        const status = form.querySelector('#bulk-matrix-status');
        const preview = form.querySelector('#bulk-matrix-preview');
        const review = form.querySelector('#bulk-matrix-review');
        const tbody = form.querySelector('#bulk-matrix-rows');
        const save = form.querySelector('#bulk-matrix-save');
        const apply = form.querySelector('#bulk-apply-values');
        const batchLimit = Math.min(60, Number(form.dataset.batchLimit) || 60, Number(form.dataset.remainingSlots) || 0);
        let existing = [];
        let oldRows = [];
        try { existing = JSON.parse(form.dataset.existingMatrix || '[]'); } catch (_) { /* Server remains authoritative. */ }
        try { oldRows = JSON.parse(form.dataset.oldRows || '[]'); } catch (_) { /* Ignore stale flash. */ }
        if (!Array.isArray(existing)) existing = [];
        if (!Array.isArray(oldRows)) oldRows = [];
        const oldRowMap = new Map(oldRows.map((row) => [keyFor(row.options), row]));

        function message(text, warning = false) {
            if (!status) return;
            status.textContent = text;
            status.classList.toggle('alert-warning', warning);
            status.classList.toggle('alert-light', !warning);
        }
        function invalidate() {
            if (review) review.hidden = true;
            if (tbody) tbody.replaceChildren();
            if (save) save.disabled = true;
            message('Values changed. Preview the combinations again before saving.');
        }
        function groupsSelected() {
            return CODES.map((code) => ({
                code,
                values: boxes.filter((box) => box.dataset.bulkOption === code && box.checked)
                    .map((box) => ({ id: box.value, label: box.dataset.bulkLabel || box.value })),
            })).filter((group) => boxes.some((box) => box.dataset.bulkOption === group.code));
        }
        function addInput(parent, type, name, value, label, required = false) {
            const input = document.createElement('input');
            input.type = type;
            input.name = name;
            input.value = String(value ?? '');
            if (type !== 'hidden') {
                input.className = 'form-control form-control-sm';
                input.setAttribute('aria-label', label);
                input.required = required;
                input.step = '1';
                input.min = type === 'number' && name.endsWith('[price_override]') ? '1' : '0';
                input.max = type === 'number' && name.endsWith('[stock]') ? '1000000' : '4294967295';
            }
            parent.appendChild(input);
            return input;
        }
        function buildRow(combo, index, groups) {
            const tr = document.createElement('tr');
            const description = document.createElement('th');
            description.scope = 'row';
            description.className = 'font-weight-normal';
            description.textContent = groups.map((group) => {
                const item = group.values.find((value) => value.id === combo[group.code]);
                return group.code.replace('_', ' ') + ': ' + (item?.label ?? combo[group.code]);
            }).join(' / ');
            const old = oldRowMap.get(keyFor(combo)) || {};
            CODES.forEach((code) => {
                if (combo[code] != null) addInput(description, 'hidden', `variants[${index}][options][${code}]`, combo[code], '');
            });
            tr.appendChild(description);
            const priceTd = document.createElement('td');
            addInput(priceTd, 'number', `variants[${index}][price_override]`, old.price_override ?? '', 'Selling price in BDT for ' + description.textContent, true);
            tr.appendChild(priceTd);
            const oldPriceTd = document.createElement('td');
            addInput(oldPriceTd, 'number', `variants[${index}][old_price_override]`, old.old_price_override ?? '', 'Optional old price for ' + description.textContent);
            tr.appendChild(oldPriceTd);
            const stockTd = document.createElement('td');
            addInput(stockTd, 'number', `variants[${index}][stock]`, old.stock ?? 0, 'Exact sellable pack stock for ' + description.textContent, true);
            tr.appendChild(stockTd);
            return tr;
        }
        function showPreview() {
            const groups = groupsSelected();
            if (groups.length === 0 || groups.some((group) => group.values.length === 0)) {
                invalidate();
                message('Select at least one value in every listed option group.', true);
                return;
            }
            const possibilities = groups.reduce((total, group) => total * group.values.length, 1);
            if (possibilities > 500) {
                invalidate();
                message('Too many possible combinations (' + possibilities + '). Choose fewer values, then build batches of up to 60 new SKUs.', true);
                return;
            }
            const missing = missingCombinations(groups, existing);
            if (missing.length === 0) {
                invalidate();
                message('All ' + possibilities + ' selected combinations already have a SKU. No new ones will be created. Existing/inactive SKUs can be reviewed below.');
                return;
            }
            if (missing.length > batchLimit) {
                invalidate();
                message(missing.length + ' new combinations selected, but this product can create at most ' + batchLimit + ' in this batch. Narrow your selections.', true);
                return;
            }
            if (tbody) {
                tbody.replaceChildren(...missing.map((combo, index) => buildRow(combo, index, groups)));
            }
            if (review) review.hidden = false;
            if (save) save.disabled = false;
            message(missing.length + ' new combinations to review. ' + (possibilities - missing.length) + ' existing/archived combinations skipped. Enter the exact price and stock for each row.');
        }

        boxes.forEach((box) => box.addEventListener('change', invalidate));
        preview?.addEventListener('click', showPreview);
        apply?.addEventListener('click', () => {
            if (!review || review.hidden) return;
            const price = form.querySelector('#bulk-price-all');
            const stock = form.querySelector('#bulk-stock-all');
            if (price?.value && price.checkValidity()) {
                tbody.querySelectorAll('input[name$="[price_override]"]').forEach((input) => { input.value = price.value; });
            }
            if (stock?.value !== '' && stock?.checkValidity()) {
                tbody.querySelectorAll('input[name$="[stock]"]').forEach((input) => { input.value = stock.value; });
            }
        });
        form.addEventListener('submit', (event) => {
            if (review?.hidden || !tbody?.children.length || !form.checkValidity()) {
                event.preventDefault();
                if (!form.checkValidity()) form.reportValidity();
                return;
            }
            if (typeof window.confirm === 'function' && !window.confirm(
                'Create ' + tbody.children.length + ' NEW SKUs with these exact prices and pack stocks? Existing SKUs will stay unchanged.'
            )) event.preventDefault();
        });

        // Laravel flashes the invalid submission. Rebuild the same combinations
        // and restore per-SKU numeric inputs, not a guessed price.
        if (oldRows.length) {
            boxes.forEach((box) => {
                box.checked = oldRows.some((row) => String(row.options?.[box.dataset.bulkOption] ?? '') === String(box.value));
            });
            showPreview();
        }
    }
    return { keyFor, missingCombinations, init };
});
