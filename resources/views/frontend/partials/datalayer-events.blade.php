<script>
(function () {
    window.dataLayer = window.dataLayer || [];

    const DEBUG = {{ config('app.debug') ? 'true' : 'false' }};
    const DEFAULT_CURRENCY = 'BDT';

    function cleanNumber(value) {
        value = Number(value || 0);
        return isNaN(value) ? 0 : Number(value.toFixed(2));
    }

    function cleanString(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).trim();
    }

    function cleanItems(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items.map(function (item) {
            return {
                item_id: cleanString(item.item_id || item.id || item.product_id),
                item_name: cleanString(item.item_name || item.name || item.product_name),
                price: cleanNumber(item.price || item.item_price || 0),
                quantity: Number(item.quantity || item.qty || 1),
                item_category: item.item_category || item.category || undefined,
                item_brand: item.item_brand || item.brand || undefined
            };
        }).filter(function (item) {
            return item.item_id || item.item_name;
        });
    }

    function debugLog(eventName, payload) {
        if (DEBUG && window.console) {
            console.log('[SFA Tracking]', eventName, payload);
        }
    }

    function makeEventId(eventName, transactionId) {
        const base = transactionId || Date.now() + '_' + Math.random().toString(36).slice(2);
        return 'sfa_' + eventName + '_' + base;
    }

    function pushDataLayer(eventName, ecommerce, extra) {
        ecommerce = ecommerce || {};
        extra = extra || {};

        const payload = Object.assign({}, extra, {
            event: eventName,
            ecommerce: ecommerce
        });

        window.dataLayer.push(payload);

        debugLog(eventName, payload);

        return payload;
    }

    function metaPayloadFrom(eventName, ecommerce, extra) {
        ecommerce = ecommerce || {};
        extra = extra || {};

        const items = cleanItems(ecommerce.items || []);
        const value = cleanNumber(ecommerce.value || extra.value || 0);
        const currency = ecommerce.currency || extra.currency || DEFAULT_CURRENCY;

        const payload = {
            currency: currency,
            value: value,
            content_type: extra.content_type || 'product',
            content_ids: items.map(function (item) {
                return item.item_id;
            }).filter(Boolean),
            contents: items.map(function (item) {
                return {
                    id: item.item_id,
                    quantity: item.quantity,
                    item_price: item.price
                };
            })
        };

        if (extra.content_id && payload.content_ids.length === 0) {
            payload.content_ids = [cleanString(extra.content_id)];
        }

        if (extra.content_name) {
            payload.content_name = cleanString(extra.content_name);
        }

        if (ecommerce.transaction_id) {
            payload.order_id = cleanString(ecommerce.transaction_id);
        }

        return payload;
    }

    function sendMetaEvent(eventName, ecommerce, extra) {
        if (typeof window.fbq !== 'function') {
            debugLog('meta_not_loaded_' + eventName, ecommerce);
            return;
        }

        const eventMap = {
            page_view: 'PageView',
            view_content: 'ViewContent',
            add_to_cart: 'AddToCart',
            begin_checkout: 'InitiateCheckout',
            purchase: 'Purchase'
        };

        if (!eventMap[eventName]) {
            return;
        }

        ecommerce = ecommerce || {};
        extra = extra || {};

        const transactionId = ecommerce.transaction_id || null;
        const eventId = makeEventId(eventName, transactionId);

        if (eventName === 'page_view') {
            window.fbq('track', 'PageView', {
                page_type: extra.page_type || 'other',
                page_title: extra.page_title || document.title,
                page_url: extra.page_url || window.location.href
            }, {
                eventID: eventId
            });

            return;
        }

        window.fbq('track', eventMap[eventName], metaPayloadFrom(eventName, ecommerce, extra), {
            eventID: eventId
        });
    }

    function sendTikTokEvent(eventName, ecommerce, extra) {
        if (!window.ttq || typeof window.ttq.track !== 'function') {
            debugLog('tiktok_not_loaded_' + eventName, ecommerce);
            return;
        }

        const eventMap = {
            page_view: 'PageView',
            view_content: 'ViewContent',
            add_to_cart: 'AddToCart',
            begin_checkout: 'InitiateCheckout',
            purchase: 'CompletePayment'
        };

        if (!eventMap[eventName]) {
            return;
        }

        ecommerce = ecommerce || {};
        extra = extra || {};

        const items = cleanItems(ecommerce.items || []);

        const payload = {
            value: cleanNumber(ecommerce.value || extra.value || 0),
            currency: ecommerce.currency || extra.currency || DEFAULT_CURRENCY,
            content_type: extra.content_type || 'product',
            content_id: items.length ? items[0].item_id : (extra.content_id || undefined),
            content_name: items.length ? items[0].item_name : (extra.content_name || undefined),
            contents: items.map(function (item) {
                return {
                    content_id: item.item_id,
                    content_name: item.item_name,
                    quantity: item.quantity,
                    price: item.price
                };
            })
        };

        if (eventName === 'page_view' && typeof window.ttq.page === 'function') {
            window.ttq.page();
            return;
        }

        window.ttq.track(eventMap[eventName], payload);
    }

    function sendGA4Event(eventName, ecommerce, extra) {
        if (typeof window.gtag !== 'function') {
            return;
        }

        const gaEventMap = {
            page_view: 'page_view',
            view_content: 'view_item',
            add_to_cart: 'add_to_cart',
            begin_checkout: 'begin_checkout',
            purchase: 'purchase'
        };

        if (!gaEventMap[eventName]) {
            return;
        }

        ecommerce = ecommerce || {};
        extra = extra || {};

        const items = cleanItems(ecommerce.items || []);

        if (eventName === 'page_view') {
            window.gtag('event', 'page_view', {
                page_title: extra.page_title || document.title,
                page_location: extra.page_url || window.location.href,
                page_type: extra.page_type || 'other'
            });

            return;
        }

        window.gtag('event', gaEventMap[eventName], {
            transaction_id: ecommerce.transaction_id || undefined,
            affiliation: ecommerce.affiliation || undefined,
            value: cleanNumber(ecommerce.value || extra.value || 0),
            shipping: cleanNumber(ecommerce.shipping || 0),
            tax: cleanNumber(ecommerce.tax || 0),
            currency: ecommerce.currency || extra.currency || DEFAULT_CURRENCY,
            items: items
        });
    }

    function track(eventName, ecommerce, extra) {
        ecommerce = ecommerce || {};
        extra = extra || {};

        const payload = pushDataLayer(eventName, ecommerce, extra);

        sendMetaEvent(eventName, ecommerce, extra);
        sendTikTokEvent(eventName, ecommerce, extra);
        sendGA4Event(eventName, ecommerce, extra);

        return payload;
    }

    window.SFATracking = {
        track: track,

        pageView: function (data) {
            data = data || {};

            return track('page_view', {}, {
                page_type: data.page_type || 'other',
                page_title: data.page_title || document.title,
                page_url: data.page_url || window.location.href
            });
        },

        viewContent: function (data) {
            data = data || {};

            return track('view_content', {
                currency: data.currency || DEFAULT_CURRENCY,
                value: cleanNumber(data.value || 0),
                items: cleanItems(data.items || [])
            }, {
                content_type: data.content_type || 'product',
                content_id: data.content_id || null,
                content_name: data.content_name || null,
                page_url: data.page_url || window.location.href
            });
        },

        addToCart: function (item) {
            item = item || {};

            return track('add_to_cart', {
                currency: item.currency || DEFAULT_CURRENCY,
                value: cleanNumber((item.price || 0) * (item.quantity || 1)),
                items: cleanItems([{
                    item_id: item.item_id || item.id || item.product_id,
                    item_name: item.item_name || item.name || item.product_name,
                    price: item.price || 0,
                    quantity: item.quantity || 1,
                    item_category: item.item_category || item.category || undefined,
                    item_brand: item.item_brand || item.brand || undefined
                }])
            }, {
                content_type: 'product'
            });
        },

        beginCheckout: function (data) {
            data = data || {};

            return track('begin_checkout', {
                currency: data.currency || DEFAULT_CURRENCY,
                value: cleanNumber(data.value || 0),
                shipping: cleanNumber(data.shipping || 0),
                tax: cleanNumber(data.tax || 0),
                items: cleanItems(data.items || [])
            }, {
                content_type: 'product'
            });
        },

        purchase: function (data) {
            data = data || {};

            const transactionId = cleanString(data.transaction_id || data.order_id || '');

            if (transactionId) {
                const storageKey = 'sfa_purchase_' + transactionId;

                if (sessionStorage.getItem(storageKey)) {
                    debugLog('purchase_duplicate_skipped', data);
                    return null;
                }

                sessionStorage.setItem(storageKey, '1');
            }

            return track('purchase', {
                transaction_id: transactionId,
                affiliation: data.affiliation || @json(config('app.name') . ' Online Store'),
                currency: data.currency || DEFAULT_CURRENCY,
                value: cleanNumber(data.value || 0),
                shipping: cleanNumber(data.shipping || 0),
                tax: cleanNumber(data.tax || 0),
                items: cleanItems(data.items || [])
            }, {
                content_type: 'product'
            });
        }
    };

    window.SFATracking.pageView({
        page_type: @json(request()->is('/') ? 'home' : (request()->is('campaign/*') ? 'campaign' : (request()->is('success/*') ? 'success' : 'other'))),
        page_title: @json(trim($__env->yieldContent('title')) ?: config('app.name')),
        page_url: @json(url()->current())
    });
})();
</script>