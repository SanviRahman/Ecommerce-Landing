/* P35: visual quantity controls only; Laravel still validates quantity and stock. */
(() => {
  'use strict';
  document.querySelectorAll('[data-p35-quantity-stepper]').forEach((stepper) => {
    const field = stepper.querySelector('input[type="number"][name="quantity"]');
    if (!field) return;
    const buttons = [...stepper.querySelectorAll('[data-p35-qty-step]')];
    const limits = () => ({
      min: Math.max(1, Number(field.min) || 1),
      max: Math.max(1, Math.min(1000, Number(field.max) || 1000)),
    });
    const update = () => {
      const {min, max} = limits();
      const current = Number(field.value);
      buttons.forEach((button) => {
        button.disabled = !Number.isFinite(current) || (Number(button.dataset.p35QtyStep) < 0
          ? current <= min : current >= max);
      });
    };
    buttons.forEach((button) => button.addEventListener('click', () => {
      const {min, max} = limits();
      const value = Number(field.value);
      const base = Number.isFinite(value) ? value : min;
      field.value = String(Math.max(min, Math.min(max, base + Number(button.dataset.p35QtyStep))));
      field.dispatchEvent(new Event('input', {bubbles:true}));
      update();
    }));
    field.addEventListener('input', update);
    field.addEventListener('change', () => {
      const {min, max} = limits();
      field.value = String(Math.max(min, Math.min(max, Math.round(Number(field.value) || min))));
      update();
    });
    field.addEventListener('p35-stock-changed', () => {
      const {min, max} = limits();
      if (Number(field.value) > max) field.value = String(max);
      if (Number(field.value) < min) field.value = String(min);
      update();
    });
    update();
  });
})();
