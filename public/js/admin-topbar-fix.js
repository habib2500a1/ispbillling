/**
 * Remove duplicate Livewire x-persist topbar.end nodes (PC profile menu appearing in page body).
 */
(function () {
    'use strict';

    function removeOrphanTopbarPersist() {
        const liveEnd = document.querySelector('.fi-topbar .fi-topbar-end');

        if (!liveEnd) {
            return;
        }

        document.querySelectorAll('[x-persist*="topbar.end"]').forEach((node) => {
            if (node === liveEnd || liveEnd.contains(node)) {
                return;
            }

            node.remove();
        });
    }

    function init() {
        removeOrphanTopbarPersist();
    }

    document.addEventListener('livewire:navigated', () => {
        window.setTimeout(removeOrphanTopbarPersist, 0);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
