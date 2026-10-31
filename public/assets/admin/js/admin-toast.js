(function (window, document) {
    'use strict';

    if (window.AdminToast && typeof window.AdminToast.show === 'function') {
        return;
    }

    var allowedTypes = {
        success: 'success',
        error: 'danger',
        danger: 'danger',
        warning: 'warning',
        info: 'info'
    };

    function getContainer() {
        var container = document.querySelector('[data-admin-toast-container]');

        if (container) {
            return container;
        }

        container = document.createElement('div');
        container.setAttribute('data-admin-toast-container', '');
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'false');
        container.style.position = 'fixed';
        container.style.top = '1rem';
        container.style.right = '1rem';
        container.style.zIndex = '2000';
        container.style.width = 'min(380px, calc(100vw - 2rem))';
        document.body.appendChild(container);

        return container;
    }

    function show(type, message, options) {
        var settings = options || {};
        var variant = allowedTypes[String(type || 'info').toLowerCase()] || 'info';
        var toast = document.createElement('div');
        var close = document.createElement('button');
        var text = document.createElement('span');
        var duration = Number.isFinite(Number(settings.duration))
            ? Math.max(0, Number(settings.duration))
            : 4500;

        toast.className = 'alert alert-' + variant + ' alert-dismissible shadow-sm mb-2';
        toast.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
        toast.style.display = 'flex';
        toast.style.alignItems = 'flex-start';
        toast.style.justifyContent = 'space-between';
        toast.style.gap = '.75rem';

        text.textContent = String(message == null ? '' : message);
        close.type = 'button';
        close.className = 'close';
        close.setAttribute('aria-label', 'Close notification');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () {
            toast.remove();
        });

        toast.appendChild(text);
        toast.appendChild(close);
        getContainer().appendChild(toast);

        if (duration > 0) {
            window.setTimeout(function () {
                if (toast.isConnected) {
                    toast.remove();
                }
            }, duration);
        }

        return toast;
    }

    window.AdminToast = Object.freeze({ show: show });
})(window, document);
