(function () {
    const root = document.querySelector('[data-isp-noc-wall]');
    const streamUrl = root?.dataset.nocStream;

    function formatKpi(key, value) {
        if (key === 'wan_download_mbps' || key === 'wan_upload_mbps') {
            return Number(value).toFixed(2) + ' Mbps';
        }
        if (key === 'users_bandwidth') {
            return value;
        }
        if (key === 'olt_impact') {
            return value;
        }

        return new Intl.NumberFormat().format(Number(value) || 0);
    }

    function applyKpis(kpis) {
        if (!kpis) {
            return;
        }

        document.querySelectorAll('[data-noc-metric]').forEach(function (el) {
            const key = el.dataset.nocMetric;
            if (key === 'users_bandwidth' && kpis.users_download_mbps !== undefined) {
                el.textContent =
                    Number(kpis.users_download_mbps).toFixed(2) +
                    ' / ' +
                    Number(kpis.users_upload_mbps || 0).toFixed(2);
            } else if (key === 'olt_impact' && kpis.olt_offline !== undefined) {
                el.textContent =
                    new Intl.NumberFormat().format(kpis.olt_offline || 0) +
                    ' down · ' +
                    new Intl.NumberFormat().format(kpis.olt_partial || 0) +
                    ' partial';
            } else if (kpis[key] !== undefined) {
                el.textContent = formatKpi(key, kpis[key]);
            }
        });
    }

    function connectSse() {
        if (!streamUrl || typeof EventSource === 'undefined') {
            return;
        }

        const source = new EventSource(streamUrl);
        source.addEventListener('noc', function (event) {
            try {
                const data = JSON.parse(event.data);
                applyKpis(data.kpis);
                window.dispatchEvent(new CustomEvent('isp-noc-wall-update', { detail: data }));
            } catch (e) {
                /* ignore */
            }
        });
        source.onerror = function () {
            source.close();
        };
    }

    function connectEcho() {
        if (typeof window.Echo === 'undefined' || !window.ISP_BROADCAST) {
            return;
        }

        const cfg = window.ISP_BROADCAST;
        const tenantId = cfg.tenantId;
        if (!tenantId) {
            return;
        }

        window.Echo.channel('tenant.' + tenantId + '.noc').listen('.noc.updated', function (payload) {
            applyKpis(payload.kpis);
            window.dispatchEvent(new CustomEvent('isp-noc-wall-update', { detail: payload }));
        });
    }

    if (typeof requestIdleCallback === 'function') {
        requestIdleCallback(function () {
            connectSse();
            connectEcho();
        }, { timeout: 3000 });
    } else {
        window.setTimeout(function () {
            connectSse();
            connectEcho();
        }, 1500);
    }
})();
