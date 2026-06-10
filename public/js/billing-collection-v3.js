/**
 * Collection desk — GET search (reliable) + recent search chips.
 */
(function () {
    'use strict';

    const KEY = 'isp-collection-recent';
    const MAX = 8;
    const FOCUS_KEY = 'isp-collection-search-focus';
    const CURSOR_KEY = 'isp-collection-search-cursor';

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
        if (q.length < 2) {
            return;
        }

        const next = [q, ...read().filter((x) => x !== q)];
        write(next);
        render();
    }

    function currentFilter() {
        const hidden = document.getElementById('collection-search-filter');

        return hidden && hidden.value ? hidden.value : 'all';
    }

    function submitCollectionSearch() {
        const form = document.getElementById('collection-search-form');
        const input = document.getElementById('collection-search');

        if (!form || !input) {
            return;
        }

        const base = form.getAttribute('action') || window.location.pathname;
        const term = String(input.value || '').trim();
        const filter = currentFilter();

        if (term.length < 2) {
            return;
        }

        const params = new URLSearchParams();
        params.set('q', term);

        if (filter && filter !== 'all') {
            params.set('filter', filter);
        }

        try {
            window.sessionStorage.setItem(FOCUS_KEY, '1');
            window.sessionStorage.setItem(CURSOR_KEY, String(input.selectionStart ?? input.value.length));
        } catch (e) {
            /* ignore */
        }

        const query = params.toString();
        const url = query ? base + (base.indexOf('?') >= 0 ? '&' : '?') + query : base;

        if (term.length >= 2) {
            push(term);
        }

        window.location.assign(url);
    }

    function restoreSearchFocus() {
        const input = document.getElementById('collection-search');

        if (!input) {
            return;
        }

        try {
            if (window.sessionStorage.getItem(FOCUS_KEY) !== '1') {
                return;
            }

            input.focus();
            const cursor = Number(window.sessionStorage.getItem(CURSOR_KEY));

            if (!Number.isNaN(cursor)) {
                input.setSelectionRange(cursor, cursor);
            }

            window.sessionStorage.removeItem(FOCUS_KEY);
            window.sessionStorage.removeItem(CURSOR_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function render() {
        const wrap = document.querySelector('[data-collection-recent]');
        const chips = document.querySelector('[data-collection-recent-chips]');

        if (!wrap || !chips) {
            return;
        }

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
                const input = document.getElementById('collection-search');

                if (input) {
                    input.value = btn.dataset.recent || '';
                }

                submitCollectionSearch();
            });
        });
    }

    function bindCollectionSearch() {
        const form = document.getElementById('collection-search-form');
        const input = document.getElementById('collection-search');

        if (!form || !input || input.dataset.collectionSearchBound === '1') {
            restoreSearchFocus();
            render();

            return;
        }

        input.dataset.collectionSearchBound = '1';

        let timer;

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(submitCollectionSearch, 500);
        });

        input.addEventListener('search', () => {
            window.clearTimeout(timer);
            submitCollectionSearch();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(timer);
            submitCollectionSearch();
        });

        restoreSearchFocus();
        render();
    }

    window.ispSubmitCollectionSearch = submitCollectionSearch;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindCollectionSearch);
    } else {
        bindCollectionSearch();
    }

    document.addEventListener('livewire:navigated', bindCollectionSearch);
})();
