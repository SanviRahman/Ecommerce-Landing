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

        return items.map(function (item, index) {
            const itemId = cleanString(
                item.item_id ||
                item.id ||
                item.product_id ||
                item.sku ||
                item.code ||
                ('item_' + (index + 1))
            );

            const itemName = cleanString(
                item.item_name ||
                item.name ||
                item.product_name ||
                ('Product ' + (index + 1))
            );

            const price = cleanNumber(item.price || item.item_price || item.unit_price || 0);
            const quantity = Number(item.quantity || item.qty || 1);

            return {
                item_id: itemId,
                item_name: itemName,
                price: price,
                quantity: quantity > 0 ? quantity : 1,
                item_category: cleanString(item.item_category || item.category || ''),
                item_brand: cleanString(item.item_brand || item.brand || '')
            };
        }).filter(function (item) {
            return item.item_id && item.item_name;
        });
    }

    function debugLog(eventName, payload) {
        if (DEBUG && window.console) {
            console.log('[SFA Tracking]', eventName, payload);
        }
    }

    function currentUrl() {
        return window.location.href;
    }

    function makeEventId(eventName, transactionId) {
        const safeName = cleanString(eventName).replace(/[^a-zA-Z0-9_-]/g, '_');
        const safeTransaction = cleanString(transactionId).replace(/[^a-zA-Z0-9_-]/g, '_');

        if (safeTransaction) {
            return 'sfa_' + safeName + '_' + safeTransaction;
        }

        return 'sfa_' + safeName + '_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
    }

    function totalQuantity(items) {
        return cleanItems(items).reduce(function (total, item) {
            return total + Number(item.quantity || 1);
        }, 0);
    }

    function totalValue(items) {
        return cleanItems(items).reduce(function (total, item) {
            return total + (cleanNumber(item.price) * Number(item.quantity || 1));
        }, 0);
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
        const calculatedValue = totalValue(items);
        const value = cleanNumber(ecommerce.value || extra.value || calculatedValue || 0);
        const currency = ecommerce.currency || extra.currency || DEFAULT_CURRENCY;

        const contentIds = items.map(function (item) {
            return cleanString(item.item_id);
        }).filter(Boolean);

        const contents = items.map(function (item) {
            return {
                id: cleanString(item.item_id),
                quantity: Number(item.quantity || 1),
                item_price: cleanNumber(item.price || 0)
            };
        });

        const firstItem = items.length ? items[0] : null;

        const payload = {
            value: value,
            currency: currency,
            content_type: extra.content_type || 'product',
            content_ids: contentIds,
            contents: contents,
            num_items: totalQuantity(items),
            content_name: cleanString(extra.content_name || (firstItem ? firstItem.item_name : 'Product')),
            content_category: cleanString(extra.content_category || (firstItem ? firstItem.item_category : '')),
            event_source_url: extra.page_url || currentUrl()
        };

        if (!payload.content_ids.length && extra.content_id) {
            payload.content_ids = [cleanString(extra.content_id)];
        }

        if (!payload.contents.length && extra.content_id) {
            payload.contents = [{
                id: cleanString(extra.content_id),
                quantity: 1,
                item_price: value
            }];

            payload.num_items = 1;
        }

        if (ecommerce.transaction_id) {
            payload.order_id = cleanString(ecommerce.transaction_id);
        }

        return payload;
    }

    function sendMetaTrack(eventName, payload, options) {
        if (typeof window.fbq !== 'function') {
            debugLog('meta_not_loaded_' + eventName, payload);
            return;
        }

        const pixelIds = Array.isArray(window.SFA_META_PIXEL_IDS)
            ? window.SFA_META_PIXEL_IDS.filter(Boolean)
            : [];

        if (pixelIds.length) {
            pixelIds.forEach(function (pixelId) {
                window.fbq('trackSingle', String(pixelId), eventName, payload || {}, options || {});
            });

            return;
        }

        window.fbq('track', eventName, payload || {}, options || {});
    }

    function sendMetaEvent(eventName, ecommerce, extra) {
        ecommerce = ecommerce || {};
        extra = extra || {};

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

        const transactionId = ecommerce.transaction_id || null;
        const eventId = makeEventId(eventName, transactionId);

        if (eventName === 'page_view') {
            sendMetaTrack('PageView', {
                page_type: extra.page_type || 'other',
                page_title: extra.page_title || document.title,
                event_source_url: extra.page_url || currentUrl()
            }, {
                eventID: eventId
            });

            return;
        }

        sendMetaTrack(eventMap[eventName], metaPayloadFrom(eventName, ecommerce, extra), {
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
        const firstItem = items.length ? items[0] : null;

        const payload = {
            value: cleanNumber(ecommerce.value || extra.value || totalValue(items) || 0),
            currency: ecommerce.currency || extra.currency || DEFAULT_CURRENCY,
            content_type: extra.content_type || 'product',
            content_id: firstItem ? firstItem.item_id : (extra.content_id || undefined),
            content_name: firstItem ? firstItem.item_name : (extra.content_name || undefined),
            quantity: totalQuantity(items),
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
                page_location: extra.page_url || currentUrl(),
                page_type: extra.page_type || 'other'
            });

            return;
        }

        window.gtag('event', gaEventMap[eventName], {
            transaction_id: ecommerce.transaction_id || undefined,
            affiliation: ecommerce.affiliation || undefined,
            value: cleanNumber(ecommerce.value || extra.value || totalValue(items) || 0),
            shipping: cleanNumber(ecommerce.shipping || 0),
            tax: cleanNumber(ecommerce.tax || 0),
            currency: ecommerce.currency || extra.currency || DEFAULT_CURRENCY,
            items: items.map(function (item) {
                return {
                    item_id: item.item_id,
                    item_name: item.item_name,
                    price: item.price,
                    quantity: item.quantity,
                    item_category: item.item_category || undefined,
                    item_brand: item.item_brand || undefined
                };
            })
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
                page_url: data.page_url || currentUrl()
            });
        },

        viewContent: function (data) {
            data = data || {};

            const items = cleanItems(data.items || []);
            const value = cleanNumber(data.value || totalValue(items) || 0);

            return track('view_content', {
                currency: data.currency || DEFAULT_CURRENCY,
                value: value,
                items: items
            }, {
                content_type: data.content_type || 'product',
                content_id: data.content_id || (items[0] ? items[0].item_id : null),
                content_name: data.content_name || (items[0] ? items[0].item_name : null),
                content_category: data.content_category || (items[0] ? items[0].item_category : null),
                page_url: data.page_url || currentUrl()
            });
        },

        addToCart: function (item) {
            item = item || {};

            const items = cleanItems([{
                item_id: item.item_id || item.id || item.product_id,
                item_name: item.item_name || item.name || item.product_name,
                price: item.price || 0,
                quantity: item.quantity || 1,
                item_category: item.item_category || item.category || undefined,
                item_brand: item.item_brand || item.brand || undefined
            }]);

            return track('add_to_cart', {
                currency: item.currency || DEFAULT_CURRENCY,
                value: cleanNumber(totalValue(items)),
                items: items
            }, {
                content_type: 'product',
                content_id: items[0] ? items[0].item_id : null,
                content_name: items[0] ? items[0].item_name : null,
                content_category: items[0] ? items[0].item_category : null,
                page_url: currentUrl()
            });
        },

        beginCheckout: function (data) {
            data = data || {};

            const items = cleanItems(data.items || []);
            const value = cleanNumber(data.value || totalValue(items) || 0);

            return track('begin_checkout', {
                currency: data.currency || DEFAULT_CURRENCY,
                value: value,
                shipping: cleanNumber(data.shipping || 0),
                tax: cleanNumber(data.tax || 0),
                items: items
            }, {
                content_type: 'product',
                content_id: items[0] ? items[0].item_id : null,
                content_name: items[0] ? items[0].item_name : null,
                content_category: items[0] ? items[0].item_category : null,
                page_url: currentUrl()
            });
        },

        purchase: function (data) {
            data = data || {};

            const transactionId = cleanString(data.transaction_id || data.order_id || '');
            const items = cleanItems(data.items || []);
            const value = cleanNumber(data.value || totalValue(items) || 0);

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
                value: value,
                shipping: cleanNumber(data.shipping || 0),
                tax: cleanNumber(data.tax || 0),
                items: items
            }, {
                content_type: 'product',
                content_id: items[0] ? items[0].item_id : null,
                content_name: items[0] ? items[0].item_name : null,
                content_category: items[0] ? items[0].item_category : null,
                page_url: currentUrl()
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