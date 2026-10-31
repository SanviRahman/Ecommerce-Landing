/* P39: progressive, accessible left-to-right review-sequence carousel.
   Manual arrows/dots work with keyboard; autoplay respects motion and visibility. */
(() => {
  'use strict';
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  document.querySelectorAll('[data-p39-review-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-review-track]');
    const slides = [...(track?.querySelectorAll('.sf-review') || [])];
    const controls = carousel.querySelector('[data-review-controls]');
    const dotsHolder = carousel.querySelector('[data-review-dots]');
    if (!track || !controls || !dotsHolder || slides.length < 2) return;
    let timer = null;
    let pages = 1;
    let index = 0;
    let moving = false;
    let paused = false;
    let observer;
    const gap = () => parseFloat(getComputedStyle(track).columnGap) || 0;
    const step = () => (slides[0]?.getBoundingClientRect().width || 1) + gap();
    const maximum = () => Math.max(0, track.scrollWidth - track.clientWidth);
    const pageCount = () => Math.max(1, Math.min(slides.length, Math.ceil(maximum() / step() - 0.001) + 1));
    const position = (i) => Math.min(maximum(), Math.max(0, i * step()));
    const dotButtons = [];
    const refreshDots = () => {
      dotButtons.forEach((dot, i) => {
        const active = i === index;
        dot.setAttribute('aria-current', active ? 'true' : 'false');
        dot.setAttribute('aria-label', `Show review position ${i + 1} of ${pages}${active ? ', current' : ''}`);
      });
    };
    const goto = (i, animate = true) => {
      index = Math.min(pages - 1, Math.max(0, i));
      refreshDots();
      moving = true;
      track.scrollTo({left: position(index), behavior: animate && !reduced ? 'smooth' : 'auto'});
      window.setTimeout(() => {moving = false;}, animate && !reduced ? 550 : 0);
    };
    const resetDots = () => {
      const previous = index;
      pages = pageCount();
      dotsHolder.replaceChildren();
      dotButtons.length = 0;
      controls.hidden = dotsHolder.hidden = pages < 2;
      if (pages < 2) {index = 0; stop(); return;}
      for (let i = 0; i < pages; i++) {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.addEventListener('click', () => {goto(i); restart();});
        dotButtons.push(dot);
        dotsHolder.append(dot);
      }
      goto(Math.min(previous, pages - 1), false);
    };
    const stop = () => {if (timer !== null) {window.clearInterval(timer); timer = null;}};
    const shouldAuto = () => !reduced && !paused && !document.hidden && pages > 1;
    const start = () => {
      if (timer !== null || !shouldAuto()) return;
      timer = window.setInterval(() => {
        // Read reviews left-to-right through their sequence, one position at a time.
        // Wrap to the first review without an abrupt reverse slide.
        if (index >= pages - 1) goto(0, false);
        else goto(index + 1);
      }, 4900);
    };
    const restart = () => {stop(); start();};
    controls.querySelector('[data-review-prev]')?.addEventListener('click', () => {goto(index - 1 < 0 ? pages - 1 : index - 1); restart();});
    controls.querySelector('[data-review-next]')?.addEventListener('click', () => {goto(index + 1 >= pages ? 0 : index + 1); restart();});
    carousel.addEventListener('mouseenter', () => {paused = true; stop();});
    carousel.addEventListener('mouseleave', () => {paused = false; start();});
    carousel.addEventListener('focusin', () => {paused = true; stop();});
    carousel.addEventListener('focusout', (event) => {if (!carousel.contains(event.relatedTarget)) {paused = false; start();}});
    carousel.addEventListener('touchstart', () => {paused = true; stop();}, {passive: true});
    carousel.addEventListener('touchend', () => {paused = false; restart();}, {passive: true});
    carousel.addEventListener('keydown', (event) => {
      if (event.target instanceof HTMLElement && event.target.closest('button,a,input')) return;
      if (event.key === 'ArrowLeft') {event.preventDefault(); goto(index - 1 < 0 ? pages - 1 : index - 1); restart();}
      else if (event.key === 'ArrowRight') {event.preventDefault(); goto(index + 1 >= pages ? 0 : index + 1); restart();}
    });
    let scrollFrame = null;
    track.addEventListener('scroll', () => {
      if (moving || scrollFrame !== null) return;
      scrollFrame = window.requestAnimationFrame(() => {
        scrollFrame = null;
        const updated = Math.min(pages - 1, Math.max(0, Math.round(track.scrollLeft / step())));
        if (updated !== index) {index = updated; refreshDots();}
      });
    }, {passive:true});
    if ('ResizeObserver' in window) {
      observer = new ResizeObserver(() => {
        if (track.isConnected) {resetDots(); restart();} else {observer?.disconnect(); stop();}
      });
      observer.observe(track);
    } else window.addEventListener('resize', () => {resetDots(); restart();}, {passive:true});
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
    resetDots();
    if (pages > 1) goto(0, false); // start from the first complete review card
    start();
  });
})();
