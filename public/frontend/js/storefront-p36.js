/* P43: keep a single mobile hero image under its readable copy overlay.
   Sizing follows the active slide's visible text, not image height PLUS text.
   Image-only slides keep their intrinsic banner ratio; desktop is unchanged. */
(() => {
  'use strict';
  for (const carousel of document.querySelectorAll('[data-hero-carousel]')) {
    const track = carousel.querySelector('[data-hero-track]');
    if (!track) continue;
    const slides = [...track.querySelectorAll('[data-hero-slide]')].filter(
      slide => !slide.classList.contains('p33-hero-clone'));
    if (!slides.length) continue;
    const compact = window.matchMedia('(max-width: 760px)');
    const sync = () => {
      if (!compact.matches) {
        carousel.style.removeProperty('--p36-mobile-height');
        return;
      }
      const selected = slides.find(slide => slide.getAttribute('aria-hidden') === 'false') || slides[0];
      const image = selected.querySelector('.p36-hero-banner');
      if (!image) return;
      const width = carousel.clientWidth;
      if (!width) return;
      const pixels = image.naturalWidth && image.naturalHeight
        ? Math.round(width * image.naturalHeight / image.naturalWidth)
        : Math.round(width * 7 / 16);

      const copy = selected.classList.contains('p36-hero-with-copy')
        ? selected.querySelector('.sf-hero-copy') : null;
      let required = pixels;
      if (copy) {
        const style = window.getComputedStyle(copy);
        const children = [...copy.children].filter(child =>
          child.getClientRects().length && window.getComputedStyle(child).display !== 'none');
        const px = value => Number.parseFloat(value) || 0;
        const contentHeight = children.reduce((sum, child) => {
          const margin = window.getComputedStyle(child);
          return sum + child.getBoundingClientRect().height
            + px(margin.marginTop) + px(margin.marginBottom);
        }, 0);
        const gap = px(style.rowGap) * Math.max(0, children.length - 1);
        // The overlay includes a generous bottom safe area for carousel controls.
        // Never stack image pixels and copy height: they share the same panel.
        required = Math.max(pixels, Math.ceil(contentHeight + gap + px(style.paddingTop) + px(style.paddingBottom) + 3));
      }
      if (required > 48 && required < 2400) {
        carousel.style.setProperty('--p36-mobile-height', `${required}px`);
      }
    };
    slides.forEach(slide => slide.querySelector('.p36-hero-banner')?.addEventListener('load', sync));
    if ('MutationObserver' in window) {
      const observer = new MutationObserver(sync);
      slides.forEach(slide => observer.observe(slide, { attributes: true, attributeFilter:['aria-hidden'] }));
    }
    window.addEventListener('resize', sync, { passive: true });
    if (document.fonts?.ready) document.fonts.ready.then(sync);
    sync();
  }
})();
