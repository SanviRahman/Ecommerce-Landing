/* P19: progressive, accessible storefront interactions. All cart mutations go to Laravel;
   prices, stock and order totals are ALWAYS server-authoritative. */
(() => {
  'use strict';
  const doc = document;
  const $ = (selector, root = doc) => root.querySelector(selector);
  const $$ = (selector, root = doc) => [...root.querySelectorAll(selector)];
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  const csrf = $('meta[name="csrf-token"]')?.content || '';

  // Sticky header (CSS sticky is the no-JS fallback).
  const header = $('[data-sticky-header]');
  if (header) {
    const updateHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
    window.addEventListener('scroll', updateHeader, { passive: true });
    updateHeader();
  }

  // P33 hero track: every auto advance moves RIGHT -> LEFT, including seamless wrap.
  // Native buttons + arrow keys + reduced-motion/manual fallback. No clone focus leaks.
  $$('[data-hero-carousel]').forEach((carousel) => {
    const track = $('[data-hero-track]', carousel);
    const slides = $$('[data-hero-slide]', track || carousel);
    const controls = $('[data-hero-controls]', carousel);
    const dotsContainer = $('[data-hero-dots]', carousel);
    if (!track || !slides.length) return;
    slides.forEach((slide, i) => slide.setAttribute('aria-label', `${i + 1} of ${slides.length}`));
    if (slides.length < 2 || !controls || !dotsContainer) return;
    const clone = slides[0].cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    clone.inert = true;
    clone.classList.add('p33-hero-clone');
    track.append(clone);
    let active = 0, transitioning = false, timer = null;
    const dots = slides.map((_, i) => {
      const dot = doc.createElement('button');
      dot.type = 'button';
      dot.setAttribute('aria-label', `Go to banner ${i + 1}`);
      dot.addEventListener('click', () => go(i));
      dotsContainer.append(dot);
      return dot;
    });
    const updateAccessibility = () => {
      slides.forEach((slide, i) => {
        const visible = i === (active % slides.length);
        slide.inert = !visible;
        slide.setAttribute('aria-hidden', visible ? 'false' : 'true');
      });
      dots.forEach((dot, i) => dot.setAttribute('aria-current', i === (active % slides.length) ? 'true' : 'false'));
    };
    const setPosition = (animate) => {
      track.style.transition = !animate || reduced ? 'none' : '';
      track.style.transform = `translate3d(-${active * 100}%, 0, 0)`;
      updateAccessibility();
    };
    const go = (next) => {
      if (transitioning) return;
      const length = slides.length;
      if (next === length && active === length - 1) active = length; // cloned first slide at the end
      else active = (next % length + length) % length;
      transitioning = !reduced;
      setPosition(true);
      if (reduced) transitioning = false;
    };
    track.addEventListener('transitionend', (event) => {
      if (event.target !== track || event.propertyName !== 'transform') return;
      transitioning = false;
      if (active !== slides.length) return;
      active = 0;
      setPosition(false);
      // Restore normal transitions only after the non-animated reset is painted.
      requestAnimationFrame(() => requestAnimationFrame(() => {track.style.transition = '';}));
    });
    carousel.dataset.heroReady = 'true';
    controls.hidden = false;
    setPosition(false);
    const prev = () => go(active - 1);
    const next = () => go(active + 1);
    $('[data-hero-prev]', carousel)?.addEventListener('click', prev);
    $('[data-hero-next]', carousel)?.addEventListener('click', next);
    carousel.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowRight') next();
      if (event.key === 'ArrowLeft') prev();
    });
    const pause = () => { if (timer) clearInterval(timer); timer = null; };
    const resume = () => {
      if (reduced || doc.hidden || timer) return;
      timer = setInterval(next, 5400);
    };
    carousel.addEventListener('mouseenter', pause);
    carousel.addEventListener('mouseleave', resume);
    carousel.addEventListener('focusin', pause);
    carousel.addEventListener('focusout', (event) => {if (!carousel.contains(event.relatedTarget)) resume();});
    doc.addEventListener('visibilitychange', () => doc.hidden ? pause() : resume());
    resume();
  });

  // Approved global testimonials remain read-only; navigation supports pointer, keyboard and reduced motion.
  $$('[data-review-carousel]').forEach(carousel => {
    if (carousel.hasAttribute('data-p39-review-carousel')) return; // enhanced autoplay/dots handled once by P39
    const track = $('[data-review-track]', carousel);
    const cards = $$('.sf-review', track);
    const controls = $('[data-review-controls]', carousel);
    if (!track || !controls || cards.length < 2) return;
    controls.hidden = false;
    const move = (direction) => track.scrollBy({left:direction * Math.max(220, cards[0].getBoundingClientRect().width + 18),
      behavior:reduced?'auto':'smooth'});
    $('[data-review-prev]', controls)?.addEventListener('click', () => move(-1));
    $('[data-review-next]', controls)?.addEventListener('click', () => move(1));
  });

  // Storefront menu/cart drawer. The cart must be ABOVE its backdrop, and the
  // backdrop must not blur the page or intercept the checkout link. Preserve
  // pre-existing body overflow and reset stale state on page navigation/BFCache.
  const overlay = $('[data-drawer-backdrop]');
  const menu = $('[data-menu-drawer]');
  const cartDrawer = $('[data-cart-drawer]');
  let openDrawer = null;
  let focusReturn = null;
  let savedBodyOverflow = null;
  const closeDrawer = ({restoreFocus = true} = {}) => {
    if (!openDrawer) {
      // Pageshow may restore an old DOM snapshot after a browser Back action.
      if (overlay) { overlay.hidden = true; delete overlay.dataset.drawerKind; }
      if (menu) menu.hidden = true;
      if (cartDrawer) cartDrawer.hidden = true;
      $$('[data-menu-open]').forEach(button => button.setAttribute('aria-expanded', 'false'));
      return;
    }
    openDrawer.hidden = true;
    $$('[data-menu-open]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    if (overlay) { overlay.hidden = true; delete overlay.dataset.drawerKind; }
    doc.body.style.overflow = savedBodyOverflow ?? '';
    savedBodyOverflow = null;
    openDrawer = null;
    const previousFocus = focusReturn;
    focusReturn = null;
    if (restoreFocus && previousFocus?.isConnected) previousFocus.focus?.();
  };
  const showDrawer = (drawer, target) => {
    if (!drawer) return;
    closeDrawer({restoreFocus: false});
    focusReturn = target || doc.activeElement;
    savedBodyOverflow = doc.body.style.overflow;
    openDrawer = drawer;
    drawer.hidden = false;
    if (drawer === menu) target?.setAttribute?.('aria-expanded', 'true');
    if (overlay) {
      overlay.dataset.drawerKind = drawer === cartDrawer ? 'cart' : 'menu';
      overlay.hidden = false;
    }
    doc.body.style.overflow = 'hidden';
    $('[data-drawer-close]', drawer)?.focus();
  };
  $$('[data-menu-open]').forEach(button => button.addEventListener('click', () => showDrawer(menu, button)));
  $$('[data-cart-open]').forEach(button => button.addEventListener('click', () => {
    showDrawer(cartDrawer, button);
    void refreshCart();
  }));
  $$('[data-drawer-close]').forEach(button => button.addEventListener('click', () => closeDrawer()));
  overlay?.addEventListener('click', () => closeDrawer());
  // Follow checkout/cart links with NO lingering overlay or scroll lock.
  cartDrawer?.querySelectorAll('a[href]').forEach(link => link.addEventListener('click', () => {
    closeDrawer({restoreFocus: false});
  }));
  window.addEventListener('pagehide', () => closeDrawer({restoreFocus: false}));
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) closeDrawer({restoreFocus: false});
  });
  doc.addEventListener('keydown', (event) => {
    if (!openDrawer) return;
    if (event.key === 'Escape') { event.preventDefault(); closeDrawer(); return; }
    if (event.key !== 'Tab') return;
    const focusable = $$('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled)', openDrawer)
      .filter(el => el.getClientRects().length > 0);
    if (!focusable.length) {event.preventDefault();return;}
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && doc.activeElement === first) {event.preventDefault();last.focus();}
    else if (!event.shiftKey && doc.activeElement === last) {event.preventDefault();first.focus();}
  });
  const mobileSearch = $('[data-mobile-search]');
  $$('[data-search-open]').forEach(button => button.addEventListener('click', () => {
    if (!mobileSearch) return;
    mobileSearch.hidden = false;
    $('#p19-mobile-search-input')?.focus();
  }));
  $('[data-search-close]')?.addEventListener('click', () => {
    if (mobileSearch) mobileSearch.hidden = true;
    $('[data-search-open]')?.focus();
  });

  // Real server-owned cart summary only. No fabricated counts or guessed item totals.
  const cartEndpoint = $('[data-cart-summary-url]')?.dataset.cartSummaryUrl;
  let cartVersion = null;
  let cartLoading = null;
  const updateCartTotals = (data) => {
    $$('[data-cart-count]').forEach(el => {el.textContent = String(data.count);});
    $$('[data-cart-total]').forEach(el => {el.textContent = String(data.subtotal);});
    $$('form[data-async-cart] input[name="cart_version"]').forEach(el => {el.value = String(data.version);});
    const miniTitle = $('[data-p35-cart-peek-title]');
    const miniAction = $('[data-p35-cart-peek-action]');
    if (miniTitle) miniTitle.textContent = data.lines?.length
      ? String(data.lines[0].name).slice(0, 54) : 'Your shopping bag';
    if (miniAction) miniAction.firstChild.textContent = data.lines?.length ? 'View cart ' : 'Open cart ';
    cartVersion = data.version;
  };
  const feedback = (message, error = false) => {
    const container = $('[data-cart-lines]');
    if (!container) return;
    const box = doc.createElement('p');
    box.className = 'p19-cart-message' + (error ? ' is-error' : '');
    box.setAttribute('role', error ? 'alert' : 'status');
    box.textContent = message;
    container.prepend(box);
  };
  const refreshCart = async () => {
    if (!cartEndpoint) return null;
    if (cartLoading) return cartLoading;
    cartLoading = (async () => {
      const response = await fetch(cartEndpoint, {
        method: 'GET', credentials: 'same-origin', headers: {'Accept':'application/json'}, cache:'no-store'
      });
      if (!response.ok) throw new Error('Cart summary is not available. Open the cart page to check your items.');
      const data = await response.json();
      updateCartTotals(data);
      renderCart(data);
      return data;
    })();
    try {return await cartLoading;}
    catch (error) {feedback(error.message, true);return null;}
    finally {cartLoading = null;}
  };
  const renderCart = (data) => {
    const container = $('[data-cart-lines]');
    if (!container) return;
    container.replaceChildren();
    if (!data.lines?.length) {
      const empty = doc.createElement('p');
      empty.textContent = 'Your cart is empty. Add products to get started.';
      container.append(empty);
      return;
    }
    for (const line of data.lines) {
      const row = doc.createElement('div'); row.className = 'p19-mini-line';
      const content = doc.createElement('div'); content.className = 'p19-mini-line-title';
      const name = doc.createElement('strong'); name.textContent = line.name;
      const price = doc.createElement('small'); price.textContent = `${line.option_label || 'Item'} · ৳${line.line_total}`;
      content.append(name, price);
      const actions = doc.createElement('div'); actions.className = 'p19-mini-actions';
      for (const [title,delta] of [['−',-1],['+',1]]) {
        const button = doc.createElement('button');button.type = 'button';button.textContent = title;
        button.setAttribute('aria-label', delta > 0 ? `Increase ${line.name} quantity` : `Decrease ${line.name} quantity`);
        button.addEventListener('click', () => void changeQuantity(line.id, line.quantity + delta));
        actions.append(button);
      }
      const number = doc.createElement('span'); number.textContent = String(line.quantity);
      actions.insertBefore(number, actions.lastChild);
      const remove = doc.createElement('button'); remove.type = 'button';remove.className = 'p19-mini-remove';remove.textContent = 'Remove';
      remove.addEventListener('click', () => void changeQuantity(line.id, 0));actions.append(remove);
      content.append(actions);row.append(content);container.append(row);
      if (!line.available || !line.sufficient || line.price_changed) {
        const warning = doc.createElement('p');warning.className = 'p19-cart-message is-error';
        warning.textContent = 'Product, price or stock changed. Review the full cart before checkout.';
        container.append(warning);
      }
    }
  };
  const csrfHeader = {'Accept':'application/json','X-CSRF-TOKEN':csrf};
  const changeQuantity = async (item, quantity) => {
    if (!cartEndpoint || cartVersion === null) return;
    const url = cartEndpoint.replace(/\/summary\/?$/, '/items/' + encodeURIComponent(String(item)));
    try {
      const response = await fetch(url, {method:'PATCH', credentials:'same-origin',headers:{...csrfHeader,'Content-Type':'application/json'},
        body:JSON.stringify({quantity,cart_version:cartVersion})});
      if (!response.ok) throw new Error(response.status === 409 ? 'Cart changed. Reloading current cart.' : 'Unable to update this item.');
      await refreshCart();
    } catch (error) {await refreshCart();feedback(error.message,true);}
  };
  if (cartEndpoint) void refreshCart();
  $$('form[data-async-cart]').forEach(form => form.addEventListener('submit', async (event) => {
    if (!cartEndpoint || !csrf) return; // Standard POST form fallback.
    event.preventDefault();
    const submitter = event.submitter;
    const data = new FormData(form);
    data.set('intent', submitter?.value || 'cart');
    if (cartVersion !== null) data.set('cart_version', String(cartVersion));
    if (submitter) submitter.disabled = true;
    try {
      const response = await fetch(form.action, {method:'POST',body:data,credentials:'same-origin',headers:csrfHeader});
      if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const first = Object.values(body.errors || {}).flat()[0];
        throw new Error(first || (response.status === 419 ? 'Session expired. Refresh this page.' : 'Unable to add this product. Please review stock and options.'));
      }
      const result = await response.json();
      // Only a confirmed Laravel cart mutation is an AddToCart event.
      // Buy Now flashes that event on the landing Checkout page to avoid a
      // cancelled Pixel request during navigation. No duplicate emission here.
      if (result.redirect) {window.location.assign(result.redirect);return;}
      if (result.saved === true && result.tracking && window.SFATracking?.addToCart) {
        try {window.SFATracking.addToCart(result.tracking);}
        catch (analyticsError) {console.warn('Cart saved, analytics unavailable.', analyticsError);}
      }
      await refreshCart();
      const opener = $('[data-cart-open]');
      showDrawer(cartDrawer, opener);
      feedback('Item added to cart.');
    } catch (error) {await refreshCart(); showDrawer(cartDrawer, $('[data-cart-open]'));feedback(error.message,true);}
    finally {if (submitter) submitter.disabled = false;}
  }));

  // Sticky Buy Now after the primary actions have scrolled above the viewport.
  // Scroll-position checks also handle fast swipes that skip an IntersectionObserver
  // visibility transition (below and above the viewport in the same frame).
  const sticky = $('[data-sticky-order]');
  const primary = $('#p19-primary-buy-actions');
  if (sticky && primary) {
    let framePending = false;
    const update = () => {
      framePending = false;
      const mobile = window.matchMedia('(max-width: 760px)').matches;
      const visible = mobile && primary.getBoundingClientRect().bottom <= 0;
      sticky.hidden = !visible;
      doc.body.classList.toggle('p35-sticky-buy-visible', visible);
    };
    const requestUpdate = () => {
      if (framePending) return;
      framePending = true;
      window.requestAnimationFrame(update);
    };
    window.addEventListener('scroll', requestUpdate, {passive: true});
    window.addEventListener('resize', requestUpdate, {passive: true});
    requestUpdate();
  }

  // Checkout: 1 Shipping -> 2 COD Payment -> 3 Review -> 4 existing order success URL.
  const checkout = $('[data-checkout-stepper]');
  if (checkout) {
    const panels = $$('[data-checkout-step]', checkout);
    const indicators = $$('[data-step-indicator-item]');
    const shippingOptions = $$('[data-shipping-area]', checkout);
    const selectedShipping = () => shippingOptions.find((option) => option.checked);
    const getStep = (index) => panels.find(panel => Number(panel.dataset.checkoutStep) === index);
    const totalPreview = $('[data-checkout-total-preview][data-subtotal-bdt]');
    // Parse decimal BDT into integer paisa; never infer a total from formatted
    // strings with commas or from the browser's cart widget. Server is final.
    const asPaisa = (raw) => {
      const value = String(raw ?? '').trim();
      if (!/^\d+(?:\.\d{1,2})?$/.test(value)) return null;
      const [taka, fraction = ''] = value.split('.');
      const cents = Number(taka) * 100 + Number(fraction.padEnd(2, '0'));
      return Number.isSafeInteger(cents) ? cents : null;
    };
    const bdt = (amount) => '৳' + (amount / 100).toLocaleString('en-BD',
      {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const updateEstimate = () => {
      const fee = selectedShipping()?.dataset.shippingCost;
      const shippingPaisa = asPaisa(fee);
      const subtotalPaisa = asPaisa(totalPreview?.dataset.subtotalBdt);
      const valid = shippingPaisa !== null && subtotalPaisa !== null
        && Number.isSafeInteger(shippingPaisa + subtotalPaisa);
      $$('[data-shipping-preview]').forEach(el => {
        el.textContent = valid ? `Est. ${bdt(shippingPaisa)}` : 'Choose delivery area';
      });
      $$('[data-checkout-total-preview]').forEach(el => {
        el.textContent = valid ? `Est. ${bdt(subtotalPaisa + shippingPaisa)}` : 'Choose delivery area';
      });
    };
    const show = (index, {scroll = true, focus = true} = {}) => {
      panels.forEach(panel => { panel.hidden = Number(panel.dataset.checkoutStep) !== index; });
      indicators.forEach(item => {
        const i = Number(item.dataset.stepIndicatorItem);
        item.classList.toggle('is-current', i === index);
        item.classList.toggle('is-complete', i < index);
        if (i === index) item.setAttribute('aria-current','step');else item.removeAttribute('aria-current');
      });
      if (index === 3) {
        const preview = $('[data-review-shipping]');
        if (preview) {
          preview.textContent = [$('#p08-customer-name')?.value, $('#p08-phone')?.value,
            $('#p08-address')?.value, selectedShipping()?.closest('.p61-area-option')?.textContent?.trim()]
            .filter(Boolean).join('\n');
        }
      }
      const legend = getStep(index)?.querySelector('legend');
      if (legend && focus) {
        legend.setAttribute('tabindex', '-1');
        legend.focus({preventScroll: true});
      }
      if (scroll) {
        // Follow the real sticky header height rather than a fixed 140px offset.
        // Keep the progress indicator visible above the newly opened panel.
        const indicator = $('[data-step-indicator]');
        const stickyHeight = header?.getBoundingClientRect().height || 0;
        const target = indicator || checkout;
        window.scrollTo({
          top: Math.max(0, target.getBoundingClientRect().top + window.scrollY - stickyHeight - 20),
          behavior: reduced ? 'auto' : 'smooth'
        });
      }
    };
    shippingOptions.forEach((option) => option.addEventListener('change', updateEstimate));
    updateEstimate();
    $$('[data-step-next]', checkout).forEach(button => button.addEventListener('click', () => {
      const target = Number(button.dataset.stepNext);
      if (target === 2) {
        const required = $$('[data-checkout-step="1"] :is(input,textarea,select)[required]',checkout);
        const invalid = required.find(field => !field.checkValidity());
        if (invalid) {invalid.reportValidity();invalid.focus();return;}
      }
      show(target);
    }));
    $$('[data-step-back]', checkout).forEach(button => button.addEventListener('click', () => show(Number(button.dataset.stepBack))));
    // Form still submits only to the existing server-validated COD endpoint.
    // Never force-scroll on initial load: the heading, instructions and steps
    // must remain visible, including after Buy Now navigation to Checkout.
    show(1, {scroll: false, focus: false});
  }

  // Low-impact scroll reveal, with a complete no-JS and reduced-motion fallback.
  if (!reduced && 'IntersectionObserver' in window) {
    const nodes = $$('[data-reveal]');
    if (nodes.length) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {entry.target.classList.add('p19-inview');observer.unobserve(entry.target);}
        });
      }, {rootMargin:'0px 0px -30px 0px',threshold:0.08});
      nodes.forEach(node => observer.observe(node));
      doc.body.classList.add('p19-animated');
    }
  }
})();
