/**
 * Inventory Asset Intelligence — lightweight UI enhancements (no API changes).
 */
(function () {
    'use strict';

    function markInventoryModule() {
        const path = window.location.pathname || '';
        if (/inventory-hub|warehouses|products|inventory-sales|purchase-orders|stock-movements|vendors|fixed-assets|store-device-loans|devices/.test(path)) {
            document.body.classList.add('isp-inventory-module');
        }
    }

    function animateLifecycleCounts(root) {
        root.querySelectorAll('.iv-lifecycle__stage[data-count]').forEach(function (stage) {
            const target = parseInt(stage.getAttribute('data-count') || '0', 10);
            const el = stage.querySelector('.iv-lifecycle__count');
            if (!el || target <= 0) {
                return;
            }

            let current = 0;
            const steps = 18;
            const increment = Math.max(1, Math.ceil(target / steps));
            const timer = window.setInterval(function () {
                current = Math.min(target, current + increment);
                el.textContent = current.toLocaleString();
                if (current >= target) {
                    window.clearInterval(timer);
                }
            }, 30);
        });
    }

    function init() {
        markInventoryModule();
        const lifecycle = document.querySelector('[data-iv-lifecycle]');
        if (lifecycle) {
            animateLifecycleCounts(lifecycle);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
})();
