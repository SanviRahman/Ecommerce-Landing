/** Meta, TikTok and GA4 bootstrap. Pixel IDs are server-provided JSON data, not JS source. */
(function (w, d) {
    'use strict';
    const element = d.getElementById('gtmPixelConfig') || d.getElementById('sfaPixelConfig');
    let settings = {};
    try {
        settings = element ? JSON.parse(element.getAttribute('content') || '{}') : {};
    } catch (error) {
        console.error('Invalid tracking pixel configuration.', error);
        return;
    }
    if (w.__GTM_PIXEL_BOOTSTRAP_READY__ || w.__SFA_PIXEL_BOOTSTRAP_READY__) return;
    w.__GTM_PIXEL_BOOTSTRAP_READY__ = true;
    w.__SFA_PIXEL_BOOTSTRAP_READY__ = true;
    const metaIds = Array.isArray(settings.meta)
        ? Array.from(new Set(settings.meta.map(String).filter(function (id) {
            return /^[0-9]{5,30}$/.test(id);
        }))) : [];
    const tiktokIds = Array.isArray(settings.tiktok) ? settings.tiktok.filter(Boolean) : [];
    const gaIds = Array.isArray(settings.ga) ? settings.ga.filter(Boolean) : [];
    w.dataLayer = w.dataLayer || [];
    w.GTM_META_PIXEL_IDS = metaIds;
    w.SFA_META_PIXEL_IDS = metaIds; // Compatibility with earlier frontend scripts.
    // Browser-only, non-sensitive state for debugging Pixel Helper discrepancies.
    // A queued fbq call does NOT prove that Meta's CDN accepted the request.
    w.GTM_TRACKING_DIAGNOSTICS = {
        pixel_ids: metaIds.slice(),
        network: metaIds.length ? 'pending' : 'not_configured',
        events_queued: 0,
        last_event: null,
        capi: {state: 'not_initialized', requests: 0, delivered: 0, failures: 0, recent: []}
    };
    // Backward compatibility for existing debugging and extensions.
    w.SFA_META_DIAGNOSTICS = w.GTM_TRACKING_DIAGNOSTICS;
    if (metaIds.length) {
        if (!w.fbq) {
            const fbq = function () {
                fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments);
            };
            if (!w._fbq) w._fbq = fbq;
            fbq.push = fbq;
            fbq.loaded = true;
            fbq.version = '2.0';
            fbq.queue = [];
            w.fbq = fbq;
            const script = d.createElement('script');
            script.async = true;
            script.src = 'https://connect.facebook.net/en_US/fbevents.js';
            script.onload = function () {
                w.SFA_META_DIAGNOSTICS.network = 'loaded';
            };
            script.onerror = function () {
                w.SFA_META_DIAGNOSTICS.network = 'blocked_or_unavailable';
                if (w.console && typeof w.console.warn === 'function') {
                    w.console.warn('[GTM Tracking Bridge] Meta Pixel script could not load. Check browser privacy/extensions and Network tab.');
                }
            };
            const first = d.getElementsByTagName('script')[0];
            if (first) first.parentNode.insertBefore(script, first);
            else d.head.appendChild(script);
        } else {
            // GTM or another integration already owns fbq. Never load fbevents twice.
            w.SFA_META_DIAGNOSTICS.network = 'externally_managed';
        }
        metaIds.forEach(function (pixelId) { w.fbq('init', pixelId); });
    }

    if (tiktokIds.length) {
        const name = 'ttq';
        const ttq = w[name] = w[name] || [];
        ttq.methods = [
            'page', 'track', 'identify', 'instances', 'debug', 'on', 'off',
            'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie'
        ];
        ttq.setAndDefer = function (target, method) {
            target[method] = function () {
                target.push([method].concat(Array.prototype.slice.call(arguments, 0)));
            };
        };
        for (let i = 0; i < ttq.methods.length; i++) {
            ttq.setAndDefer(ttq, ttq.methods[i]);
        }
        ttq.instance = function (pixelId) {
            ttq._i = ttq._i || {};
            const instance = ttq._i[pixelId] || [];
            for (let i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(instance, ttq.methods[i]);
            return instance;
        };
        ttq.load = function (pixelId, options) {
            const url = 'https://analytics.tiktok.com/i18n/pixel/events.js';
            ttq._i = ttq._i || {};
            ttq._i[pixelId] = [];
            ttq._i[pixelId]._u = url;
            ttq._t = ttq._t || {};
            ttq._t[pixelId] = +new Date();
            ttq._o = ttq._o || {};
            ttq._o[pixelId] = options || {};
            const script = d.createElement('script');
            script.type = 'text/javascript';
            script.async = true;
            script.src = url + '?sdkid=' + encodeURIComponent(pixelId) + '&lib=' + name;
            const first = d.getElementsByTagName('script')[0];
            if (first) first.parentNode.insertBefore(script, first);
            else d.head.appendChild(script);
        };
        tiktokIds.forEach(function (pixelId) { ttq.load(pixelId); });
    }

    if (gaIds.length) {
        w.gtag = function () { w.dataLayer.push(arguments); };
        w.gtag('js', new Date());
        gaIds.forEach(function (pixelId) {
            w.gtag('config', pixelId, { send_page_view: false });
        });
    }
})(window, document);
