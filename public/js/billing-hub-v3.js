/**
 * Billing hub v3 — tabs, instant search, pinned actions, search history.
 */
(function () {
    'use strict';

    const STORAGE_PINS = 'isp-billing-hub-pins';
    const STORAGE_SEARCH = 'isp-billing-search-history';
    const SMART_SEARCH = '/admin/smart-search';

    function initTabs(root) {
        const tabs = root.querySelectorAll('[data-bh-tab]');
        const panels = root.querySelectorAll('[data-bh-panel]');
        if (!tabs.length) return;

        const setTab = (id, pushUrl) => {
            tabs.forEach((t) => {
                t.classList.toggle('bh-tabs__btn--active', t.dataset.bhTab === id);
            });
            panels.forEach((p) => {
                const on = p.dataset.bhPanel === id;
                p.hidden = !on;
            });
            if (pushUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', id);
                window.history.replaceState({}, '', url);
            }
        };

        const initial = new URLSearchParams(window.location.search).get('tab') || 'overview';
        if (root.querySelector(`[data-bh-panel="${initial}"]`)) {
            setTab(initial, false);
        }

        tabs.forEach((btn) => {
            btn.addEventListener('click', () => setTab(btn.dataset.bhTab || 'overview', true));
        });
    }

    function initHubSearch(root) {
        const input = root.querySelector('[data-bh-hub-search]');
        const resultsEl = root.querySelector('[data-bh-hub-search-results]');
        if (!input || !resultsEl) return;

        let timer;

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = (input.value || '').trim();
            if (q.length < 2) {
                resultsEl.hidden = true;
                resultsEl.innerHTML = '';
                return;
            }
            timer = setTimeout(() => runSearch(q, resultsEl, input), 280);
        });

        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                const q = (input.value || '').trim();
                if (q.length >= 2) {
                    saveSearchHistory(q);
                    runSearch(q, resultsEl, input);
                }
            }
        });
    }

    async function runSearch(q, resultsEl, input) {
        resultsEl.hidden = false;
        resultsEl.innerHTML = '<p class="bh-hub-search__item" style="opacity:0.7">Searching…</p>';

        try {
            const res = await fetch(`${SMART_SEARCH}?q=${encodeURIComponent(q)}&limit=8`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('search failed');
            const data = await res.json();
            const items = data.results || data.data || data || [];
            const list = Array.isArray(items) ? items : [];

            if (list.length === 0) {
                resultsEl.innerHTML = '<p class="bh-hub-search__item" style="opacity:0.7">No results</p>';
                return;
            }

            resultsEl.innerHTML = list
                .slice(0, 8)
                .map((row) => {
                    const url = row.url || row.link || '#';
                    const title = row.title || row.label || row.name || '—';
                    const sub = row.subtitle || row.group || row.type || '';
                    return `<button type="button" class="bh-hub-search__item" data-url="${escapeAttr(url)}"><strong>${escapeHtml(title)}</strong><small>${escapeHtml(sub)}</small></button>`;
                })
                .join('');

            resultsEl.querySelectorAll('[data-url]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    saveSearchHistory(q);
                    const url = btn.dataset.url;
                    if (url && url !== '#') window.location.href = url;
                });
            });
        } catch (e) {
            resultsEl.innerHTML = '<p class="bh-hub-search__item" style="opacity:0.7">Search unavailable — use ⌘K</p>';
        }
    }

    function saveSearchHistory(q) {
        try {
            const hist = JSON.parse(localStorage.getItem(STORAGE_SEARCH) || '[]').filter((x) => x !== q);
            hist.unshift(q);
            localStorage.setItem(STORAGE_SEARCH, JSON.stringify(hist.slice(0, 8)));
        } catch (e) {
            /* ignore */
        }
    }

    function renderSearchHistory(root) {
        const el = root.querySelector('[data-bh-search-history]');
        const input = root.querySelector('[data-bh-hub-search]');
        if (!el) return;

        let hist = [];
        try {
            hist = JSON.parse(localStorage.getItem(STORAGE_SEARCH) || '[]');
        } catch (e) {
            hist = [];
        }

        if (!hist.length) {
            el.hidden = true;
            return;
        }

        el.hidden = false;
        el.innerHTML = hist
            .map(
                (q) =>
                    `<button type="button" class="bh-pin" data-history="${escapeAttr(q)}">${escapeHtml(q)}</button>`,
            )
            .join('');

        el.querySelectorAll('[data-history]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (input) {
                    input.value = btn.dataset.history || '';
                    input.dispatchEvent(new Event('input'));
                }
            });
        });
    }

    function initPins(root) {
        const row = root.querySelector('[data-bh-pins]');
        if (!row) return;

        const defaults = JSON.parse(row.dataset.bhDefaultPins || '[]');
        let pins = defaults;

        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_PINS) || 'null');
            if (Array.isArray(saved) && saved.length) pins = saved;
        } catch (e) {
            pins = defaults;
        }

        const render = () => {
            row.innerHTML = pins
                .map(
                    (p) =>
                        `<a href="${escapeAttr(p.url)}" class="bh-pin" data-pin-url="${escapeAttr(p.url)}">${escapeHtml(p.label)}</a>`,
                )
                .join('');
            row.innerHTML +=
                '<button type="button" class="bh-pin bh-pin--add" data-bh-pin-add title="Pin current tools tab item">+ Pin</button>';
        };

        render();

        row.addEventListener('click', (ev) => {
            const add = ev.target.closest('[data-bh-pin-add]');
            if (!add) return;
            const label = prompt('Pin label (e.g. Collect payment):');
            const url = prompt('URL to pin:');
            if (!label || !url) return;
            pins = [{ label: label.trim(), url: url.trim() }, ...pins].slice(0, 6);
            localStorage.setItem(STORAGE_PINS, JSON.stringify(pins));
            render();
        });
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }

    function init() {
        const root = document.querySelector('.bh-pro[data-bh-hub]');
        if (!root) return;

        initTabs(root);
        initHubSearch(root);
        renderSearchHistory(root);
        initPins(root);
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:navigated', init);
})();
