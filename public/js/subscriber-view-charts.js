(function () {
    var activeUsagePeriod = 'day';
    var resizeTimer = null;
    var timelineVisibleDefault = 4;

    function parseJson(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent || '{}'); } catch (e) { return null; }
    }

    function chartHeight() {
        if (window.matchMedia('(max-width: 640px)').matches) {
            return 84;
        }
        if (window.matchMedia('(max-width: 1023px)').matches) {
            return 96;
        }
        return 120;
    }

    function canvasWidth(canvas, fallback) {
        var width = canvas.offsetWidth || canvas.parentElement?.offsetWidth || fallback || 320;
        return Math.max(240, width);
    }

    function drawSparkline(canvasId, dataId, color) {
        var canvas = document.getElementById(canvasId);
        var data = parseJson(dataId);
        if (!canvas || !data || !data.rx || data.rx.length === 0) return;

        var values = data.rx.filter(function (v) { return v !== null && !isNaN(v); }).map(Number);
        if (values.length === 0) return;

        var ctx = canvas.getContext('2d');
        var width = canvasWidth(canvas);
        var height = parseInt(canvas.getAttribute('height') || '64', 10);
        var ratio = window.devicePixelRatio || 1;

        canvas.width = width * ratio;
        canvas.height = height * ratio;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        var min = Math.min.apply(null, values);
        var max = Math.max.apply(null, values);
        var pad = (max - min) * 0.1 || 1;

        ctx.clearRect(0, 0, width, height);
        ctx.beginPath();
        ctx.strokeStyle = color || '#2563eb';
        ctx.lineWidth = 2;

        values.forEach(function (v, i) {
            var x = (i / Math.max(1, values.length - 1)) * (width - 8) + 4;
            var y = height - 4 - ((v - min + pad) / (max - min + pad * 2)) * (height - 8);
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.stroke();
    }

    function drawUsageChart(period) {
        var canvas = document.getElementById('sub-usage-chart');
        var all = parseJson('sub-usage-data');
        if (!canvas || !all) return;

        activeUsagePeriod = period || activeUsagePeriod || 'day';
        var data = all[activeUsagePeriod] || all.day || { labels: [], download_gb: [], upload_gb: [] };
        var ctx = canvas.getContext('2d');
        var width = canvasWidth(canvas);
        var height = chartHeight();
        var ratio = window.devicePixelRatio || 1;

        canvas.width = width * ratio;
        canvas.height = height * ratio;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        var downloads = (data.download_gb || []).map(Number);
        var max = Math.max.apply(null, downloads.concat([0.01]));
        var count = Math.max(1, downloads.length);
        var barW = Math.max(6, (width - 16) / count - 3);

        ctx.clearRect(0, 0, width, height);

        downloads.forEach(function (v, i) {
            var barH = (v / max) * (height - 28);
            var x = 8 + i * (barW + 3);
            var y = height - 10 - barH;
            ctx.fillStyle = '#3b82f6';
            ctx.fillRect(x, y, barW, barH);
        });
    }

    function bindUsageTabs() {
        var panel = document.querySelector('[data-sub-usage-panel]');
        if (!panel) return;
        panel.querySelectorAll('[data-usage-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                panel.querySelectorAll('[data-usage-tab]').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                drawUsageChart(btn.getAttribute('data-usage-tab'));
            });
        });
    }

    function redrawCharts() {
        drawSparkline('sub-onu-sparkline', 'sub-onu-spark-data', '#7c3aed');
        drawUsageChart(activeUsagePeriod);
    }

    function scheduleRedraw() {
        if (resizeTimer) window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(function () {
            redrawCharts();
            capTimelineScroll();
        }, 120);
    }

    function capTimelineScroll() {
        document.querySelectorAll('[data-sub-timeline-wrap]').forEach(function (wrap) {
            var list = wrap.querySelector('.sub-cc-timeline');
            var items = list ? list.querySelectorAll('.sub-cc-timeline__item') : [];
            var visible = parseInt(wrap.getAttribute('data-sub-timeline-visible') || String(timelineVisibleDefault), 10);

            if (!list || items.length === 0) {
                wrap.classList.remove('sub-cc-timeline-wrap--scroll');
                wrap.style.maxHeight = '';
                return;
            }

            if (items.length <= visible) {
                wrap.classList.remove('sub-cc-timeline-wrap--scroll');
                wrap.style.maxHeight = '';
                return;
            }

            wrap.classList.add('sub-cc-timeline-wrap--scroll');

            var total = 0;
            for (var i = 0; i < visible; i++) {
                total += items[i].getBoundingClientRect().height;
            }

            var listStyles = window.getComputedStyle(list);
            var gap = parseFloat(listStyles.rowGap || listStyles.gap || '0');
            if (!isNaN(gap) && gap > 0) {
                total += gap * (visible - 1);
            }

            var wrapStyles = window.getComputedStyle(wrap);
            total += parseFloat(wrapStyles.paddingTop) + parseFloat(wrapStyles.paddingBottom);

            wrap.style.maxHeight = Math.ceil(total) + 'px';
        });
    }

    function scheduleTimelineCap() {
        window.requestAnimationFrame(capTimelineScroll);
        window.setTimeout(capTimelineScroll, 120);
    }

    function init() {
        activeUsagePeriod = 'day';
        var activeBtn = document.querySelector('[data-sub-usage-panel] [data-usage-tab].is-active');
        if (activeBtn) {
            activeUsagePeriod = activeBtn.getAttribute('data-usage-tab') || 'day';
        }
        redrawCharts();
        bindUsageTabs();
        scheduleTimelineCap();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
    window.addEventListener('resize', scheduleRedraw);
    window.addEventListener('orientationchange', scheduleRedraw);
})();
