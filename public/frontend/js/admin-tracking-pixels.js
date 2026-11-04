(function () {
    'use strict';

    const form = document.getElementById('sfa-pixel-form');
    if (!form) return;

    const container = document.getElementById('sfa-pixel-entries');
    const template = document.getElementById('sfa-pixel-template');
    let nextIndex = 0;

    if (container) {
        container.querySelectorAll('[name^="pixels["]').forEach(function (input) {
            const match = input.name.match(/^pixels\[(\d+)\]/);
            if (match) nextIndex = Math.max(nextIndex, Number(match[1]) + 1);
        });
    }

    function refresh(row) {
        const platform = row.querySelector('[data-platform]');
        const pixelId = row.querySelector('[data-pixel-id]');
        const idLabel = row.querySelector('[data-id-label]');
        const idHelp = row.querySelector('[data-id-help]');
        const capiPanel = row.querySelector('[data-capi-fields]');
        const enabled = row.querySelector('[data-capi-switch]');
        const token = row.querySelector('[data-capi-token]');
        const script = row.querySelector('[data-script]');
        const scriptHelp = row.querySelector('[data-script-help]');
        const gtmHelp = row.querySelector('[data-gtm-help]');
        const isMeta = platform && platform.value === 'meta';
        const isGtm = platform && platform.value === 'gtm';
        const hasScript = Boolean(script && script.value.trim());

        if (!isMeta && enabled) enabled.checked = false;
        if (capiPanel) capiPanel.hidden = !isMeta;
        if (token) token.required = Boolean(isMeta && enabled && enabled.checked && row.dataset.hasToken !== '1');

        if (idLabel) {
            idLabel.textContent = isGtm ? 'GTM Container ID *' : 'Manual Pixel ID / Container ID';
        }
        if (pixelId) {
            pixelId.placeholder = isGtm ? 'GTM-TS7Q7WGG' : 'e.g. 2355381278153504';
            // A GTM ID is required unless the optional legacy snippet supplies it.
            pixelId.required = Boolean(isGtm && !hasScript);
        }
        if (idHelp) {
            idHelp.textContent = isGtm
                ? 'Enter a valid GTM Container ID beginning with GTM-. The ID is enough; a script is not required.'
                : 'Provide an ID or paste a script containing the ID.';
        }
        if (script) {
            script.required = false;
            script.placeholder = isGtm
                ? 'Optional legacy full GTM snippet. Container ID-only setup is recommended.'
                : 'Optional: paste the official tracking script here';
        }
        if (scriptHelp) {
            scriptHelp.textContent = isGtm
                ? 'Optional for GTM. The website generates the official loader and noscript iframe from the Container ID.'
                : 'Optional for Meta, TikTok and Google Analytics. The platform ID can also be extracted from a supported script.';
        }
        if (gtmHelp) gtmHelp.hidden = !isGtm;
    }

    function refreshAll() {
        form.querySelectorAll('.sfa-pixel-row').forEach(refresh);

        if (container) {
            const rows = container.querySelectorAll('.sfa-pixel-row');
            rows.forEach(function (row, index) {
                const title = row.querySelector('.sfa-pixel-row-title');
                if (title) title.textContent = 'Tracking Integration ' + (index + 1);

                const remove = row.querySelector('.sfa-remove-pixel');
                if (remove) remove.disabled = rows.length <= 1;
            });
        }
    }

    form.addEventListener('change', function (event) {
        if (event.target.matches('[data-platform], [data-capi-switch]')) {
            const row = event.target.closest('.sfa-pixel-row');
            if (row) refresh(row);
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.matches('[data-script]')) {
            const row = event.target.closest('.sfa-pixel-row');
            if (row) refresh(row);
        }
    });

    form.addEventListener('click', function (event) {
        const remove = event.target.closest('.sfa-remove-pixel');
        if (!remove || !container) return;
        if (container.querySelectorAll('.sfa-pixel-row').length < 2) return;

        remove.closest('.sfa-pixel-row').remove();
        refreshAll();
    });

    document.getElementById('sfa-add-pixel')?.addEventListener('click', function () {
        if (!container || !template || container.querySelectorAll('.sfa-pixel-row').length >= 20) return;

        // Unique numeric form indices and checkbox IDs; empty token inputs never leak credentials.
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        container.insertAdjacentHTML('beforeend', html);
        refreshAll();
    });

    refreshAll();
})();
