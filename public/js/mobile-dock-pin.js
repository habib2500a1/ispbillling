/**
 * Keep .isp-mobile-bar fixed at the viewport bottom on mobile.
 * Re-runs after Livewire navigate (DOMContentLoaded does not fire again).
 */
(function () {
    'use strict';

    function isMobileViewport() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function pinMobileBar() {
        const bar = document.querySelector('.isp-mobile-bar');
        if (!bar) {
            return;
        }

        if (bar.closest('.fi-layout, .fi-main-ctn, .fi-main') && bar.parentElement !== document.body) {
            document.body.appendChild(bar);
        }

        if (!isMobileViewport()) {
            return;
        }

        bar.style.setProperty('position', 'fixed', 'important');
        bar.style.setProperty('bottom', '0', 'important');
        bar.style.setProperty('left', '0', 'important');
        bar.style.setProperty('right', '0', 'important');
        bar.style.setProperty('z-index', '55', 'important');

        const height = Math.ceil(bar.getBoundingClientRect().height);
        if (height > 0) {
            document.documentElement.style.setProperty('--isp-mobile-bar-height', height + 'px');
        }
    }

    function init() {
        pinMobileBar();

        if (typeof ResizeObserver !== 'undefined') {
            const bar = document.querySelector('.isp-mobile-bar');
            if (bar && !bar.dataset.ispDockObserved) {
                bar.dataset.ispDockObserved = '1';
                new ResizeObserver(pinMobileBar).observe(bar);
            }
        }
    }

    window.addEventListener('resize', pinMobileBar, { passive: true });
    document.addEventListener('livewire:navigated', function () {
        window.setTimeout(init, 0);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
