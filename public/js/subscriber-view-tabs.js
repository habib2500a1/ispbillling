(function () {
    'use strict';

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
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
})();
