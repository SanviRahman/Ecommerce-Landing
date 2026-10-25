/** Fallback UX for authenticated product create/edit direct URLs, not AJAX modals. */
(() => {
    'use strict';

    function init() {
        const page = document.querySelector('.js-product-standalone');
        if (!page) return;

        const form = page.querySelector('#productForm');
        const returnUrl = page.dataset.returnUrl;
        const csrfToken = page.dataset.csrfToken;
        if (!form || !returnUrl || !csrfToken) return;

        // On standalone pages Bootstrap's modal dismissal has nothing to close.
        const cancel = form.querySelector('[data-dismiss="modal"]');
        cancel?.addEventListener('click', () => {
            window.location.assign(returnUrl);
        });

        form.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const button = target.closest('.btnDeleteProductMedia');
            if (!button || !form.contains(button) || button.disabled) return;
            event.preventDefault();
            if (!window.confirm('Delete this gallery image? This cannot be undone.')) return;

            // A server-generated data-url from the shared Blade view. No user-supplied URL.
            const url = button.dataset.url;
            const id = button.dataset.id;
            if (!url || !id) return;
            button.disabled = true;
            try {
                const response = await fetch(url, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                const result = await response.json();
                if (!response.ok || result.status !== true) {
                    throw new Error('Could not delete the image.');
                }
                document.getElementById('product-media-box-' + id)?.remove();
            } catch {
                button.disabled = false;
                window.alert('Image delete failed. Please retry or reload.');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
