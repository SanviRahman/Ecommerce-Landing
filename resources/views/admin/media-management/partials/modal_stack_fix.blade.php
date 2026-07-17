{{--
|--------------------------------------------------------------------------
| Media Library "Move to Trash" Popup Force Top Fix
|--------------------------------------------------------------------------
| Purpose:
| - Media Library modal open থাকা অবস্থায় existing image delete/trash করলে
|   "Move media to trash?" popup যেন Media Library popup-এর উপরে আসে।
| - SweetAlert2 যদি modal-এর নিচে আটকে যায়, সেটাকে bypass করে body-level
|   custom confirm modal দেখানো হবে।
| - Existing backend/controller/model unchanged.
*/ --}}

<style>
    .media-trash-top-backdrop {
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483640 !important;
        background: rgba(0, 0, 0, .62) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 16px !important;
    }

    .media-trash-top-box {
        width: min(500px, 100%) !important;
        background: #fff !important;
        border-radius: 8px !important;
        box-shadow: 0 18px 65px rgba(0, 0, 0, .42) !important;
        padding: 34px 28px 24px !important;
        text-align: center !important;
        position: relative !important;
        z-index: 2147483641 !important;
        font-family: inherit !important;
        animation: mediaTrashTopPop .12s ease-out !important;
    }

    @keyframes mediaTrashTopPop {
        from { transform: scale(.97); opacity: .4; }
        to { transform: scale(1); opacity: 1; }
    }

    .media-trash-top-close {
        position: absolute !important;
        right: 12px !important;
        top: 8px !important;
        border: 0 !important;
        background: transparent !important;
        color: #999 !important;
        font-size: 28px !important;
        line-height: 1 !important;
        cursor: pointer !important;
    }

    .media-trash-top-icon {
        width: 82px !important;
        height: 82px !important;
        border: 4px solid #f6bd7c !important;
        border-radius: 50% !important;
        color: #f6a623 !important;
        font-size: 50px !important;
        line-height: 72px !important;
        margin: 0 auto 18px !important;
        font-weight: 700 !important;
    }

    .media-trash-top-title {
        font-size: 28px !important;
        font-weight: 600 !important;
        color: #545454 !important;
        margin-bottom: 12px !important;
    }

    .media-trash-top-text {
        color: #6c757d !important;
        font-size: 16px !important;
        line-height: 1.45 !important;
        margin-bottom: 24px !important;
    }

    .media-trash-top-actions {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 10px !important;
        flex-wrap: wrap !important;
    }

    .media-trash-top-actions .btn {
        min-width: 112px !important;
    }

    body > .swal2-container,
    .swal2-container,
    .swal2-popup {
        z-index: 2147483630 !important;
    }
</style>

