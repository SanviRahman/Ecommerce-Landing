(() => {
  'use strict';
  const dock = document.querySelector('[data-p38-contact-dock]');
  if (!dock) return;
  const details = dock.querySelector('details');
  if (!details) return;
  document.addEventListener('pointerdown', (event) => {
    if (details.open && !dock.contains(event.target)) details.open = false;
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && details.open) {
      details.open = false;
      details.querySelector('summary')?.focus();
    }
  });
})();
