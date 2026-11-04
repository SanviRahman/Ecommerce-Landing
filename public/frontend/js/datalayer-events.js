(function () {
    const settings = document.getElementById('gtmTrackingConfig') || document.getElementById('sfaTrackingConfig');
    let config = {};
    try {
        config = settings ? JSON.parse(settings.getAttribute('data-config') || '{}') : {};
    } catch (error) {
        console.error('Invalid event tracking configuration.', error);
        return;
    }
    window.dataLayer = window.dataLayer || [];
    // Prevent a duplicated partial/script from firing PageView and conversions twice.
    if (window.__GTM_TRACKING_BRIDGE_READY__ || window.__SFA_TRACKING_BRIDGE_READY__) return;
    window.__GTM_TRACKING_BRIDGE_READY__ = true;
    window.__SFA_TRACKING_BRIDGE_READY__ = true;

    const META_EVENT_NAMES = {
        page_view: 'PageView', view_content: 'ViewContent',
        add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase'
    };
    let checkoutTracked = false;
    const purchasesTracked = new Set();

    const DEBUG = Boolean(config.debug);
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
            console.log('[GTM Tracking Bridge]', eventName, payload);
        }
    }

    function currentUrl() {
        return window.location.href;
    }

    function makeEventId(eventName, transactionId) {
        const safeName = cleanString(eventName).replace(/[^a-zA-Z0-9_-]/g, '_');
        const safeTransaction = cleanString(transactionId).replace(/[^a-zA-Z0-9_-]/g, '_');

        if (safeTransaction) {
            // Keep the established Purchase ID for previously placed orders so that a
            // success-page revisit cannot create a new deduplication identity.
            return (safeName === 'purchase' ? 'sfa_' : 'gtm_') + safeName + '_' + safeTransaction;
        }

        return 'gtm_' + safeName + '_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
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
        const configured = window.GTM_META_PIXEL_IDS || window.SFA_META_PIXEL_IDS;
        const pixelIds = Array.isArray(configured) ? configured.filter(Boolean) : [];
        // An external GTM tag may own fbq, but an absent admin Meta integration
        // must never trigger that unrelated Pixel a second time.
        if (!pixelIds.length) return;
        if (typeof window.fbq !== 'function') {
            debugLog('meta_not_loaded_' + eventName, payload);
            return;
        }
        const diagnostics = window.GTM_TRACKING_DIAGNOSTICS || window.SFA_META_DIAGNOSTICS;
        if (diagnostics) {
            diagnostics.events_queued += 1;
            diagnostics.last_event = {name: eventName, event_id: options && options.eventID};
        }

        pixelIds.forEach(function (pixelId) {
            window.fbq('trackSingle', String(pixelId), eventName, payload || {}, options || {});
        });
    }

    // The browser's eventID and CAPI's event_id MUST be the same for each action.
    // Purchase is sent from the actual order on the server, never from untrusted JS data.
    function sendConversionsEvent(eventName, eventId, payload) {
        // Purchase is dispatched using the saved order by the Laravel service.
        if (!config.capiUrl || eventName === 'Purchase') return;
        const diagnostics = window.GTM_TRACKING_DIAGNOSTICS;
        const capi = diagnostics && diagnostics.capi;
        if (typeof window.fetch !== 'function') {
            if (capi) { capi.state = 'fetch_unavailable'; capi.failures++; }
            return;
        }
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf || !csrf.content) {
            if (capi) { capi.state = 'csrf_missing'; capi.failures++; }
            return;
        }
        const requestStatus = {name: eventName, event_id: eventId, status: 'pending'};
        if (capi) {
            capi.state = 'sending';
            capi.requests++;
            capi.recent.push(requestStatus);
            if (capi.recent.length > 20) capi.recent.shift();
        }
        const customData = {};
        ['value', 'currency', 'content_type', 'content_ids', 'contents', 'num_items'].forEach(function (key) {
            if (payload && payload[key] !== undefined) customData[key] = payload[key];
        });
        window.fetch(config.capiUrl, {
            method: 'POST', credentials: 'same-origin', keepalive: true,
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.content,
                'Accept': 'application/json'},
            body: JSON.stringify({event_name: eventName, event_id: eventId,
                custom_data: customData})
        }).then(function (response) {
            requestStatus.http_status = response.status;
            if (!response.ok) {
                requestStatus.status = 'http_error';
                if (capi) { capi.state = 'http_error'; capi.failures++; }
                debugLog('capi_http_error_' + eventName, {status: response.status});
                return;
            }
            // Laravel accepted the request; Meta's response decides actual delivery.
            return response.json().then(function (result) {
                const received = Number(result && result.server_events_delivered || 0);
                requestStatus.status = received > 0 ? 'meta_accepted' : 'not_delivered';
                requestStatus.delivered_to = received;
                if (capi) {
                    capi.state = requestStatus.status;
                    if (received > 0) capi.delivered += received;
                    else capi.failures++;
                }
                if (!received) debugLog('capi_delivery_unconfirmed_' + eventName, {event_id: eventId});
            });
        }).catch(function () {
            requestStatus.status = 'network_error';
            if (capi) { capi.state = 'network_error'; capi.failures++; }
            debugLog('capi_unavailable_' + eventName, {event_id: eventId});
        });
    }

    function sendMetaEvent(eventName, ecommerce, extra, eventId) {
        ecommerce = ecommerce || {};
        extra = extra || {};

        if (!META_EVENT_NAMES[eventName]) return;

        if (eventName === 'page_view') {
            const pagePayload = {
                page_type: extra.page_type || 'other',
                page_title: extra.page_title || document.title,
                event_source_url: extra.page_url || currentUrl()
            };
            sendMetaTrack('PageView', pagePayload, {eventID: eventId});
            sendConversionsEvent('PageView', eventId, {});
            return;
        }

        const payload = metaPayloadFrom(eventName, ecommerce, extra);
        sendMetaTrack(META_EVENT_NAMES[eventName], payload, {eventID: eventId});
        sendConversionsEvent(META_EVENT_NAMES[eventName], eventId, payload);
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

        // Generate ONCE and reuse in dataLayer, browser Pixel and server CAPI.
        const eventId = META_EVENT_NAMES[eventName]
            ? makeEventId(eventName, ecommerce.transaction_id || null)
            : null;
        const dataLayerExtras = eventId ? Object.assign({}, extra, {
            event_id: eventId, meta_event_name: META_EVENT_NAMES[eventName]
        }) : extra;
        const payload = pushDataLayer(eventName, ecommerce, dataLayerExtras);

        sendMetaEvent(eventName, ecommerce, extra, eventId);
        sendTikTokEvent(eventName, ecommerce, extra);
        sendGA4Event(eventName, ecommerce, extra);

        return payload;
    }

    window.GTMTracking = {
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
            // Campaign order anchor and form submit can both invoke this action.
            if (checkoutTracked) return null;

            const items = cleanItems(data.items || []);
            if (!items.length) return null;
            checkoutTracked = true;
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
                if (purchasesTracked.has(transactionId)) return null;
                try {
                    // Storage may be denied by browser privacy settings: do not stop conversion.
                    if (window.sessionStorage.getItem(storageKey)) return null;
                    window.sessionStorage.setItem(storageKey, '1');
                } catch (error) {
                    debugLog('purchase_storage_unavailable', {});
                }
                purchasesTracked.add(transactionId);
            }

            return track('purchase', {
                transaction_id: transactionId,
                affiliation: data.affiliation || config.affiliation,
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

    // Existing campaign, checkout and storefront JS still calls SFATracking.
    // Preserve that API while exposing the preferred GTMTracking name.
    window.SFATracking = window.GTMTracking;
    const diagnostics = window.GTM_TRACKING_DIAGNOSTICS;
    if (diagnostics && diagnostics.capi) {
        diagnostics.capi.state = config.capiUrl ? 'ready' : 'disabled';
        diagnostics.capi.local_test_mode = Boolean(config.localTestMode);
    }

    window.GTMTracking.pageView({
        page_type: config.pageType,
        page_title: config.pageTitle,
        page_url: config.pageUrl
    });

    // GTM's own container is responsible for gtm.js, gtm.dom and gtm.load.
    // No synthetic lifecycle events: only genuine ecommerce interactions
    // are pushed by the first-party tracking bridge.
})();
