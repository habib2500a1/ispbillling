/**
 * Support hub v3 — tabs + URL sync.
 */
(function () {
    'use strict';

    function initTabs(root) {
        const tabs = root.querySelectorAll('[data-sh-tab]');
        const panels = root.querySelectorAll('[data-sh-panel]');
        if (!tabs.length) return;

        const setTab = (id, pushUrl) => {
            tabs.forEach((t) => {
                t.classList.toggle('sh-tabs__btn--active', t.dataset.shTab === id);
            });
            panels.forEach((p) => {
                p.hidden = p.dataset.shPanel !== id;
            });
            if (pushUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', id);
                window.history.replaceState({}, '', url);
            }
        };

        const initial = new URLSearchParams(window.location.search).get('tab') || 'overview';
        if (root.querySelector(`[data-sh-panel="${initial}"]`)) {
            setTab(initial, false);
        }

        tabs.forEach((btn) => {
            btn.addEventListener('click', () => setTab(btn.dataset.shTab || 'overview', true));
        });
    }

    function init() {
        const root = document.querySelector('.sh-pro[data-sh-hub]');
        if (!root) return;
        initTabs(root);
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:navigated', init);
})();
