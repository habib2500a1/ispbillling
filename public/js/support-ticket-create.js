/**
 * Support ticket create — mobile drawer, search UX, keyboard on results.
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

    function bindResultKeyboard() {
        const list = document.querySelector('.sp-create-search .isp-collection-results ul[role="listbox"]');
        if (!list || list.dataset.keyboardBound === '1') {
            return;
        }

        list.dataset.keyboardBound = '1';
        const input = document.getElementById('support-ticket-subscriber-search');
        if (!input) {
            return;
        }

        input.addEventListener('keydown', function (event) {
            const items = Array.from(list.querySelectorAll('[role="option"] button'));
            if (items.length === 0) {
                return;
            }

            const active = list.querySelector('.sp-create-result-card--active');
            let index = active ? items.indexOf(active) : -1;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                index = Math.min(items.length - 1, index + 1);
                items[index]?.click();
                items[index]?.scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                index = Math.max(0, index <= 0 ? 0 : index - 1);
                items[index]?.click();
                items[index]?.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function init() {
        closeMobileSidebar();
        bindSearchEnter();
        bindResultKeyboard();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:initialized', function () {
        if (window.Livewire) {
            Livewire.hook('morph.updated', function () {
                bindSearchEnter();
                bindResultKeyboard();
            });
        }
    });
})();
