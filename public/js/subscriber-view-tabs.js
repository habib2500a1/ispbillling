(function () {
    'use strict';

    var timelineVisibleDefault = 4;

    function capTimelineScroll() {
        document.querySelectorAll('.sub-cc-timeline-wrap--scroll').forEach(function (wrap) {
            var list = wrap.querySelector('.sub-cc-timeline');
            if (!list) {
                return;
            }

            var items = list.querySelectorAll('.sub-cc-timeline__item');
            var visible = parseInt(wrap.getAttribute('data-sub-timeline-visible') || String(timelineVisibleDefault), 10);

            if (items.length <= visible) {
                list.style.removeProperty('max-height');
                list.scrollTop = 0;
                return;
            }

            var height = 0;
            var gap = 0;
            var style = window.getComputedStyle(list);
            if (style.display === 'flex') {
                gap = parseFloat(style.rowGap || style.gap || '0') || 0;
            }

            for (var i = 0; i < visible && i < items.length; i++) {
                if (i > 0) {
                    height += gap;
                }
                height += items[i].getBoundingClientRect().height;
            }

            if (height > 0) {
                list.style.setProperty('max-height', height + 'px', 'important');
            }

            list.scrollTop = 0;
        });
    }

    function scheduleTimelineCap() {
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(capTimelineScroll);
        });
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
    }

    function boot() {
        document.querySelectorAll('[data-sub-tabs-root]').forEach((root) => {
            if (root.dataset.subTabsBound === '1') {
                scheduleTimelineCap();
                return;
            }

            root.dataset.subTabsBound = '1';
            const initial = window.location.hash.replace('#', '') || root.dataset.initialTab || 'overview';
            activate(root, initial);

            root.querySelectorAll('[data-sub-tab]').forEach((button) => {
                button.addEventListener('click', () => {
                    const tab = button.getAttribute('data-sub-tab') || 'overview';
                    activate(root, tab);
                    if (history.replaceState) {
                        history.replaceState(null, '', '#' + tab);
                    }
                    scheduleTimelineCap();
                });
            });
        });

        scheduleTimelineCap();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
    window.addEventListener('resize', scheduleTimelineCap);
    window.addEventListener('orientationchange', scheduleTimelineCap);
})();
