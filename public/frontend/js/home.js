$(document).ready(function() {
    const configElement = document.getElementById('sfaHomeConfig');
    if (!configElement) return;
    let config = {};
    try {
        config = JSON.parse(configElement.getAttribute('data-config') || '{}');
    } catch (error) {
        console.error('Invalid campaign frontend configuration.', error);
        return;
    }

    const noImage = config.noImage || '/frontend/images/no-image.svg';
    const heroVideoAutoplayEnabled = Boolean(config.heroVideoAutoplay);
    const heroVideoMutedEnabled = Boolean(config.heroVideoMuted);

    /*
     * Uploaded/direct file autoplay compatibility.
     * Browsers block autoplay with sound. First try the admin-selected state;
     * if that is blocked, retry muted so autoplay still works. When Muted is
     * OFF, sound is restored on the visitor's first page interaction.
     */
    function initializeHeroVideoAutoplay() {
        const video = document.getElementById('campaignHeroVideo');

        if (!video) {
            return;
        }

        const shouldAutoplay = Boolean(heroVideoAutoplayEnabled);
        const shouldMute = Boolean(heroVideoMutedEnabled);

        video.playsInline = true;
        video.autoplay = shouldAutoplay;
        video.muted = shouldMute;
        video.defaultMuted = shouldMute;

        if (!shouldAutoplay) {
            return;
        }

        let fallbackStartedMuted = false;
        let gestureListenersAttached = false;

        const restoreAdminSound = function() {
            if (shouldMute || !fallbackStartedMuted) {
                return;
            }

            video.muted = false;
            video.defaultMuted = false;
            removeGestureListeners();

            if (video.paused) {
                const retryPromise = video.play();

                if (retryPromise && typeof retryPromise.catch === 'function') {
                    retryPromise.catch(function() {});
                }
            }
        };

        const removeGestureListeners = function() {
            if (!gestureListenersAttached) {
                return;
            }

            gestureListenersAttached = false;
            document.removeEventListener('pointerdown', restoreAdminSound, true);
            document.removeEventListener('touchstart', restoreAdminSound, true);
            document.removeEventListener('keydown', restoreAdminSound, true);
        };

        const attachSoundRestoreGesture = function() {
            if (shouldMute || gestureListenersAttached) {
                return;
            }

            gestureListenersAttached = true;
            document.addEventListener('pointerdown', restoreAdminSound, true);
            document.addEventListener('touchstart', restoreAdminSound, true);
            document.addEventListener('keydown', restoreAdminSound, true);
        };

        const startMutedFallback = function() {
            fallbackStartedMuted = true;
            video.muted = true;
            video.defaultMuted = true;

            const mutedPlayPromise = video.play();

            if (mutedPlayPromise && typeof mutedPlayPromise.then === 'function') {
                mutedPlayPromise.then(attachSoundRestoreGesture).catch(attachSoundRestoreGesture);
            } else {
                attachSoundRestoreGesture();
            }
        };

        const attemptAutoplay = function() {
            video.muted = shouldMute;
            video.defaultMuted = shouldMute;

            const playPromise = video.play();

            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function() {
                    if (!shouldMute) {
                        startMutedFallback();
                    }
                });
            }
        };

        if (video.readyState >= 2) {
            attemptAutoplay();
        } else {
            video.addEventListener('canplay', attemptAutoplay, { once: true });
        }

        window.setTimeout(function() {
            if (video.paused) {
                if (!shouldMute) {
                    startMutedFallback();
                } else {
                    attemptAutoplay();
                }
            }
        }, 180);
    }

    /*
     * YouTube embeds are generated muted when autoplay is ON because browsers
     * block unmuted autoplay. If the admin kept Muted OFF, restore sound with
     * the iframe API on the first page interaction. Other embeds safely ignore
     * the YouTube command and keep their native controls.
     */
    function initializeHeroEmbedVideoAutoplay() {
        const iframe = document.getElementById('campaignHeroEmbedVideo');

        if (!iframe || !heroVideoAutoplayEnabled || heroVideoMutedEnabled) {
            return;
        }

        let restored = false;

        const restoreEmbedSound = function() {
            if (restored) {
                return;
            }

            restored = true;

            try {
                iframe.contentWindow.postMessage(JSON.stringify({
                    event: 'command',
                    func: 'unMute',
                    args: []
                }), '*');

                iframe.contentWindow.postMessage(JSON.stringify({
                    event: 'command',
                    func: 'playVideo',
                    args: []
                }), '*');
            } catch (error) {}

            document.removeEventListener('pointerdown', restoreEmbedSound, true);
            document.removeEventListener('touchstart', restoreEmbedSound, true);
            document.removeEventListener('keydown', restoreEmbedSound, true);
        };

        document.addEventListener('pointerdown', restoreEmbedSound, true);
        document.addEventListener('touchstart', restoreEmbedSound, true);
        document.addEventListener('keydown', restoreEmbedSound, true);
    }

    /*
     * Bootstrap updates only elements inside .carousel-indicators automatically.
     * Keep a small event fallback too so the custom review dots always follow
     * auto-scroll as well as Previous/Next/manual indicator navigation.
     */
    function initializeReviewCarouselIndicators() {
        const carousel = $('#customerReviewCarousel');

        if (!carousel.length) {
            return;
        }

        const indicators = carousel.find('.review-indicators li');

        if (!indicators.length) {
            return;
        }

        const setActiveIndicator = function(index) {
            const safeIndex = Math.max(0, Math.min(Number(index) || 0, indicators.length - 1));

            indicators.removeClass('active').attr('aria-current', 'false');
            indicators.eq(safeIndex).addClass('active').attr('aria-current', 'true');
        };

        carousel.on('slide.bs.carousel', function(event) {
            if (typeof event.to === 'number') {
                setActiveIndicator(event.to);
            }
        });

        const initialIndex = carousel.find('.carousel-item').index(
            carousel.find('.carousel-item.active').first()
        );

        setActiveIndicator(initialIndex >= 0 ? initialIndex : 0);
    }

    initializeHeroVideoAutoplay();
    initializeHeroEmbedVideoAutoplay();
    initializeReviewCarouselIndicators();

    function normalizeTrackingPhone(value) {
        return String(value || '').replace(/\D/g, '').slice(0, 11);
    }

    function setOrderTrackingError(message) {
        const box = $('#orderTrackingError');
        const input = $('#trackingPhone');

        if (message) {
            box.text(message).removeClass('d-none');
            input.addClass('is-invalid');
            return;
        }

        box.text('').addClass('d-none');
        input.removeClass('is-invalid');
    }


    function resetOrderTrackingUi() {
        $('#trackingPhone').val('').removeClass('is-invalid');
        $('#orderTrackingError').text('').addClass('d-none');
        $('#orderTrackingResults').empty();
    }

    function resetOrderTrackingAfterRefresh() {
        let isReload = false;

        try {
            const navigationEntry = window.performance
                && typeof window.performance.getEntriesByType === 'function'
                ? window.performance.getEntriesByType('navigation')[0]
                : null;

            isReload = navigationEntry
                ? navigationEntry.type === 'reload'
                : !!(window.performance
                    && window.performance.navigation
                    && window.performance.navigation.type === 1);
        } catch (error) {
            isReload = false;
        }

        if (isReload) {
            resetOrderTrackingUi();
        }
    }

    resetOrderTrackingAfterRefresh();

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            resetOrderTrackingUi();
        }
    });

    $(document).on('input', '#trackingPhone', function() {
        $(this).val(normalizeTrackingPhone($(this).val()));

        if ($(this).val().length === 11) {
            setOrderTrackingError('');
        }
    });

    $(document).on('submit', '#orderTrackingForm', function(event) {
        event.preventDefault();

        const form = $(this);
        const input = $('#trackingPhone');
        const submitButton = $('#orderTrackingSubmit');
        const phone = normalizeTrackingPhone(input.val());

        input.val(phone);
        setOrderTrackingError('');

        if (!/^01[0-9]{9}$/.test(phone)) {
            setOrderTrackingError('সঠিক ১১ সংখ্যার বাংলাদেশি ফোন নম্বর দিন।');
            input.trigger('focus');
            return;
        }

        submitButton.prop('disabled', true);
        submitButton.find('span').text('Tracking...');

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            dataType: 'json',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                $('#orderTrackingResults').html(response.html || '');
            },
            error: function(xhr) {
                const validationMessage = xhr.responseJSON?.errors?.tracking_phone?.[0];
                const message = validationMessage
                    || xhr.responseJSON?.message
                    || 'Order tracking details load করা যায়নি। আবার চেষ্টা করুন।';

                setOrderTrackingError(message);
            },
            complete: function() {
                submitButton.prop('disabled', false);
                submitButton.find('span').text('Track Order');
            }
        });
    });

    const deliveryCharges = (config.deliveryCharges || {});

    const defaultDeliveryArea = Object.keys(deliveryCharges)[0] || '';

    let selectedCategory = 'all';
    let selectedBrand = 'all';
    let selectedProducts = {};
    let suppressTracking = false;

    function money(amount) {
        return '৳' + Number(amount || 0).toLocaleString('en-US');
    }

    function number(value) {
        value = Number(value || 0);
        return isNaN(value) ? 0 : value;
    }

    function trackingReady() {
        return typeof window.SFATracking !== 'undefined';
    }

    function filterProducts() {
        let visibleCount = 0;

        $('.product-grid-item').each(function() {
            const item = $(this);
            const categoryId = String(item.data('category'));
            const brandId = String(item.data('brand'));

            const categoryMatch = selectedCategory === 'all' || selectedCategory === categoryId;
            const brandMatch = selectedBrand === 'all' || selectedBrand === brandId;

            if (categoryMatch && brandMatch) {
                item.show();
                visibleCount++;
            } else {
                item.hide();
            }
        });

        $('#noProductFoundBox').remove();

        if (visibleCount === 0) {
            $('#frontProductGrid').append(`
                <div class="col-12" id="noProductFoundBox">
                    <div class="alert alert-warning text-center">
                        এই category এবং brand অনুযায়ী কোনো product পাওয়া যায়নি।
                    </div>
                </div>
            `);
        }
    }

    $(document).on('click', '.product-filter', function() {
        const button = $(this);
        const type = button.data('filter-type');
        const value = String(button.data('filter-value'));

        if (type === 'category') {
            selectedCategory = value;
            $('.product-filter[data-filter-type="category"]').removeClass('active');
        }

        if (type === 'brand') {
            selectedBrand = value;
            $('.product-filter[data-filter-type="brand"]').removeClass('active');
        }

        button.addClass('active');
        filterProducts();
    });

    function hasFreeDeliveryProduct() {
        return Object.values(selectedProducts).some(function(item) {
            return item.isFreeDelivery === true;
        });
    }

    function updateDeliveryAreaView() {
        if (hasFreeDeliveryProduct()) {
            $('#deliveryAreaOptions, #deliveryAreaSelectWrap').addClass('d-none');
            $('#freeDeliveryAreaBox').removeClass('d-none');
        } else {
            $('#deliveryAreaOptions, #deliveryAreaSelectWrap').removeClass('d-none');
            $('#freeDeliveryAreaBox').addClass('d-none');
        }
    }

    function addProductFromCard(card) {
        const id = String(card.data('id'));

        selectedProducts[id] = {
            id: id,
            name: String(card.data('name') || ''),
            price: number(card.data('price') || 0),
            weight: String(card.data('weight') || ''),
            image: card.data('image') || noImage,
            isFreeDelivery: Number(card.data('free-delivery') || 0) === 1,
            quantity: selectedProducts[id] ? Number(selectedProducts[id].quantity || 1) : 1
        };

        return selectedProducts[id];
    }

    function itemPayload(item) {
        return {
            item_id: String(item.id || ''),
            item_name: String(item.name || ''),
            price: number(item.price || 0),
            quantity: Number(item.quantity || 1)
        };
    }

    function getCheckoutItems() {
        return Object.values(selectedProducts).map(function(item) {
            return itemPayload(item);
        }).filter(function(item) {
            return item.item_id || item.item_name;
        });
    }

    function getAllOrderItems() {
        return $('.order-product-card').map(function() {
            const card = $(this);

            return {
                item_id: String(card.data('id') || ''),
                item_name: String(card.data('name') || ''),
                price: number(card.data('price') || 0),
                quantity: 1
            };
        }).get().filter(function(item) {
            return item.item_id || item.item_name;
        });
    }

    function getOrderTotals() {
        let subTotal = 0;

        Object.values(selectedProducts).forEach(function(item) {
            subTotal += number(item.price || 0) * Number(item.quantity || 1);
        });

        const hasAnyFreeDeliveryProduct = hasFreeDeliveryProduct();

        const deliveryArea = $('#deliveryAreaSelect').val()
            || $('input[name="delivery_area"]:checked').val()
            || defaultDeliveryArea;

        const deliveryCharge = hasAnyFreeDeliveryProduct ? 0 : number(deliveryCharges[deliveryArea] || 0);
        const codCharge = 0;
        const grandTotal = subTotal + deliveryCharge + codCharge;

        return {
            subTotal: subTotal,
            deliveryCharge: deliveryCharge,
            codCharge: codCharge,
            grandTotal: grandTotal,
            hasAnyFreeDeliveryProduct: hasAnyFreeDeliveryProduct
        };
    }

    function trackViewContentOnce() {
        if (!trackingReady()) {
            return;
        }

        const items = getAllOrderItems();

        if (!items.length) {
            return;
        }

        const value = items.reduce(function(total, item) {
            return total + (number(item.price) * Number(item.quantity || 1));
        }, 0);

        window.SFATracking.viewContent({
            currency: 'BDT',
            value: value,
            content_type: 'product',
            content_id: items[0].item_id,
            content_name: items[0].item_name,
            items: items
        });
    }

    function trackAddToCart(item) {
        if (!trackingReady() || !item || suppressTracking) {
            return;
        }

        window.SFATracking.addToCart({
            item_id: item.id,
            item_name: item.name,
            price: item.price,
            quantity: item.quantity || 1,
            currency: 'BDT'
        });
    }

    function trackBeginCheckout() {
        if (!trackingReady()) {
            return;
        }

        const items = getCheckoutItems();

        if (!items.length) {
            return;
        }

        const totals = getOrderTotals();

        window.SFATracking.beginCheckout({
            currency: 'BDT',
            value: totals.grandTotal,
            shipping: totals.deliveryCharge,
            tax: totals.codCharge,
            items: items
        });
    }

    function escapeSummaryText(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
        });
    }

    function safeSummaryImage(value) {
        try {
            const url = new URL(String(value || noImage), window.location.origin);
            if (url.protocol === 'https:' || url.protocol === 'http:') {
                return escapeSummaryText(url.href);
            }
        } catch (error) {
            // Unsafe or invalid URL: use the verified application fallback.
        }
        return escapeSummaryText(new URL(noImage, window.location.origin).href);
    }

    function renderSummary() {
        let itemsHtml = '';
        let inputsHtml = '';
        let index = 0;

        Object.values(selectedProducts).forEach(function(item) {
            itemsHtml += `
                <div class="summary-product" data-id="${escapeSummaryText(item.id)}">
                    <div class="summary-product-info">
                        <img src="${safeSummaryImage(item.image)}" alt="${escapeSummaryText(item.name)}">
                        <div>
                            <h5>${escapeSummaryText(item.name)}</h5>
                            <span>${escapeSummaryText(item.weight)}</span><br>
                            <strong>${escapeSummaryText(money(item.price))}</strong>
                            ${item.isFreeDelivery ? '<br><span class="badge badge-success mt-1">ফ্রি ডেলিভারি</span>' : ''}
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <button type="button" class="remove-summary-item"
                                data-id="${escapeSummaryText(item.id)}" aria-label="Remove ${escapeSummaryText(item.name)}">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        </button>

                        <select class="summary-qty" data-id="${escapeSummaryText(item.id)}"
                                aria-label="Quantity for ${escapeSummaryText(item.name)}">
                            ${[1,2,3,4,5,6,7,8,9,10].map(function(qty) {
                                return `<option value="${qty}" ${qty === Number(item.quantity) ? 'selected' : ''}>${qty}</option>`;
                            }).join('')}
                        </select>
                    </div>
                </div>
            `;

            inputsHtml += `
                <input type="hidden" name="products[${index}][id]" value="${escapeSummaryText(item.id)}">
                <input type="hidden" name="products[${index}][quantity]" value="${escapeSummaryText(item.quantity)}">
            `;

            index++;
        });

        if (!Object.keys(selectedProducts).length) {
            itemsHtml = `
                <div class="alert alert-warning">
                    অর্ডার করার জন্য কমপক্ষে একটি product select করুন।
                </div>
            `;
        }

        $('#selectedProductsBox').html(itemsHtml);
        $('#hiddenProductInputs').html(inputsHtml);
        $('#selectedProductsBox img').one('error', function() {
            this.src = new URL(noImage, window.location.origin).href;
        });
        $('.order-product-card').attr('aria-pressed', function() {
            return $(this).hasClass('active') ? 'true' : 'false';
        });

        const totals = getOrderTotals();

        $('#summarySubTotal').text(money(totals.subTotal));
        $('#summaryDeliveryCharge').text(totals.hasAnyFreeDeliveryProduct ? 'Free Delivery' : money(totals.deliveryCharge));
        $('#summaryGrandTotal').text(money(totals.grandTotal));

        updateDeliveryAreaView();
    }

    $(document).on('keydown', '.order-product-card', function(event) {
        if (event.target === this && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            $(this).trigger('click');
        }
    });

    $(document).on('click', '.order-product-card', function() {
        const card = $(this);
        const id = String(card.data('id'));

        if (selectedProducts[id]) {
            delete selectedProducts[id];
            card.removeClass('active');
        } else {
            const item = addProductFromCard(card);
            card.addClass('active');
            trackAddToCart(item);
        }

        renderSummary();
    });

    $(document).on('click', '.add-product-to-order', function() {
        const productId = String($(this).data('product-id'));
        const orderCard = $('.order-product-card[data-id="' + productId + '"]');

        if (orderCard.length) {
            const item = addProductFromCard(orderCard);

            orderCard.addClass('active');
            renderSummary();
            trackAddToCart(item);

            const orderSection = $('#order-section');

            if (orderSection.length) {
                $('html, body').animate({
                    scrollTop: orderSection.offset().top - 80
                }, 500);
            }
        }
    });

    $(document).on('click', 'a[href="#order-section"]', function() {
        trackBeginCheckout();
    });

    $(document).on('click', '.remove-summary-item', function() {
        const id = String($(this).data('id'));

        delete selectedProducts[id];

        $('.order-product-card[data-id="' + id + '"]').removeClass('active');

        renderSummary();
    });

    $(document).on('change', '.summary-qty', function() {
        const id = String($(this).data('id'));
        const quantity = Number($(this).val() || 1);

        if (selectedProducts[id]) {
            selectedProducts[id].quantity = quantity;
        }

        renderSummary();
    });

    $(document).on('change', '#deliveryAreaSelect, input[name="delivery_area"]', function() {
        if ($(this).is('input[name="delivery_area"]')) {
            $('.delivery-area-card').removeClass('active');
            $(this).closest('.delivery-area-card').addClass('active');
        }

        renderSummary();
    });

    const orderResetStorageKey = 'campaign_order_reset_' + String(config.campaignId ?? 'home');
    const shouldResetFromPreviousOrder = sessionStorage.getItem(orderResetStorageKey) === '1';

    function resetOrderFormAfterSuccess() {
        selectedProducts = {};

        const form = $('#campaignOrderForm');

        if (form.length && form[0]) {
            form[0].reset();
        }

        $('.order-product-card').removeClass('active');
        $('#hiddenProductInputs').empty();

        const firstDeliveryInput = $('input[name="delivery_area"]').first();

        if (firstDeliveryInput.length) {
            $('input[name="delivery_area"]').prop('checked', false);
            $('.delivery-area-card').removeClass('active');

            firstDeliveryInput.prop('checked', true);
            firstDeliveryInput.closest('.delivery-area-card').addClass('active');
        }

        $('#campaignOrderForm').find('[type="submit"]')
            .prop('disabled', false)
            .removeClass('disabled');

        renderSummary();
    }

    window.addEventListener('pageshow', function(event) {
        if (event.persisted || sessionStorage.getItem(orderResetStorageKey) === '1') {
            resetOrderFormAfterSuccess();
            sessionStorage.removeItem(orderResetStorageKey);
        }
    });

    function showOrderWarningPopup(message, title) {
        const popup = $('#orderWarningPopup');

        if (!popup.length) {
            return;
        }

        $('#orderWarningPopupTitle').text(title || 'প্রোডাক্ট সিলেক্ট করুন');
        $('#orderWarningPopupMessage').text(message || 'দয়া করে কমপক্ষে একটি product select করুন।');
        popup.addClass('show').attr('aria-hidden', 'false');

        setTimeout(function () {
            $('#orderWarningPopupClose').trigger('focus');
        }, 80);
    }

    const blockedOrderMessage = config.blockedOrderMessage || null;

    if (blockedOrderMessage) {
        showOrderWarningPopup(blockedOrderMessage, 'অর্ডার গ্রহণ করা হয়নি');
    }

    function hideOrderWarningPopup() {
        $('#orderWarningPopup').removeClass('show').attr('aria-hidden', 'true');
    }

    $(document).on('click', '#orderWarningPopupClose', function() {
        hideOrderWarningPopup();
    });

    $(document).on('click', '#orderWarningPopup', function(e) {
        if (e.target === this) {
            hideOrderWarningPopup();
        }
    });

    $(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            hideOrderWarningPopup();
        }
    });

    const deviceIdentifierStorageKey = 'deshbajar_browser_device_identifier';

    function createBrowserDeviceIdentifier() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            const bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);
            return 'dev_' + Array.from(bytes, function(byte) {
                return byte.toString(16).padStart(2, '0');
            }).join('');
        }

        return 'dev_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 18);
    }

    function ensureBrowserDeviceIdentifier() {
        let deviceIdentifier = '';

        try {
            deviceIdentifier = String(localStorage.getItem(deviceIdentifierStorageKey) || '').trim();
        } catch (error) {
            deviceIdentifier = '';
        }

        if (!/^[A-Za-z0-9_-]{16,100}$/.test(deviceIdentifier)) {
            deviceIdentifier = createBrowserDeviceIdentifier();

            try {
                localStorage.setItem(deviceIdentifierStorageKey, deviceIdentifier);
            } catch (error) {
                // Storage can be unavailable in strict/private browser modes.
                // The hidden value still identifies orders during this page session.
            }
        }

        $('#deviceIdentifier').val(deviceIdentifier);
        return deviceIdentifier;
    }

    ensureBrowserDeviceIdentifier();

    function normalizeCustomerPhone(value) {
        return String(value || '').replace(/\D/g, '').slice(0, 11);
    }

    function isValidCustomerPhone(value) {
        return /^01[0-9]{9}$/.test(String(value || ''));
    }

    $(document).on('input', '#customerPhone', function() {
        $(this).val(normalizeCustomerPhone($(this).val()));
    });

    $('#campaignOrderForm').on('submit', function(e) {
        ensureBrowserDeviceIdentifier();
        const customerPhone = normalizeCustomerPhone($('#customerPhone').val());

        $('#customerPhone').val(customerPhone);

        if (!isValidCustomerPhone(customerPhone)) {
            e.preventDefault();
            showOrderWarningPopup('মোবাইল নাম্বার অবশ্যই ১১ ডিজিট হতে হবে এবং 01 দিয়ে শুরু হতে হবে (+88 ছাড়া)।');
            $('#customerPhone').trigger('focus');
            return false;
        }

        if (!Object.keys(selectedProducts).length) {
            e.preventDefault();
            showOrderWarningPopup('দয়া করে কমপক্ষে একটি product select করুন।');
            return false;
        }

        if (!hasFreeDeliveryProduct() && !$('input[name="delivery_area"]:checked').length) {
            e.preventDefault();
            showOrderWarningPopup('এই campaign-এর জন্য কোনো delivery area select করা হয়নি।');
            return false;
        }

        sessionStorage.setItem(orderResetStorageKey, '1');

        $(this).find('[type="submit"]')
            .prop('disabled', true)
            .addClass('disabled');

        trackBeginCheckout();

        return true;
    });

    if (shouldResetFromPreviousOrder) {
        resetOrderFormAfterSuccess();
        sessionStorage.removeItem(orderResetStorageKey);
    } else {
        suppressTracking = true;
        $('.order-product-card').first().trigger('click');
        suppressTracking = false;
    }

    filterProducts();
    trackViewContentOnce();
});
