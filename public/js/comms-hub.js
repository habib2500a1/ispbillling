(function () {
    'use strict';

    function animateKpis() {
        document.querySelectorAll('[data-comms-kpi]').forEach((el) => {
            const target = parseInt(el.dataset.commsKpi || '0', 10);
            if (Number.isNaN(target)) {
                return;
            }
            let current = 0;
            const step = Math.max(1, Math.ceil(target / 20));
            const tick = () => {
                current = Math.min(target, current + step);
                el.textContent = String(current);
                if (current < target) {
                    requestAnimationFrame(tick);
                }
            };
            requestAnimationFrame(tick);
        });
    }

    function initBarChart() {
        document.querySelectorAll('[data-comms-bar]').forEach((bar) => {
            const h = parseInt(bar.dataset.commsBar || '0', 10);
            bar.style.height = Math.max(4, h) + 'px';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            initBarChart();

            return;
        }
        animateKpis();
        initBarChart();
    });

    document.addEventListener('livewire:navigated', () => {
        animateKpis();
        initBarChart();
    });
})();
