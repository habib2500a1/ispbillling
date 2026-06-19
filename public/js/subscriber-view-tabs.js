(function () {
    'use strict';

    var timelineVisible = 4;

    function capTimelineScroll(root) {
        var scope = root || document;
        scope.querySelectorAll('.sub-cc-timeline-wrap--scroll').forEach(function (wrap) {
            var list = wrap.querySelector('.sub-cc-timeline');
            var items = wrap.querySelectorAll('.sub-cc-timeline__item');
            if (!list || items.length <= timelineVisible) {
                wrap.style.maxHeight = '';
                return;
            }

            if (wrap.offsetParent === null && wrap.getClientRects().length === 0) {
                return;
            }

            var total = 0;
            for (var i = 0; i < timelineVisible; i++) {
                total += items[i].offsetHeight;
            }

            var gap = parseFloat(window.getComputedStyle(list).rowGap || window.getComputedStyle(list).gap || '0');
            if (!isNaN(gap) && gap > 0) {
                total += gap * (timelineVisible - 1);
            }

            wrap.style.maxHeight = total + 'px';
        });
    }

    function scheduleTimelineCap(root) {
        window.requestAnimationFrame(function () {
            capTimelineScroll(root);
        });
        window.setTimeout(function () {
            capTimelineScroll(root);
        }, 100);
    }

    function activate(root, tab) {
        const next = tab || 'overview';

        root.querySelectorAll('[data-sub-tab]').forEach((button) => {
            const active = button.getAttribute('data-sub-tab') === next;
            button.classList.toggle('sub-tabs__btn--active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[data-sub-pane]').forEach((pane) => {
            const active = pane.getAttribute('data-sub-pane') === next;
            pane.hidden = ! active;
            pane.style.display = active ? '' : 'none';
            pane.removeAttribute('x-cloak');
        });

        if (next === 'overview') {
            scheduleTimelineCap(root);
        }
    }

    function boot() {
        document.querySelectorAll('[data-sub-tabs-root]').forEach((root) => {
            if (root.dataset.subTabsBound === '1') {
                return;
            }

            root.dataset.subTabsBound = '1';
            const initial = window.location.hash.replace('#', '') || root.dataset.initialTab || 'overview';
            activate(root, initial);
            scheduleTimelineCap(root);

            root.querySelectorAll('[data-sub-tab]').forEach((button) => {
                button.addEventListener('click', () => {
                    const tab = button.getAttribute('data-sub-tab') || 'overview';
                    activate(root, tab);
                    if (history.replaceState) {
                        history.replaceState(null, '', '#' + tab);
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
    window.addEventListener('resize', function () {
        document.querySelectorAll('[data-sub-tabs-root]').forEach(function (root) {
            scheduleTimelineCap(root);
        });
    });
})();
