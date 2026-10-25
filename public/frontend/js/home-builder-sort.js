/* P06: progressive-enhancement sort UI for the authenticated, test-only builder.
   No external JS packages, dynamic HTML, new endpoints, or local storage. */
(function () {
    'use strict';

    function moveKey(keys, key, destination) {
        const result = keys.slice();
        const origin = result.indexOf(key);
        if (origin < 0 || !Number.isInteger(destination) || destination < 0 || destination >= result.length) {
            return result;
        }
        result.splice(origin, 1);
        result.splice(destination, 0, key);
        return result;
    }

    function isExactOrder(expected, incoming) {
        return Array.isArray(expected) && Array.isArray(incoming)
            && expected.length === incoming.length
            && new Set(expected).size === expected.length
            && new Set(incoming).size === incoming.length
            && incoming.every((key) => typeof key === 'string' && expected.includes(key));
    }

    // Pure functions are used in dependency-free node regression tests.
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { moveKey, isExactOrder };
    }
    if (typeof document === 'undefined') return;

    function init() {
        const list = document.getElementById('p06-sort-list');
        const editors = document.getElementById('p06-section-editors');
        const feedback = document.getElementById('p06-sort-feedback');
        if (!list || !editors || !feedback) return;
        const url = list.dataset.reorderUrl;
        const csrf = list.dataset.csrfToken;
        if (!url || !csrf) return;

        let busy = false;
        let pointer = null;
        const entries = () => Array.from(list.querySelectorAll(':scope > .p06-sort-item'));
        const keys = () => entries().map((item) => item.dataset.sectionKey);

        function say(message, type) {
            feedback.textContent = message;
            feedback.classList.remove('text-muted', 'text-success', 'text-danger');
            feedback.classList.add(type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-muted');
        }

        function setBusy(flag) {
            busy = flag;
            list.classList.toggle('p06-saving', flag);
            entries().forEach((entry) => {
                const handle = entry.querySelector('.p06-drag-handle');
                if (handle) handle.disabled = flag;
            });
        }

        function showPositions() {
            entries().forEach((entry, i) => {
                const number = entry.querySelector('.p06-sort-number');
                if (number) number.textContent = String(i + 1);
            });
        }

        function restore(original) {
            const byKey = new Map(entries().map((entry) => [entry.dataset.sectionKey, entry]));
            original.forEach((key) => {
                const entry = byKey.get(key);
                if (entry) list.appendChild(entry);
            });
            showPositions();
        }

        function syncCards(order) {
            const byKey = new Map(Array.from(editors.querySelectorAll(':scope > [data-p06-section-card]'))
                .map((card) => [card.dataset.sectionKey, card]));
            order.forEach((key, i) => {
                const card = byKey.get(key);
                if (!card) return;
                editors.appendChild(card); // Move existing inputs without wiping unsaved form values.
                const number = card.querySelector('.p06-card-number');
                if (number) number.textContent = String(i + 1);
                const up = card.querySelector('[data-p06-direction="up"]');
                const down = card.querySelector('[data-p06-direction="down"]');
                if (up) up.disabled = i === 0;
                if (down) down.disabled = i === order.length - 1;
            });
        }

        function syncVersion(version) {
            list.dataset.version = String(version);
            document.querySelectorAll('input[name="expected_version"]').forEach((input) => {
                input.value = String(version);
            });
            const counter = document.getElementById('p06-page-version');
            if (counter) counter.textContent = String(version);
        }

        async function persist(original, trigger) {
            const changed = keys();
            if (original.join('|') === changed.join('|')) return;
            if (!isExactOrder(original, changed)) {
                restore(original);
                say('Invalid order. Original section order restored.', 'error');
                return;
            }
            setBusy(true);
            say('Saving the new draft section order...', 'info');
            const version = Number(list.dataset.version);
            try {
                const response = await fetch(url, {
                    method: 'PATCH', credentials: 'same-origin', cache: 'no-store',
                    headers: {
                        'Accept': 'application/json', 'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ expected_version: version, section_keys: changed }),
                });
                if (!response.ok) {
                    throw new Error(response.status === 409 ? 'Another tab changed this draft. Reload before moving sections.'
                        : response.status === 419 ? 'Your session expired. Reload and sign in again.'
                            : 'Server rejected this section order (' + response.status + ').');
                }
                const answer = await response.json();
                if (answer.saved !== true || answer.lock_version !== version + 1
                    || !Array.isArray(answer.section_keys)
                    || answer.section_keys.join('|') !== changed.join('|')) {
                    throw new Error('The saved order could not be confirmed. Reload before further changes.');
                }
                syncVersion(answer.lock_version);
                syncCards(changed);
                showPositions();
                say('Section order saved to the private draft. Published homepage was not changed.', 'success');
                if (trigger && typeof trigger.focus === 'function' && trigger.isConnected) {
                    trigger.focus({ preventScroll: true });
                }
            } catch (error) {
                restore(original);
                say((error && error.message ? error.message : 'Network error.') + ' Original order restored on this page.', 'error');
            } finally {
                setBusy(false);
            }
        }

        list.addEventListener('keydown', (event) => {
            const handle = event.target.closest('.p06-drag-handle');
            if (!handle || busy || pointer) return;
            const row = handle.closest('.p06-sort-item');
            const original = keys();
            const start = original.indexOf(row.dataset.sectionKey);
            const target = event.key === 'ArrowUp' ? start - 1
                : event.key === 'ArrowDown' ? start + 1
                    : event.key === 'Home' ? 0 : event.key === 'End' ? original.length - 1 : -1;
            if (!['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            if (target < 0 || target >= original.length || target === start) return;
            const moved = moveKey(original, row.dataset.sectionKey, target);
            restore(moved);
            persist(original, handle);
        });

        list.addEventListener('pointerdown', (event) => {
            if (busy || pointer || event.button !== 0) return;
            const handle = event.target.closest('.p06-drag-handle');
            if (!handle) return;
            const row = handle.closest('.p06-sort-item');
            if (!row) return;
            pointer = {
                id: event.pointerId, handle, row, fromY: event.clientY,
                original: keys(), dragged: false,
            };
            if (handle.setPointerCapture) handle.setPointerCapture(event.pointerId);
        });

        list.addEventListener('pointermove', (event) => {
            if (!pointer || event.pointerId !== pointer.id) return;
            if (!pointer.dragged && Math.abs(event.clientY - pointer.fromY) < 7) return;
            pointer.dragged = true;
            event.preventDefault();
            pointer.row.classList.add('p06-dragging');
            // elementFromPoint works while the drag handle holds pointer capture.
            const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('.p06-sort-item');
            if (!target || target === pointer.row || target.parentElement !== list) return;
            const rect = target.getBoundingClientRect();
            list.insertBefore(pointer.row, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
            showPositions();
        });

        list.addEventListener('pointerup', (event) => {
            if (!pointer || event.pointerId !== pointer.id) return;
            const active = pointer;
            pointer = null;
            active.row.classList.remove('p06-dragging');
            if (active.dragged) persist(active.original, active.handle);
        });

        function cancelPointer() {
            if (!pointer) return;
            const active = pointer;
            pointer = null;
            active.row.classList.remove('p06-dragging');
            restore(active.original);
            say('Drag cancelled. No changes saved.', 'info');
        }
        list.addEventListener('pointercancel', cancelPointer);
        window.addEventListener('blur', cancelPointer);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && pointer) {
                event.preventDefault();
                cancelPointer();
            }
        });
        // Picker is a convenient visual companion; the HEX field stays the sole form value.
        // Clearing an override preserves P05 null=inherited-global semantics.
        document.addEventListener('input', (event) => {
            if (event.target.matches('[data-p06-color-picker]')) {
                const target = document.getElementById(event.target.dataset.p06HexInput);
                if (target) target.value = event.target.value.toUpperCase();
            } else if (event.target.matches('[data-p06-hex-value]')) {
                const hex = event.target.value;
                if (!/^#[0-9a-fA-F]{6}$/.test(hex)) return;
                document.querySelectorAll('[data-p06-color-picker]').forEach((picker) => {
                    if (picker.dataset.p06HexInput === event.target.id) picker.value = hex;
                });
            }
        });
        document.addEventListener('click', (event) => {
            const reset = event.target.closest('[data-p06-clear-color]');
            if (!reset) return;
            const input = document.getElementById(reset.dataset.p06ClearColor);
            if (input) {
                input.value = ''; // Server stores null: inherit global, not arbitrary CSS.
                input.focus();
            }
        });

        list.classList.add('p06-ready');
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
}());
