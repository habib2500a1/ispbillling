/**
 * Admin sidebar: sticky layout, single active item, group expand, scroll active link.
 */
(function () {
    const DESKTOP_MQ = '(min-width: 1024px)';

    function sidebarStore() {
        return window.Alpine?.store?.('sidebar');
    }

    function isDesktop() {
        return window.matchMedia(DESKTOP_MQ).matches;
    }

    function syncBodyClasses() {
        const store = sidebarStore();
        if (!store) {
            return;
        }

        const open = Boolean(store.isOpen);
        const desktop = isDesktop();

        document.body.classList.toggle('isp-admin-sidebar-open', !desktop && open);
        document.body.classList.toggle('isp-sidebar-desktop-collapsed', desktop && !open);
        document.body.classList.toggle('isp-sidebar-desktop-expanded', desktop && open);
    }

    function menuScroller() {
        return document.querySelector('.fi-main-sidebar .fi-sidebar-nav-groups');
    }

    function normalizeSidebarSelection() {
        const root = document.querySelector('.fi-main-sidebar');
        if (!root) {
            return null;
        }

        const pathname = window.location.pathname;
        const items = [...root.querySelectorAll('.fi-sidebar-nav-groups .fi-sidebar-item')];
        let best = null;
        let bestLen = -1;

        for (const item of items) {
            const link = item.querySelector('a.fi-sidebar-item-button[href]');
            if (!link) {
                continue;
            }

            let path;
            try {
                path = new URL(link.getAttribute('href'), window.location.origin).pathname;
            } catch (e) {
                continue;
            }

            if (path === '/' || path === '') {
                continue;
            }

            const exact = pathname === path;
            const nested = pathname.startsWith(path.endsWith('/') ? path : path + '/');

            if (!exact && !nested) {
                continue;
            }

            if (path.length > bestLen) {
                bestLen = path.length;
                best = item;
            }
        }

        items.forEach((item) => {
            const active = item === best;
            item.classList.toggle('fi-active', active);
            item.classList.toggle('fi-sidebar-item-active', active);
        });

        root.querySelectorAll('.fi-sidebar-group').forEach((group) => {
            const hasActive = Boolean(group.querySelector('.fi-sidebar-item.fi-active'));
            group.classList.toggle('fi-active', hasActive);
        });

        return best;
    }

    function expandGroupForActiveItem(activeItem) {
        const store = sidebarStore();
        if (!store || !activeItem) {
            return null;
        }

        const group = activeItem.closest('.fi-sidebar-group[data-group-label]');
        if (!group) {
            return null;
        }

        const label = group.dataset.groupLabel;
        if (!label) {
            return null;
        }

        if (!Array.isArray(store.collapsedGroups)) {
            store.collapsedGroups = [];
        }

        if (store.collapsedGroups.includes(label)) {
            store.toggleCollapsedGroup(label);
        }

        try {
            sessionStorage.setItem('isp-sidebar-last-group', label);
        } catch (e) {
            /* ignore */
        }

        return { activeItem, group, label };
    }

    function scrollActiveIntoView() {
        const scroller = menuScroller();
        const activeItem = normalizeSidebarSelection() || document.querySelector('.fi-main-sidebar .fi-sidebar-item.fi-active');

        if (!scroller || !activeItem || activeItem.offsetParent === null) {
            return;
        }

        expandGroupForActiveItem(activeItem);

        requestAnimationFrame(() => {
            const scrollerRect = scroller.getBoundingClientRect();
            const itemRect = activeItem.getBoundingClientRect();
            const delta = itemRect.top - scrollerRect.top - scroller.clientHeight / 2 + itemRect.height / 2;

            scroller.scrollBy({
                top: delta,
                behavior: 'smooth',
            });
        });
    }

    function restoreModuleOnExpand() {
        const store = sidebarStore();
        if (!store?.isOpen || !isDesktop()) {
            return;
        }

        let label = null;

        try {
            label = sessionStorage.getItem('isp-sidebar-last-group');
        } catch (e) {
            /* ignore */
        }

        if (!label || !Array.isArray(store.collapsedGroups)) {
            return;
        }

        if (store.collapsedGroups.includes(label)) {
            store.toggleCollapsedGroup(label);
        }
    }

    function bindStore() {
        const store = sidebarStore();
        if (!store || store.__ispLayoutBound) {
            return;
        }

        store.__ispLayoutBound = true;

        const originalOpen = store.open.bind(store);
        const originalClose = store.close.bind(store);

        store.open = function () {
            originalOpen();
            syncBodyClasses();
            restoreModuleOnExpand();
            requestAnimationFrame(scrollActiveIntoView);
        };

        store.close = function () {
            originalClose();
            syncBodyClasses();
        };
    }

    function watchSidebarOpen() {
        const store = sidebarStore();
        if (!store || store.__ispOpenWatch) {
            return;
        }

        store.__ispOpenWatch = true;

        if (typeof Alpine !== 'undefined' && typeof Alpine.effect === 'function') {
            Alpine.effect(() => {
                void Alpine.store('sidebar').isOpen;
                syncBodyClasses();
            });
        }
    }

    function init() {
        bindStore();
        watchSidebarOpen();
        syncBodyClasses();
        scrollActiveIntoView();
    }

    document.addEventListener('alpine:init', init);
    document.addEventListener('DOMContentLoaded', () => setTimeout(init, 50));
    document.addEventListener('livewire:navigated', () => {
        syncBodyClasses();
        setTimeout(scrollActiveIntoView, 80);
    });

    window.addEventListener('resize', syncBodyClasses);
})();
