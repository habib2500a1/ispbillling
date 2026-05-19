/**
 * Mobile: sync body class when sidebar drawer opens. Desktop collapse is user-controlled.
 */
(function () {
    function syncBodyClass() {
        const store = window.Alpine?.store?.('sidebar');
        if (!store) {
            return;
        }

        document.body.classList.toggle('isp-admin-sidebar-open', store.isOpen);
    }

    document.addEventListener('alpine:init', () => {
        const store = window.Alpine.store('sidebar');
        syncBodyClass();
        window.addEventListener('resize', syncBodyClass);

        if (store && typeof store.open === 'function') {
            const originalOpen = store.open.bind(store);
            const originalClose = store.close.bind(store);

            store.open = function () {
                originalOpen();
                syncBodyClass();
            };

            store.close = function () {
                originalClose();
                syncBodyClass();
            };
        }
    });
})();
