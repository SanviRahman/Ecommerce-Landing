/* P33: enhancements only; forms always work with standard POST when JS is unavailable. */
(() => {
  'use strict';
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
  // Inline order tracking on homepage and standalone storefront tracking page.
  document.querySelectorAll('form[data-storefront-track]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      if (!window.fetch || !window.FormData || !window.URLSearchParams) return;
      if (!form.checkValidity()) return;
      const output = form.parentElement.querySelector('[data-track-results]');
      if (!output) return;
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      if (button) button.disabled = true;
      output.textContent = 'Checking your order status…';
      try {
        const response = await fetch(form.action, {
          method: 'POST', credentials: 'same-origin', cache: 'no-store',
          headers: {'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
          body: new URLSearchParams(new FormData(form)).toString(),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data?.message || (response.status === 419 ? 'Session expired. Refresh and try again.' : 'Unable to track the order.'));
        // Rendered by our same-origin Laravel Blade partial (all user data Blade-escaped).
        output.innerHTML = data.html || '';
        if (!data.html) output.textContent = data.message || 'No results found.';
      } catch (error) {
        output.textContent = error?.message || 'Order tracking unavailable right now.';
      } finally {if (button) button.disabled = false;}
    });
  });
  // Bounded real catalog statistics; nothing is fabricated on the client.
  if (!reduced && 'IntersectionObserver' in window) {
    const counters = [...document.querySelectorAll('[data-count-up]')];
    if (counters.length) {
      const animate = (element) => {
        const final = Math.max(0, Math.min(1000000, Number(element.dataset.countUp) || 0));
        const start = performance.now();
        const draw = (time) => {
          const progress = Math.min(1, (time - start) / 850);
          const ease = 1 - Math.pow(1 - progress, 3);
          element.textContent = Math.round(final * ease).toLocaleString('en-BD');
          if (progress < 1) requestAnimationFrame(draw);
        };
        requestAnimationFrame(draw);
      };
      const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {animate(entry.target);observer.unobserve(entry.target);}
      }), {threshold:.35});
      counters.forEach(item => observer.observe(item));
    }
  }
  // P34: horizontal shelves advance visually right-to-left whenever on screen.
  // Preserve native touch swiping, trackpad scrolling, keyboard controls and reduced motion.
  document.querySelectorAll('[data-product-carousel]').forEach((track) => {
    const section = track.closest('.p33-auto-section, .sf-section');
    if (!section) return;
    const gap = () => Number.parseFloat(getComputedStyle(track).columnGap) || 14;
    const step = () => (track.firstElementChild?.getBoundingClientRect().width || 230) + gap();
    const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
    const canScroll = () => maxScroll() > 4;
    const scroll = (direction) => {
      if (!canScroll()) return;
      track.scrollBy({left: direction * step(), behavior: reduced ? 'auto' : 'smooth'});
    };
    let isVisible = !('IntersectionObserver' in window);
    let hovered = false;
    let focused = false;
    let pointerDown = false;
    let holdUntil = 0;
    const hold = () => {holdUntil = Date.now() + 4500;};
    section.querySelector('[data-carousel-prev]')?.addEventListener('click', () => {hold();scroll(-1);});
    section.querySelector('[data-carousel-next]')?.addEventListener('click', () => {hold();scroll(1);});
    track.addEventListener('mouseenter', () => {hovered = true;});
    track.addEventListener('mouseleave', () => {hovered = false;});
    track.addEventListener('focusin', () => {focused = true;});
    track.addEventListener('focusout', (event) => {
      if (!track.contains(event.relatedTarget)) focused = false;
    });
    track.addEventListener('touchstart', () => {pointerDown = true;hold();}, {passive:true});
    track.addEventListener('touchend', () => {pointerDown = false;hold();}, {passive:true});
    track.addEventListener('touchcancel', () => {pointerDown = false;hold();}, {passive:true});
    track.addEventListener('wheel', hold, {passive:true});
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
      }, {threshold:.2});
      observer.observe(track);
    }
    if (reduced) return; // Never auto-move when user requests reduced motion.
    window.setInterval(() => {
      if (!isVisible || document.hidden || hovered || focused || pointerDown
          || Date.now() < holdUntil || !canScroll()) return;
      // Each visible auto step moves from the RIGHT to the LEFT. Resetting at the end
      // is immediate: never animate backward through the entire collection.
      if (track.scrollLeft >= maxScroll() - 4) {
        track.scrollTo({left:0, behavior:'instant'});
      } else {
        scroll(1);
      }
    }, 3600);
  });
  // Keep desktop categories dropdown from blocking content after users click elsewhere.
  const categoriesMenu = document.querySelector('[data-categories-menu]');
  if (categoriesMenu) {
    document.addEventListener('pointerdown', (event) => {
      if (categoriesMenu.open && !categoriesMenu.contains(event.target)) categoriesMenu.open = false;
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && categoriesMenu.open) {
        categoriesMenu.open = false;
        categoriesMenu.querySelector('summary')?.focus();
      }
    });
    categoriesMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
      categoriesMenu.open = false;
    }));
  }
})();
