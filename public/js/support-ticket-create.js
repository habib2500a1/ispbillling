/**
 * Support ticket create — close mobile drawer, keep search usable.
 */
(function () {
    'use strict';

    function isMobile() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function closeMobileSidebar() {
        if (!isMobile()) {
            return;
        }

        const store = window.Alpine?.store?.('sidebar');
        if (store && typeof store.close === 'function') {
            store.close();
        }

        document.body.classList.remove('isp-admin-sidebar-open');
    }

    function bindSearchEnter() {
        const root = document.querySelector('.isp-support-subscriber-search');
        const input = document.getElementById('support-ticket-subscriber-search');
        if (!root || !input || input.dataset.createBound === '1') {
            return;
        }

        input.dataset.createBound = '1';

        input.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            const btn = root.querySelector('[wire\\:click="runSubscriberSearch"]');
            if (btn) {
                btn.click();
            }
        });
    }

    function init() {
        closeMobileSidebar();
        bindSearchEnter();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
})();
