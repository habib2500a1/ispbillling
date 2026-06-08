(function () {
    'use strict';

    function animateKpis() {
        document.querySelectorAll('.torg-kpi strong').forEach(function (el) {
            var raw = (el.textContent || '').replace(/[^\d.]/g, '');
            var target = parseFloat(raw) || 0;
            if (target <= 0) {
                return;
            }
            var suffix = (el.textContent || '').replace(/[\d.,]/g, '').trim();
            var start = 0;
            var duration = 500;
            var startTime = null;

            function step(ts) {
                if (!startTime) {
                    startTime = ts;
                }
                var p = Math.min((ts - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                var value = Math.round(start + (target - start) * eased);
                el.textContent = value.toLocaleString() + (suffix ? ' ' + suffix : '');
                if (p < 1) {
                    requestAnimationFrame(step);
                }
            }

            requestAnimationFrame(step);
        });
    }

    function closeSearchOnOutsideClick(event) {
        var wrap = document.querySelector('.torg-search');
        if (!wrap || wrap.contains(event.target)) {
            return;
        }
        var input = wrap.querySelector('input');
        if (input && input.value.length >= 2) {
            input.blur();
        }
    }

    function init() {
        animateKpis();
        document.addEventListener('click', closeSearchOnOutsideClick);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', animateKpis);

    if (window.Livewire) {
        Livewire.hook('morph.updated', function () {
            if (document.querySelector('.torg-page')) {
                animateKpis();
            }
        });
    }
})();
