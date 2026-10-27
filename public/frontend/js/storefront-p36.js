/* P36: match the mobile hero height to the selected image's intrinsic ratio.
   No network calls, no cart/state writes, and no changes to existing slider controls. */
(() => {
  'use strict';
  for (const carousel of document.querySelectorAll('[data-hero-carousel]')) {
    const track = carousel.querySelector('[data-hero-track]');
    if (!track) continue;
    const slides = [...track.querySelectorAll('[data-hero-slide]')].filter(
      slide => !slide.classList.contains('p33-hero-clone'));
    if (!slides.length) continue;
    const compact = window.matchMedia('(max-width:760px)');
    const sync = () => {
      if (!compact.matches) {
        carousel.style.removeProperty('--p36-mobile-height');
        return;
      }
      const selected = slides.find(slide => slide.getAttribute('aria-hidden') === 'false') || slides[0];
      const image = selected.querySelector('.p36-hero-banner');
      if (!image || !image.complete || !image.naturalWidth || !image.naturalHeight) return;
      const width = carousel.clientWidth;
      const pixels = Math.round(width * image.naturalHeight / image.naturalWidth);
      if (pixels > 48 && pixels < 2400) carousel.style.setProperty('--p36-mobile-height', `${pixels}px`);
    };
    slides.forEach(slide => slide.querySelector('.p36-hero-banner')?.addEventListener('load', sync));
    if ('MutationObserver' in window) {
      const observer = new MutationObserver(sync);
      slides.forEach(slide => observer.observe(slide, {attributes:true, attributeFilter:['aria-hidden']}));
    }
    window.addEventListener('resize', sync, {passive:true});
    sync();
  }
})();
