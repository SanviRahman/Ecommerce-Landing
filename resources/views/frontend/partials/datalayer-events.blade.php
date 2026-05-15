<script>
(function () {
    window.dataLayer = window.dataLayer || [];

    const DEBUG = {{ config('app.debug') ? 'true' : 'false' }};

    function cleanNumber(value) {
        value = Number(value || 0);
        return isNaN(value) ? 0 : Number(value.toFixed(2));
    }

    function cleanItems(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items.map(function (item) {
            return {
                item_id: String(item.item_id || item.id || ''),
                item_name: String(item.item_name || item.name || ''),
                price: cleanNumber(item.price || 0),
                quantity: Number(item.quantity || 1),
                item_category: item.item_category || undefined,
                item_brand: item.item_brand || undefined
            };
        });
    }

    function debugLog(eventName, payload) {
        if (DEBUG && window.console) {
            console.log('[SFA DataLayer]', eventName, payload);
        }
    }

    function pushDataLayer(eventName, ecommerce, extra) {
        ecommerce = ecommerce || {};
        extra = extra || {};

        window.dataLayer.push({
            ecommerce: null
        });

        const payload = Object.assign({}, extra, {
            event: eventName,
            ecommerce: ecommerce
        });

        window.dataLayer.push(payload);
        debugLog(eventName, payload);

        return payload;
    }

    function sendMetaEvent(eventName, ecommerce, extra) {
        if (typeof window.fbq !== 'function') {
            return;
        }

        ecommerce = ecommerce || {};
        extra = extra || {};

        const items = cleanItems(ecommerce.items || []);
        const value = cleanNumber(ecommerce.value || extra.value || 0);
        const currency = ecommerce.currency || extra.currency || 'BDT';

        const metaPayload = {
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

        if (extra.content_name) {
            metaPayload.content_name = extra.content_name;
        }

        if (ecommerce.transaction_id) {
            metaPayload.order_id = ecommerce.transaction_id;
        }

        const eventMap = {
            page_view: 'PageView',
            view_content: 'ViewContent',
            add_to_cart: 'AddToCart',
            begin_checkout: 'InitiateCheckout',
            purchase: 'Purchase'
        };

        if (eventMap[eventName]) {
            window.fbq('track', eventMap[eventName], metaPayload);
        }
    }

    function sendTikTokEvent(eventName, ecommerce, extra) {
        if (!window.ttq || typeof window.ttq.track !== 'function') {
            return;
        }

        ecommerce = ecommerce || {};
        extra = extra || {};

        const items = cleanItems(ecommerce.items || []);

        const tikTokPayload = {
            value: cleanNumber(ecommerce.value || extra.value || 0),
            currency: ecommerce.currency || extra.currency || 'BDT',
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

        const eventMap = {
            page_view: 'PageView',
            view_content: 'ViewContent',
            add_to_cart: 'AddToCart',
            begin_checkout: 'InitiateCheckout',
            purchase: 'CompletePayment'
        };

        if (eventMap[eventName]) {
            window.ttq.track(eventMap[eventName], tikTokPayload);
        }
    }

    function track(eventName, ecommerce, extra) {
        const payload = pushDataLayer(eventName, ecommerce, extra);

        sendMetaEvent(eventName, ecommerce, extra);
        sendTikTokEvent(eventName, ecommerce, extra);

        return payload;
    }

    window.SFATracking = {
        pageView: function (data) {
            data = data || {};

            track('page_view', {}, {
                page_type: data.page_type || 'other',
                page_title: data.page_title || document.title,
                page_url: data.page_url || window.location.href
            });
        },

        viewContent: function (data) {
            data = data || {};

            track('view_content', {
                currency: data.currency || 'BDT',
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

            track('add_to_cart', {
                currency: item.currency || 'BDT',
                value: cleanNumber((item.price || 0) * (item.quantity || 1)),
                items: cleanItems([
                    {
                        item_id: item.item_id || item.id,
                        item_name: item.item_name || item.name,
                        price: item.price || 0,
                        quantity: item.quantity || 1,
                        item_category: item.item_category || undefined,
                        item_brand: item.item_brand || undefined
                    }
                ])
            }, {});
        },

        beginCheckout: function (data) {
            data = data || {};

            track('begin_checkout', {
                currency: data.currency || 'BDT',
                value: cleanNumber(data.value || 0),
                shipping: cleanNumber(data.shipping || 0),
                tax: cleanNumber(data.tax || 0),
                items: cleanItems(data.items || [])
            }, {});
        },

        purchase: function (data) {
            data = data || {};

            const transactionId = String(data.transaction_id || '');

            if (transactionId) {
                const storageKey = 'sfa_purchase_' + transactionId;

                if (sessionStorage.getItem(storageKey)) {
                    debugLog('purchase_duplicate_skipped', data);
                    return;
                }

                sessionStorage.setItem(storageKey, '1');
            }

            track('purchase', {
                transaction_id: transactionId,
                affiliation: data.affiliation || '{{ config('app.name') }} Online Store',
                currency: data.currency || 'BDT',
                value: cleanNumber(data.value || 0),
                shipping: cleanNumber(data.shipping || 0),
                tax: cleanNumber(data.tax || 0),
                items: cleanItems(data.items || [])
            }, {});
        }
    };

    window.SFATracking.pageView({
        page_type: @json(request()->is('/') ? 'home' : (request()->is('campaign/*') ? 'campaign' : (request()->is('success/*') ? 'success' : 'other'))),
        page_title: @json(trim($__env->yieldContent('title')) ?: config('app.name')),
        page_url: @json(url()->current())
    });
})();
</script>