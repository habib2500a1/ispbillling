/**
 * Collection desk — recent subscriber searches (localStorage).
 */
(function () {
    'use strict';

    const KEY = 'isp-collection-recent';
    const MAX = 8;

    function read() {
        try {
            return JSON.parse(localStorage.getItem(KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function write(items) {
        try {
            localStorage.setItem(KEY, JSON.stringify(items.slice(0, MAX)));
        } catch (e) {
            /* ignore */
        }
    }

    function push(term) {
        const q = (term || '').trim();
        if (q.length < 2) return;
        const next = [q, ...read().filter((x) => x !== q)];
        write(next);
        render();
    }

    function render() {
        const wrap = document.querySelector('[data-collection-recent]');
        const chips = document.querySelector('[data-collection-recent-chips]');
        const input = document.getElementById('collection-search');
        if (!wrap || !chips) return;

        const items = read();
        if (!items.length) {
            wrap.hidden = true;
            return;
        }

        wrap.hidden = false;
        chips.innerHTML = items
            .map(
                (q) =>
                    `<button type="button" class="isp-collection-recent__chip" data-recent="${q.replace(/"/g, '&quot;')}">${q}</button>`,
            )
            .join('');

        chips.querySelectorAll('[data-recent]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!input) return;
                input.value = btn.dataset.recent || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                const form = input.closest('form');
                if (form) form.requestSubmit();
                // Livewire
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    function init() {
        const input = document.getElementById('collection-search');
        if (!input) return;

        render();

        input.addEventListener('change', () => {
            if ((input.value || '').trim().length >= 2) {
                push(input.value);
            }
        });

        document.addEventListener('livewire:navigated', render);
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:init', () => {
        if (window.Livewire) {
            Livewire.hook('commit', ({ component, succeed }) => {
                succeed(() => {
                    const input = document.getElementById('collection-search');
                    if (input && document.activeElement === input) return;
                    render();
                });
            });
        }
    });
})();
