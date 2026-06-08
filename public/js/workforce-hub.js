(function () {
    'use strict';

    function animateKpis() {
        document.querySelectorAll('[data-wf-kpi]').forEach(function (el) {
            var target = parseInt(el.getAttribute('data-wf-kpi'), 10) || 0;
            if (target <= 0) {
                return;
            }
            var start = 0;
            var duration = 500;
            var startTime = null;

            function step(ts) {
                if (!startTime) {
                    startTime = ts;
                }
                var p = Math.min((ts - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(start + (target - start) * eased).toLocaleString();
                if (p < 1) {
                    requestAnimationFrame(step);
                }
            }

            requestAnimationFrame(step);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', animateKpis);
    } else {
        animateKpis();
    }

    document.addEventListener('livewire:navigated', animateKpis);

    if (window.Livewire) {
        Livewire.hook('morph.updated', function () {
            if (document.querySelector('.isp-wf-page')) {
                animateKpis();
            }
        });
    }
})();
