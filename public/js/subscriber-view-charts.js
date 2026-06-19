(function () {
    function parseJson(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent || '{}'); } catch (e) { return null; }
    }

    function drawSparkline(canvasId, dataId, color) {
        var canvas = document.getElementById(canvasId);
        var data = parseJson(dataId);
        if (!canvas || !data || !data.rx || data.rx.length === 0) return;

        var values = data.rx.filter(function (v) { return v !== null && !isNaN(v); }).map(Number);
        if (values.length === 0) return;

        var ctx = canvas.getContext('2d');
        var w = canvas.width = canvas.offsetWidth * (window.devicePixelRatio || 1);
        var h = canvas.height = (canvas.getAttribute('height') || 64) * (window.devicePixelRatio || 1);
        ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);
        var width = canvas.offsetWidth;
        var height = parseInt(canvas.getAttribute('height') || '64', 10);

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

        var data = all[period] || all.day || { labels: [], download_gb: [], upload_gb: [] };
        var ctx = canvas.getContext('2d');
        var width = canvas.offsetWidth;
        var height = parseInt(canvas.getAttribute('height') || '120', 10);
        canvas.width = width * (window.devicePixelRatio || 1);
        canvas.height = height * (window.devicePixelRatio || 1);
        ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);

        var downloads = (data.download_gb || []).map(Number);
        var max = Math.max.apply(null, downloads.concat([0.01]));

        ctx.clearRect(0, 0, width, height);
        var barW = Math.max(4, (width - 16) / Math.max(1, downloads.length) - 2);

        downloads.forEach(function (v, i) {
            var barH = (v / max) * (height - 24);
            var x = 8 + i * (barW + 2);
            var y = height - 8 - barH;
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

    function init() {
        drawSparkline('sub-onu-sparkline', 'sub-onu-spark-data', '#7c3aed');
        drawUsageChart('day');
        bindUsageTabs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
})();
