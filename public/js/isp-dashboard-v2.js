/**
 * ISP Dashboard v2 — lightweight perf helpers (no dependencies).
 */
(function () {
    'use strict';

    if (!document.querySelector('[data-isp-dashboard="1"]')) {
        return;
    }

    /** Collapse heavy below-fold zones on mobile until expanded. */
    function initMobileAccordions() {
        document.querySelectorAll('[data-isp-dash-accordion]').forEach(function (root) {
            if (root.dataset.ispDashAccordionInit) {
                return;
            }
            root.dataset.ispDashAccordionInit = '1';

            var summary = root.querySelector('[data-isp-dash-accordion-summary]');
            var body = root.querySelector('[data-isp-dash-accordion-body]');
            if (!summary || !body) {
                return;
            }

            function sync() {
                var mobile = window.matchMedia('(max-width: 767px)').matches;
                root.classList.toggle('isp-dash-accordion--mobile', mobile);
                if (!mobile) {
                    body.hidden = false;
                    summary.setAttribute('aria-expanded', 'true');
                } else if (!root.dataset.ispDashAccordionMobileInit) {
                    body.hidden = true;
                    summary.setAttribute('aria-expanded', 'false');
                    root.dataset.ispDashAccordionMobileInit = '1';
                }
            }

            summary.addEventListener('click', function () {
                if (!window.matchMedia('(max-width: 767px)').matches) {
                    return;
                }
                var open = body.hidden;
                body.hidden = !open;
                summary.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            sync();
            window.addEventListener('resize', sync, { passive: true });
        });
    }

    /** Defer paint of below-fold analytics until near viewport. */
    function initLazyReveal() {
        document.querySelectorAll('[data-isp-lazy-reveal]').forEach(function (el) {
            if (el.dataset.ispLazyRevealInit) {
                return;
            }
            el.dataset.ispLazyRevealInit = '1';
            el.classList.add('isp-lazy-reveal--pending');

            if (!('IntersectionObserver' in window)) {
                el.classList.remove('isp-lazy-reveal--pending');
                return;
            }

            var observer = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            el.classList.remove('isp-lazy-reveal--pending');
                            observer.disconnect();
                        }
                    });
                },
                { rootMargin: '120px 0px', threshold: 0.01 },
            );

            observer.observe(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initMobileAccordions();
            initLazyReveal();
        });
    } else {
        initMobileAccordions();
        initLazyReveal();
    }

    document.addEventListener('livewire:navigated', function () {
        initMobileAccordions();
        initLazyReveal();
    });
})();
