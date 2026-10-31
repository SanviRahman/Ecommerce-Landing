/** Image fallback code used by all public-facing Blade pages. */
(function () {
    'use strict';
    document.addEventListener('error', function (event) {
        const image = event.target;
        if (!(image instanceof HTMLImageElement)) return;
        if (image.dataset.hideOnError === '1') {
            image.hidden = true;
            return;
        }
        const fallback = image.dataset.imageFallback;
        if (!fallback || image.dataset.fallbackApplied === '1') return;
        image.dataset.fallbackApplied = '1';
        if (image.getAttribute('src') !== fallback) image.src = fallback;
    }, true);
})();
