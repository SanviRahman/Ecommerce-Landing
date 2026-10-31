/* P53: reuse the existing first-party campaign event bridge and improve sticky mobile CTA placement. */
(() => {
  'use strict';
  const read = (selector, attribute) => {
    const element = document.querySelector(selector);
    if (!element) return null;
    try { return JSON.parse(element.getAttribute(attribute) || 'null'); }
    catch { return null; }
  };
  const track = () => {
    const analytics = window.SFATracking;
    if (!analytics) return;
    const viewed = read('[data-p53-product-tracking]', 'data-p53-product-tracking');
    if (viewed && typeof analytics.viewContent === 'function') {
      analytics.viewContent({currency: 'BDT', value: viewed.price,
        content_id: viewed.item_id, content_name: viewed.item_name,
        content_category: viewed.item_category, items: [viewed]});
    }
    const added = read('[data-p53-added-item]', 'data-p53-added-item');
    if (added && typeof analytics.addToCart === 'function') analytics.addToCart(added);
    const checkout = read('[data-p53-checkout-tracking]', 'data-p53-checkout-tracking');
    if (checkout && Array.isArray(checkout.items) && checkout.items.length
        && typeof analytics.beginCheckout === 'function') analytics.beginCheckout(checkout);
  };
  const syncMobileNavigation = () => {
    const nav = document.querySelector('.p19-mobile-nav.p34-mobile-nav');
    if (!nav) return;
    const height = Math.max(56, Math.ceil(nav.getBoundingClientRect().height || 0));
    document.documentElement.style.setProperty('--p53-mobile-nav-height', height + 'px');
  };
  const init = () => {
    track();
    syncMobileNavigation();
    window.addEventListener('resize', syncMobileNavigation, {passive: true});
    window.addEventListener('orientationchange', syncMobileNavigation, {passive: true});
    if ('ResizeObserver' in window) {
      const nav = document.querySelector('.p19-mobile-nav.p34-mobile-nav');
      if (nav) new ResizeObserver(syncMobileNavigation).observe(nav);
    }
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
  else init();
})();
