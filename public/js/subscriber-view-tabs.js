(function () {
    'use strict';

    function resetTimelineLayout() {
        document.querySelectorAll('.sub-cc-panel--timeline .sub-cc-timeline, .sub-cc-timeline-wrap').forEach(function (el) {
            el.style.removeProperty('max-height');
            el.style.removeProperty('min-height');
            el.style.removeProperty('height');
            el.style.removeProperty('overflow');
        });
    }

    function activate(root, tab) {
        const next = tab || 'overview';

        root.querySelectorAll('[data-sub-tab]').forEach((button) => {
            const active = button.getAttribute('data-sub-tab') === next;
            button.classList.toggle('sub-tabs__btn--active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[data-sub-pane]').forEach((pane) => {
            const active = pane.getAttribute('data-sub-pane') === next;
            pane.hidden = ! active;
            pane.style.display = active ? '' : 'none';
            pane.removeAttribute('x-cloak');
        });
    }

    function boot() {
        document.querySelectorAll('[data-sub-tabs-root]').forEach((root) => {
            if (root.dataset.subTabsBound === '1') {
                return;
            }

            root.dataset.subTabsBound = '1';
            const initial = window.location.hash.replace('#', '') || root.dataset.initialTab || 'overview';
            activate(root, initial);

            root.querySelectorAll('[data-sub-tab]').forEach((button) => {
                button.addEventListener('click', () => {
                    const tab = button.getAttribute('data-sub-tab') || 'overview';
                    activate(root, tab);
                    if (history.replaceState) {
                        history.replaceState(null, '', '#' + tab);
                    }
                    resetTimelineLayout();
                });
            });
        });

        resetTimelineLayout();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
    window.addEventListener('resize', resetTimelineLayout);
})();
