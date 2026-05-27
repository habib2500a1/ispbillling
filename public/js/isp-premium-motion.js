/**
 * Premium motion layer — CSS + vanilla JS (Framer Motion alternative for Blade/Livewire).
 */
(function () {
    'use strict';

    var loaderEl = null;
    var loaderBar = null;
    var progress = 0;
    var progressTimer = null;

    function ensureLoader() {
        if (loaderEl) return;
        loaderEl = document.createElement('div');
        loaderEl.className = 'isp-premium-loader';
        loaderEl.setAttribute('aria-hidden', 'true');
        loaderEl.innerHTML = '<div class="isp-premium-loader__bar"></div>';
        document.body.prepend(loaderEl);
        loaderBar = loaderEl.querySelector('.isp-premium-loader__bar');
    }

    function startLoader() {
        ensureLoader();
        progress = 8;
        loaderEl.classList.add('is-active');
        if (loaderBar) loaderBar.style.width = progress + '%';
        clearInterval(progressTimer);
        progressTimer = setInterval(function () {
            if (progress < 85) {
                progress += Math.random() * 12;
                if (progress > 85) progress = 85;
                if (loaderBar) loaderBar.style.width = progress + '%';
            }
        }, 280);
    }

    function finishLoader() {
        if (!loaderEl) return;
        clearInterval(progressTimer);
        progress = 100;
        if (loaderBar) loaderBar.style.width = '100%';
        setTimeout(function () {
            loaderEl.classList.remove('is-active');
            if (loaderBar) loaderBar.style.width = '0%';
            progress = 0;
        }, 350);
    }

    function revealElements(root) {
        var scope = root || document;
        var nodes = scope.querySelectorAll(
            '.fi-section, .fi-wi-stats-overview-stat, .fi-wi-widget, .fi-wi-chart, ' +
            '.portal-pro-card, .portal-card, .portal-summary-card, .portal-panel, ' +
            '.isp-hub-section, .isp-hub-hero, .isp-dash-hub-card, .isp-unified-section, ' +
            '.isp-kpi-wall, .isp-glass-float, .isp-ops-center, .portal-auth-panel, ' +
            '.fi-ta-ctn, .rsl-card, .bp-card'
        );
        nodes.forEach(function (el, i) {
            if (el.classList.contains('isp-premium-reveal')) return;
            el.classList.add('isp-premium-reveal');
            el.style.transitionDelay = Math.min(i * 0.05, 0.35) + 's';
        });

        if (!('IntersectionObserver' in window)) {
            scope.querySelectorAll('.isp-premium-reveal').forEach(function (el) {
                el.classList.add('is-visible');
            });
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: '0px 0px -40px 0px', threshold: 0.08 }
        );

        scope.querySelectorAll('.isp-premium-reveal:not(.is-visible)').forEach(function (el) {
            observer.observe(el);
        });
    }

    function init() {
        ensureLoader();
        revealElements(document);
        finishLoader();
    }

    document.addEventListener('DOMContentLoaded', init);

    document.addEventListener('livewire:navigating', startLoader);
    document.addEventListener('livewire:navigated', function () {
        finishLoader();
        revealElements(document);
    });

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) finishLoader();
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(init, 0);
    }
})();
