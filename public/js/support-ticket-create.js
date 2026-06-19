/**
 * Support ticket create — mobile drawer, live search sync, keyboard on results.
 */
(function () {
    'use strict';

    const INPUT_SELECTOR = '#support-ticket-subscriber-search';
    let debounceTimer = null;

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

    function findCreateComponent() {
        if (!window.Livewire) {
            return null;
        }

        const input = document.querySelector(INPUT_SELECTOR);
        if (input) {
            const root = input.closest('[wire\\:id]');
            if (root) {
                const fromDom = window.Livewire.find(root.getAttribute('wire:id'));
                if (fromDom) {
                    return fromDom;
                }
            }
        }

        return window.Livewire.all().find(function (component) {
            return typeof component.runSubscriberSearch === 'function';
        }) ?? null;
    }

    function syncLiveSearch(input) {
        if (!input) {
            return;
        }

        const component = findCreateComponent();
        if (!component) {
            return;
        }

        const value = (input.value || '').trim();

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            component.set('subscriberSearch', value);
            component.call('runSubscriberSearch');
        }, 280);
    }

    function bindSearchEnter() {
        const root = document.querySelector('.isp-support-subscriber-search');
        const input = document.querySelector(INPUT_SELECTOR);
        if (!root || !input || input.dataset.createBound === '1') {
            return;
        }

        input.dataset.createBound = '1';

        input.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            clearTimeout(debounceTimer);

            const component = findCreateComponent();
            if (component) {
                component.set('subscriberSearch', (input.value || '').trim());
                component.call('runSubscriberSearch');

                return;
            }

            const btn = root.querySelector('[wire\\:click="runSubscriberSearch"]');
            btn?.click();
        });
    }

    function bindResultKeyboard() {
        const list = document.querySelector('.sp-create-search .isp-collection-results ul[role="listbox"]');
        if (!list || list.dataset.keyboardBound === '1') {
            return;
        }

        list.dataset.keyboardBound = '1';
        const input = document.querySelector(INPUT_SELECTOR);
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

    document.addEventListener('input', function (event) {
        const input = event.target;
        if (!input?.matches?.(INPUT_SELECTOR)) {
            return;
        }

        syncLiveSearch(input);
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:initialized', function () {
        if (window.Livewire) {
            Livewire.hook('morph.updated', init);
        }
    });
})();
