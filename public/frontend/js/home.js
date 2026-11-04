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

    function variantMatrixFromCard(card) {
        if (Number(card.attr('data-has-variants') || 0) !== 1) return null;
        try {
            const matrix = JSON.parse(card.attr('data-variant-matrix') || '{}');
            return Array.isArray(matrix.variants) ? matrix : { options: [], variants: [] };
        } catch (error) {
            return { options: [], variants: [] };
        }
    }

    function addProductFromCard(card) {
        const id = String(card.data('id'));
        const matrix = variantMatrixFromCard(card);
        const previous = selectedProducts[id] || null;
        const optionWise = matrix?.mode === 'option_wise';
        const selectedOptions = optionWise ? { ...(previous?.selectedOptions || matrix.selected || {}) } : null;
        const selectedVariant = optionWise ? {
            id: Number(matrix.inventory_variant_id || 0),
            price: Number((matrix.prices || {})[selectedOptions.piece_count] || 0),
            stock: Number(matrix.stock || 0),
            selected: Object.fromEntries((matrix.options || []).map(function(o) {
                return [String(o.id), Number(selectedOptions[o.code] || 0)];
            }))
        } : matrix && window.SfaVariantMatcher
            ? window.SfaVariantMatcher.initial(matrix.variants, previous?.variantId)
            : null;
        if (matrix && (!selectedVariant || selectedVariant.stock < 1 || selectedVariant.price < 1 ||
            (!optionWise && !window.SfaVariantMatcher.purchasable(selectedVariant)))) {
            showOrderWarningPopup('এই প্রোডাক্টের কোনো উপলব্ধ variation নেই। অন্য প্রোডাক্ট বেছে নিন।', 'স্টক নেই');
            return null;
        }

        selectedProducts[id] = {
            id: id,
            name: String(card.data('name') || ''),
            price: matrix ? number(selectedVariant.price) : number(card.data('price') || 0),
            weight: String(card.data('weight') || ''),
            image: card.data('image') || noImage,
            isFreeDelivery: Number(card.data('free-delivery') || 0) === 1,
            quantity: previous ? Number(previous.quantity || 1) : 1,
            matrix: matrix,
            selectedOptions: selectedOptions,
            variantId: selectedVariant ? Number(selectedVariant.id) : null,
            stock: selectedVariant ? Number(selectedVariant.stock) : null
        };
        if (selectedVariant) {
            selectedProducts[id].quantity = Math.max(1,
                Math.min(selectedProducts[id].quantity, selectedProducts[id].stock, 10));
        }
        return selectedProducts[id];
    }

    function selectedVariant(item) {
        if (item.matrix?.mode === 'option_wise') return {
            id: Number(item.matrix.inventory_variant_id),
            selected: Object.fromEntries(item.matrix.options.map(function(o) {
                return [String(o.id), Number(item.selectedOptions?.[o.code] || 0)];
            })),
            stock: Number(item.matrix.stock || 0),
            price: Number(item.matrix.prices?.[item.selectedOptions?.piece_count] || 0),
        };
        return item.matrix?.variants?.find(function(row) {
            return String(row.id) === String(item.variantId);
        }) || null;
    }

    function renderVariantGroups(item) {
        const row = selectedVariant(item);
        if (!row || !item.matrix || (item.matrix.mode !== 'option_wise' && !window.SfaVariantMatcher)) return '';
        const optionIds = item.matrix.options.map(function(option) { return String(option.id); });
        let html = '<div class="campaign-summary-variants">';
        item.matrix.options.forEach(function(option) {
            html += '<fieldset><legend>' + escapeSummaryText(option.name) + '</legend>';
            html += '<div class="campaign-summary-options">';
            option.values.forEach(function(value) {
                const available = item.matrix.mode === 'option_wise' || item.matrix.variants.some(function(candidate) {
                    return String(candidate.selected?.[option.id]) === String(value.id);
                });
                const checked = String(row.selected?.[option.id]) === String(value.id);
                const swatch = /^#[0-9a-fA-F]{6}$/.test(value.swatch || '')
                    ? '<i style="--campaign-chip-swatch:' + value.swatch + '" aria-hidden="true"></i>' : '';
                html += '<label><input type="radio" class="summary-option" data-id="' +
                    escapeSummaryText(item.id) + '" data-option-id="' + escapeSummaryText(option.id) +
                    '" name="campaign-choice-' + escapeSummaryText(item.id) + '-' + escapeSummaryText(option.id) +
                    '" value="' + escapeSummaryText(value.id) + '"' +
                    (checked ? ' checked' : '') + (available ? '' : ' disabled') +
                    '><span class="campaign-chip">' + swatch + escapeSummaryText(value.label) + '</span></label>';
            });
            html += '</div></fieldset>';
        });
        const selectedLabels = item.matrix.options.map(function(option) {
            const value = option.values.find(function(v) {
                return String(v.id) === String(row.selected?.[option.id]);
            });
            return value ? option.name + ': ' + value.label : '';
        }).filter(Boolean).join(' / ');
        html += '</div><div class="campaign-variant-summary-meta">' +
            escapeSummaryText(selectedLabels) + ' · Available stock: ' + escapeSummaryText(row.stock) + '</div>';
        return html;
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
                <div class="summary-product ${item.matrix ? 'campaign-summary-product' : ''}" data-id="${escapeSummaryText(item.id)}">
                    <div class="summary-product-info">
                        <img src="${safeSummaryImage(item.image)}" alt="${escapeSummaryText(item.name)}">
                        <div>
                            <h5>${escapeSummaryText(item.name)}</h5>
                            ${item.matrix ? '' : '<span>' + escapeSummaryText(item.weight) + '</span><br>'}
                            <strong>${escapeSummaryText(money(item.price))} / selected option</strong>
                            <div class="campaign-variant-summary-meta">Line total: ${escapeSummaryText(money(item.price * item.quantity))}</div>
                            ${item.isFreeDelivery ? '<br><span class="badge badge-success mt-1">ফ্রি ডেলিভারি</span>' : ''}
                            ${renderVariantGroups(item)}
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <button type="button" class="remove-summary-item"
                                data-id="${escapeSummaryText(item.id)}" aria-label="Remove ${escapeSummaryText(item.name)}">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        </button>

                        <select class="summary-qty" data-id="${escapeSummaryText(item.id)}"
                                aria-label="Quantity for ${escapeSummaryText(item.name)}">
                            ${[1,2,3,4,5,6,7,8,9,10].filter(function(qty) {
                                return !item.matrix || qty <= item.stock;
                            }).map(function(qty) {
                                return `<option value="${qty}" ${qty === Number(item.quantity) ? 'selected' : ''}>${qty}</option>`;
                            }).join('')}
                        </select>
                    </div>
                </div>
            `;

            inputsHtml += `
                <input type="hidden" name="products[${index}][id]" value="${escapeSummaryText(item.id)}">
                <input type="hidden" name="products[${index}][quantity]" value="${escapeSummaryText(item.quantity)}">
                ${item.matrix ? `<input type="hidden" name="products[${index}][variant_id]" value="${escapeSummaryText(item.variantId)}">` : ''}
                ${item.matrix?.mode === 'option_wise' ? item.matrix.options.map(function(o) {
                    return `<input type="hidden" name="products[${index}][selected_options][${escapeSummaryText(o.code)}]" value="${escapeSummaryText(item.selectedOptions?.[o.code] || '')}">`;
                }).join('') : ''}
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
            if (!item) return;
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
            if (!item) return;

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

    // Keep the option the customer clicked, then select the closest REAL SKU.
    // Never invent a combination or derive a price from the number of pieces.
    $(document).on('change', '.summary-option', function() {
        const id = String($(this).data('id'));
        const item = selectedProducts[id];
        if (!item || !item.matrix) return;
        if (item.matrix.mode === 'option_wise') {
            const optionId = String($(this).data('option-id'));
            const option = item.matrix.options.find(function(o) { return String(o.id) === optionId; });
            if (!option) return;
            item.selectedOptions[option.code] = Number($(this).val());
            item.price = Number(item.matrix.prices[item.selectedOptions.piece_count] || 0);
            if (item.price < 1) { showOrderWarningPopup('Please select an available Piece Count.'); return; }
            item.stock = Number(item.matrix.stock);
            item.quantity = Math.min(item.quantity, Math.max(1, item.stock), 10);
            renderSummary();
            $('#selectedProductsBox .summary-option').filter(function() {
                return String($(this).data('id')) === id &&
                    String($(this).data('option-id')) === optionId &&
                    String($(this).val()) === String(item.selectedOptions[option.code]);
            }).first().trigger('focus');
            return;
        }
        if (!window.SfaVariantMatcher) return;
        const previous = selectedVariant(item);
        const optionId = String($(this).data('option-id'));
        const optionIds = item.matrix.options.map(function(option) { return String(option.id); });
        const row = window.SfaVariantMatcher.forChangeExact(
            item.matrix.variants, optionIds, previous?.selected || {}, optionId, String($(this).val())
        );
        if (!row || !window.SfaVariantMatcher.purchasable(row)) {
            showOrderWarningPopup('এই option combination-টি বর্তমানে পাওয়া যাচ্ছে না অথবা স্টক নেই। আগের selection অপরিবর্তিত আছে।');
            renderSummary();
            return;
        }
        item.variantId = Number(row.id);
        item.price = number(row.price);
        item.stock = Number(row.stock);
        item.quantity = Math.min(item.quantity, Math.max(1, item.stock), 10);
        renderSummary();
        // Re-rendering radios destroys the old focused input; restore keyboard focus.
        $('#selectedProductsBox .summary-option').filter(function() {
            return String($(this).data('id')) === id &&
                String($(this).data('option-id')) === optionId &&
                String($(this).val()) === String(row.selected?.[optionId]);
        }).first().trigger('focus');
    });

    $(document).on('change', '.summary-qty', function() {
        const id = String($(this).data('id'));
        const quantity = Number($(this).val() || 1);

        if (selectedProducts[id]) {
            selectedProducts[id].quantity = selectedProducts[id].matrix
                ? Math.min(quantity, selectedProducts[id].stock, 10)
                : quantity;
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
        // Never auto-add an unavailable variable SKU on first page load.
        $('.order-product-card').filter(function() {
            const card = $(this);
            const matrix = variantMatrixFromCard(card);
            return !matrix || (window.SfaVariantMatcher && matrix.variants.some(function(row) {
                return window.SfaVariantMatcher.purchasable(row);
            }));
        }).first().trigger('click');
        suppressTracking = false;
    }

    filterProducts();
    trackViewContentOnce();
});
