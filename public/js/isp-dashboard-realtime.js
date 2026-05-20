(function () {
    const root = document.querySelector('[data-isp-dashboard]');
    const streamUrl = root?.dataset.dashboardStream;

    if (!streamUrl || typeof EventSource === 'undefined') {
        return;
    }

    function connectStream() {
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

    if (typeof requestIdleCallback === 'function') {
        requestIdleCallback(connectStream, { timeout: 3000 });
    } else {
        window.setTimeout(connectStream, 1500);
    }

    window.addEventListener('isp-dashboard-metrics', function (e) {
        const snap = e.detail?.snapshot;
        if (!snap) {
            return;
        }

        document.querySelectorAll('[data-metric]').forEach(function (el) {
            const key = el.dataset.metric;
            if (snap[key] !== undefined) {
                el.textContent = snap[key];
            }
        });
    });
})();
