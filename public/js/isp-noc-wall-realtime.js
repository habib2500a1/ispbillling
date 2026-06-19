(function () {
    const root = document.querySelector('[data-isp-noc-wall]');
    const streamUrl = root?.dataset.nocStream;

    function formatKpi(key, value) {
        if (key === 'wan_download_mbps' || key === 'wan_upload_mbps') {
            return Number(value).toFixed(2) + ' Mbps';
        }
        if (key === 'users_bandwidth' || key === 'olt_impact') {
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
        const cfg = window.ISP_BROADCAST;
        if (!cfg?.enabled || typeof window.Pusher === 'undefined' || typeof window.Echo === 'undefined') {
            return;
        }

        window.Pusher = window.Pusher || Pusher;
        const wsPath = cfg.wsPath || '/ws';
        const echoOpts = {
            broadcaster: 'pusher',
            key: cfg.key,
            cluster: cfg.cluster || 'mt1',
            wsHost: cfg.wsHost || window.location.hostname,
            wsPort: cfg.wsPort || 6001,
            wssPort: cfg.wssPort || cfg.wsPort || 443,
            forceTLS: cfg.forceTLS === true,
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: cfg.authEndpoint || '/broadcasting/auth',
        };

        if (wsPath) {
            echoOpts.wsPath = wsPath;
        }

        window.Echo = new Echo(echoOpts);

        window.Echo.channel('tenant.' + cfg.tenantId + '.noc').listen('.noc.updated', function (payload) {
            applyKpis(payload.kpis);
            window.dispatchEvent(new CustomEvent('isp-noc-wall-update', { detail: payload }));
        });
    }

    function boot() {
        connectSse();
        connectEcho();
    }

    if (typeof requestIdleCallback === 'function') {
        requestIdleCallback(boot, { timeout: 3000 });
    } else {
        window.setTimeout(boot, 1500);
    }
})();
