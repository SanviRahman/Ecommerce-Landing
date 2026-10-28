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

  // Off-canvas menu and cart: Escape, overlay, focus trap and focus restoration.
  const overlay = $('[data-drawer-backdrop]');
  const menu = $('[data-menu-drawer]');
  const cartDrawer = $('[data-cart-drawer]');
  let openDrawer = null;
  let focusReturn = null;
  const closeDrawer = () => {
    if (!openDrawer) return;
    openDrawer.hidden = true;
    if (overlay) overlay.hidden = true;
    doc.body.style.overflow = '';
    openDrawer = null;
    focusReturn?.focus?.();
    focusReturn = null;
  };
  const showDrawer = (drawer, target) => {
    if (!drawer) return;
    closeDrawer();
    focusReturn = target || doc.activeElement;
    openDrawer = drawer;
    drawer.hidden = false;
    if (overlay) overlay.hidden = false;
    doc.body.style.overflow = 'hidden';
    $('[data-drawer-close]', drawer)?.focus();
  };
  $$('[data-menu-open]').forEach(button => button.addEventListener('click', () => showDrawer(menu, button)));
  $$('[data-cart-open]').forEach(button => button.addEventListener('click', () => {
    showDrawer(cartDrawer, button);
    void refreshCart();
  }));
  $$('[data-drawer-close]').forEach(button => button.addEventListener('click', closeDrawer));
  overlay?.addEventListener('click', closeDrawer);
  doc.addEventListener('keydown', (event) => {
    if (!openDrawer) return;
    if (event.key === 'Escape') { event.preventDefault(); closeDrawer(); return; }
    if (event.key !== 'Tab') return;
    const focusable = $$('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled)', openDrawer)
      .filter(el => el.getClientRects().length > 0);
    if (!focusable.length) return;
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
      await refreshCart();
      if (result.redirect) {window.location.assign(result.redirect);return;}
      const opener = $('[data-cart-open]');
      showDrawer(cartDrawer, opener);
      feedback('Item added to cart.');
    } catch (error) {await refreshCart(); showDrawer(cartDrawer, $('[data-cart-open]'));feedback(error.message,true);}
    finally {if (submitter) submitter.disabled = false;}
  }));

  // Sticky Order Now only after primary CTA has scrolled above the viewport.
  const sticky = $('[data-sticky-order]');
  const primary = $('#p19-primary-buy-actions');
  if (sticky && primary) {
    const setStickyVisible = (visible) => {
      sticky.hidden = !visible;
      doc.body.classList.toggle('p35-sticky-buy-visible', visible);
    };
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(([entry]) => {
        setStickyVisible(!entry.isIntersecting && entry.boundingClientRect.top < 0);
      }, {threshold:0});
      observer.observe(primary);
    } else {
      const update = () => setStickyVisible(primary.getBoundingClientRect().bottom <= 0);
      window.addEventListener('scroll', update, {passive:true});
      window.addEventListener('resize', update);
      update();
    }
  }

  // Checkout: 1 Shipping -> 2 COD Payment -> 3 Review -> 4 existing order success URL.
  const checkout = $('[data-checkout-stepper]');
  if (checkout) {
    const panels = $$('[data-checkout-step]', checkout);
    const indicators = $$('[data-step-indicator-item]');
    const shipping = $('#p08-area', checkout);
    const getStep = (index) => panels.find(panel => Number(panel.dataset.checkoutStep) === index);
    const updateEstimate = () => {
      const fee = shipping?.selectedOptions?.[0]?.dataset.shippingCost;
      $$('[data-shipping-preview]').forEach(el => {el.textContent = fee === undefined ? 'Choose delivery area' : `Est. ৳${Number(fee).toLocaleString('en-BD')} (confirmed on submit)`;});
    };
    const show = (index) => {
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
            $('#p08-address')?.value, shipping?.selectedOptions?.[0]?.textContent?.trim()]
            .filter(Boolean).join('\n');
        }
      }
      getStep(index)?.querySelector('legend')?.setAttribute('tabindex','-1');
      getStep(index)?.querySelector('legend')?.focus({preventScroll:true});
      window.scrollTo({top:Math.max(0,checkout.getBoundingClientRect().top+window.scrollY-140),behavior:reduced?'auto':'smooth'});
    };
    shipping?.addEventListener('change', updateEstimate);
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
    show(1);
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
