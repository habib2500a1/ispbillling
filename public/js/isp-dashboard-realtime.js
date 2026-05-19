(function () {
    const tenantId = document.body.dataset.tenantId;
    const streamUrl = document.body.dataset.dashboardStream;

    if (!streamUrl) {
        return;
    }

    if (typeof EventSource !== 'undefined') {
        const source = new EventSource(streamUrl);
        source.addEventListener('metrics', function (event) {
            try {
                const data = JSON.parse(event.data);
                window.dispatchEvent(new CustomEvent('isp-dashboard-metrics', { detail: data }));
            } catch (e) {
                /* ignore */
            }
        });
        source.onerror = function () {
            source.close();
        };
    }

    window.addEventListener('isp-dashboard-metrics', function (e) {
        const snap = e.detail?.snapshot;
        if (!snap) return;
        document.querySelectorAll('[data-metric]').forEach(function (el) {
            const key = el.dataset.metric;
            if (snap[key] !== undefined) {
                el.textContent = snap[key];
            }
        });
    });
})();
