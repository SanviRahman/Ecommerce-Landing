/* Shared accessible back-to-top button for admin, campaign and storefront layouts. */
(() => {
  'use strict';
  const init = () => {
    if (document.querySelector('[data-back-to-top]')) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sf-back-to-top';
    button.setAttribute('data-back-to-top', '');
    button.setAttribute('aria-label', 'Back to top');
    button.setAttribute('title', 'Back to top');
    button.innerHTML = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-7 7 7-7 7 7"/></svg>';
    document.body.appendChild(button);
    let queued = false;
    const update = () => {
      queued = false;
      const isVisible = window.scrollY > 320;
      button.classList.toggle('is-visible', isVisible);
      button.tabIndex = isVisible ? 0 : -1;
      button.setAttribute('aria-hidden', String(!isVisible));
    };
    window.addEventListener('scroll', () => {
      if (!queued) { queued = true; window.requestAnimationFrame(update); }
    }, { passive: true });
    update();
    button.addEventListener('click', () => {
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
