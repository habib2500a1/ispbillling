/**
 * Mobile sidebar body-class sync.
 * Keeps page scroll locked only while the mobile drawer is actually open.
 */
(function () {
    'use strict';

    let syncScheduled = false;

    function isMobileViewport() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function getSidebar() {
        return document.querySelector('.fi-sidebar');
    }

    function isSidebarOpen() {
        const sidebar = getSidebar();
        const alpineOpen = window.Alpine?.store?.('sidebar')?.isOpen;

        return Boolean(
            alpineOpen ||
            sidebar?.classList.contains('fi-sidebar-open') ||
            sidebar?.classList.contains('is-open')
        );
    }

    function syncSidebarBodyClass() {
        syncScheduled = false;

        const shouldLock = isMobileViewport() && isSidebarOpen();
        document.body.classList.toggle('isp-admin-sidebar-open', shouldLock);
        document.body.classList.toggle('isp-mobile-detected', isMobileViewport());
    }

    function scheduleSync(delay = 0) {
        if (syncScheduled) {
            return;
        }

        syncScheduled = true;
        window.setTimeout(syncSidebarBodyClass, delay);
    }

    function watchSidebar() {
        const observer = new MutationObserver(function (mutations) {
            for (const mutation of mutations) {
                if (
                    mutation.type === 'attributes' &&
                    (mutation.attributeName === 'class' || mutation.attributeName === 'style')
                ) {
                    scheduleSync(0);
                    return;
                }
            }
        });

        observer.observe(document.body, {
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style'],
        });
    }

    function init() {
        syncSidebarBodyClass();
        watchSidebar();

        window.addEventListener('resize', function () {
            scheduleSync(50);
        });

        document.addEventListener('click', function () {
            scheduleSync(25);
        }, true);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                scheduleSync(25);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
