(function () {
    'use strict';

    var timelineVisible = 4;

    function capTimelineScroll(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-sub-timeline-cap], .sub-cc-timeline-wrap--scroll').forEach(function (wrap) {
            var visible = parseInt(wrap.getAttribute('data-sub-timeline-cap') || String(timelineVisible), 10);
            var list = wrap.querySelector('.sub-cc-timeline');
            var items = list ? list.querySelectorAll('.sub-cc-timeline__item') : [];
            if (!list || items.length <= visible) {
                wrap.style.removeProperty('max-height');
                return;
            }

            var total = 0;
            for (var i = 0; i < visible; i++) {
                total += items[i].offsetHeight;
            }

            var gap = parseFloat(window.getComputedStyle(list).rowGap || window.getComputedStyle(list).gap || '0');
            if (!isNaN(gap) && gap > 0) {
                total += gap * (visible - 1);
            }

            wrap.style.setProperty('max-height', total + 'px', 'important');
            wrap.style.setProperty('overflow-y', 'auto', 'important');
        });
    }

    function scheduleTimelineCap(root) {
        window.requestAnimationFrame(function () {
            capTimelineScroll(root);
        });
        window.setTimeout(function () {
            capTimelineScroll(root);
        }, 150);
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

    function bindTabs(root) {
        if (root.dataset.subTabsBound === '1') {
            return;
        }

        root.dataset.subTabsBound = '1';

        root.querySelectorAll('[data-sub-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-sub-tab') || 'overview';
                activate(root, tab);
                if (history.replaceState) {
                    history.replaceState(null, '', '#' + tab);
                }
            });
        });
    }

    function boot() {
        document.querySelectorAll('[data-sub-tabs-root]').forEach((root) => {
            bindTabs(root);
            const initial = window.location.hash.replace('#', '') || root.dataset.initialTab || 'overview';
            activate(root, initial);
            scheduleTimelineCap(root);
        });

        capTimelineScroll(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
    window.addEventListener('resize', function () {
        capTimelineScroll(document);
    });
    window.addEventListener('load', function () {
        capTimelineScroll(document);
    });
})();