<script>
(function () {
    'use strict';

    let swalFirePatched = false;

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function titleFromSwalArgs(args) {
        if (!args || !args.length) return '';
        if (typeof args[0] === 'string') return args[0];
        if (args[0] && typeof args[0] === 'object') {
            const title = args[0].title || args[0].titleText || '';
            if (typeof title === 'string') return title;
            if (title && title.textContent) return title.textContent;
        }
        return '';
    }

    function textFromSwalArgs(args) {
        if (!args || !args.length) return '';
        if (args[0] && typeof args[0] === 'object') return args[0].html || args[0].text || '';
        return args[1] || '';
    }

    function isMediaTrashTitle(title) {
        title = String(title || '').toLowerCase();
        return title.includes('move media to trash') || title.includes('move selected media to trash');
    }

    function removeOldPopup() {
        document.querySelectorAll('.media-trash-top-backdrop').forEach(function (el) { el.remove(); });
    }

    function showTopConfirm(options) {
        options = options || {};
        removeOldPopup();

        const title = options.title || 'Move media to trash?';
        const html = options.html || options.text || 'The file will be removed from the active media browser.<br>Existing saved sections may still reference it until replaced.';
        const confirmText = options.confirmButtonText || 'Yes, Continue';
        const cancelText = options.cancelButtonText || 'Cancel';

        const backdrop = document.createElement('div');
        backdrop.className = 'media-trash-top-backdrop';
        backdrop.innerHTML = `
            <div class="media-trash-top-box" role="dialog" aria-modal="true">
                <button type="button" class="media-trash-top-close" aria-label="Close">&times;</button>
                <div class="media-trash-top-icon">!</div>
                <div class="media-trash-top-title">${title}</div>
                <div class="media-trash-top-text">${html}</div>
                <div class="media-trash-top-actions">
                    <button type="button" class="btn btn-danger media-trash-top-confirm">${confirmText}</button>
                    <button type="button" class="btn btn-secondary media-trash-top-cancel">${cancelText}</button>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);

        const confirmButton = backdrop.querySelector('.media-trash-top-confirm');
        const cancelButton = backdrop.querySelector('.media-trash-top-cancel');
        const closeButton = backdrop.querySelector('.media-trash-top-close');

        return new Promise(function (resolve) {
            function cancel() {
                backdrop.remove();
                resolve({ isConfirmed: false, value: false, isDismissed: true, dismiss: 'cancel' });
            }

            function confirm() {
                backdrop.remove();
                resolve({ isConfirmed: true, value: true });
            }

            cancelButton.addEventListener('click', cancel);
            closeButton.addEventListener('click', cancel);
            confirmButton.addEventListener('click', confirm);

            backdrop.addEventListener('click', function (event) {
                if (event.target === backdrop && options.allowOutsideClick !== false) cancel();
            });

            document.addEventListener('keydown', function escHandler(event) {
                if (event.key === 'Escape') {
                    document.removeEventListener('keydown', escHandler);
                    cancel();
                }
            });
        });
    }

    function patchSweetAlertFire() {
        if (!window.Swal || !window.Swal.fire || swalFirePatched || window.Swal.__mediaTrashTopPatch) return;

        const originalFire = window.Swal.fire.bind(window.Swal);

        window.Swal.fire = function () {
            const args = Array.prototype.slice.call(arguments);
            const title = titleFromSwalArgs(args);

            if (isMediaTrashTitle(title)) {
                const options = args[0] && typeof args[0] === 'object'
                    ? Object.assign({}, args[0])
                    : { title: title, text: textFromSwalArgs(args), icon: args[2] || 'warning' };

                return showTopConfirm({
                    title: options.title || 'Move media to trash?',
                    html: options.html || options.text || 'The file will be removed from the active media browser.<br>Existing saved sections may still reference it until replaced.',
                    confirmButtonText: options.confirmButtonText || 'Yes, Continue',
                    cancelButtonText: options.cancelButtonText || 'Cancel',
                    allowOutsideClick: options.allowOutsideClick
                });
            }

            if (args.length === 1 && args[0] && typeof args[0] === 'object') {
                args[0] = Object.assign({ target: document.body, heightAuto: false }, args[0]);
            }

            const result = originalFire.apply(window.Swal, args);
            setTimeout(function () {
                document.querySelectorAll('.swal2-container, .swal2-popup').forEach(function (el) {
                    el.style.setProperty('z-index', '2147483630', 'important');
                });
            }, 0);
            return result;
        };

        window.Swal.__mediaTrashTopPatch = true;
        swalFirePatched = true;
    }

    function findDeleteButton(target) {
        const button = target.closest('button, a, [role="button"]');
        if (!button) return null;

        const hasTrashIcon = !!button.querySelector('.fa-trash, .fa-trash-alt, .fa-trash-can, .fas.fa-trash, .fas.fa-trash-alt');
        const explicitDelete = button.matches('[data-delete-url], [data-action="delete"], [data-action="delete-media"], .btn-media-delete, .btn-delete-media, .btn-media-browser-delete, .js-media-delete, .js-media-browser-delete, .media-delete, .media-browser-delete, .delete-media-item');

        if (!hasTrashIcon && !explicitDelete) return null;
        return button;
    }

    function findDeleteUrl(button) {
        if (!button) return '';

        let url = button.getAttribute('data-delete-url') || button.dataset.deleteUrl || button.getAttribute('data-url') || button.dataset.url || '';
        if (url) return url;

        const parentWithUrl = button.closest('[data-delete-url], [data-url], [href]');
        if (parentWithUrl) {
            url = parentWithUrl.getAttribute('data-delete-url') || parentWithUrl.dataset.deleteUrl || parentWithUrl.getAttribute('data-url') || parentWithUrl.dataset.url || parentWithUrl.getAttribute('href') || '';
            if (url && url !== '#' && !String(url).startsWith('javascript:')) return url;
        }

        const href = button.getAttribute('href');
        if (href && href !== '#' && !String(href).startsWith('javascript:')) return href;
        return '';
    }

    function isMediaManagementDeleteUrl(url) {
        return /media-management/i.test(String(url || ''));
    }

    function refreshAfterDelete(button) {
        const refreshButton = document.querySelector('#mediaLibraryRefresh, #mediaBrowserRefresh, .js-media-refresh, .media-library-refresh, [data-media-refresh]');
        if (refreshButton) {
            refreshButton.click();
            return;
        }

        const card = button.closest('.media-browser-item, .media-library-item, .media-card, .card, .col, [data-media-id]');
        if (card) card.remove();

        document.dispatchEvent(new CustomEvent('media-library:item-deleted'));
    }

    function ajaxDelete(url, button) {
        if (window.jQuery) {
            window.jQuery.ajax({
                url: url,
                type: 'POST',
                data: { _method: 'DELETE', _token: csrfToken() },
                success: function (response) {
                    if (response && response.status === false) {
                        alert(response.message || 'Media delete failed.');
                        return;
                    }
                    refreshAfterDelete(button);
                },
                error: function (xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Media delete failed.';
                    alert(message);
                }
            });
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ _method: 'DELETE', _token: csrfToken() })
        }).then(function (response) {
            return response.json();
        }).then(function (response) {
            if (response && response.status === false) {
                alert(response.message || 'Media delete failed.');
                return;
            }
            refreshAfterDelete(button);
        }).catch(function () {
            alert('Media delete failed.');
        });
    }

    document.addEventListener('click', function (event) {
        patchSweetAlertFire();

        const button = findDeleteButton(event.target);
        if (!button) return;

        const url = findDeleteUrl(button);

        if (!url || !isMediaManagementDeleteUrl(url)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();

        showTopConfirm({
            title: 'Move media to trash?',
            html: 'The file will be removed from the active media browser.<br>Existing saved sections may still reference it until replaced.',
            confirmButtonText: 'Yes, Continue',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result && (result.isConfirmed || result.value)) ajaxDelete(url, button);
        });
    }, true);

    if (window.MutationObserver && document.body) {
        const observer = new MutationObserver(function () { patchSweetAlertFire(); });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    document.addEventListener('DOMContentLoaded', patchSweetAlertFire);

    let attempts = 0;
    const timer = setInterval(function () {
        attempts++;
        patchSweetAlertFire();
        if (swalFirePatched || attempts >= 40) clearInterval(timer);
    }, 250);

    window.MediaTrashTopConfirm = showTopConfirm;
})();
</script>
