/**
 * Portal usage page: quick ~1s speed test + pro live usage graph.
 */
(function () {
    const panel = document.getElementById('usage-panel');
    if (!panel) {
        return;
    }

    const initial = JSON.parse(panel.dataset.stats || '{}');
    const liveUrl = panel.dataset.liveUrl;
    const pollMs = Math.max(1000, parseInt(panel.dataset.pollMs || '1000', 10));
    const quickUrl = panel.dataset.quickUrl;
    const pingUrl = panel.dataset.pingUrl;

    const COLOR_DOWN = '#2563eb';
    const COLOR_UP = '#dc2626';
    const FILL_DOWN = 'rgba(37, 99, 235, 0.18)';
    const FILL_UP = 'rgba(220, 38, 38, 0.14)';

    function formatBps(bps) {
        if (bps === null || bps === undefined) {
            return '—';
        }
        if (bps <= 0) {
            return '0 bps';
        }
        if (bps >= 1_000_000) {
            return (bps / 1_000_000).toFixed(2) + ' Mbps';
        }
        if (bps >= 1000) {
            return (bps / 1000).toFixed(1) + ' Kbps';
        }

        return bps + ' bps';
    }

    function formatMbps(mbps) {
        if (mbps === null || mbps === undefined || Number.isNaN(mbps)) {
            return '0.00';
        }

        return Number(mbps).toFixed(2);
    }

    function formatBytes(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        }
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        }
        if (bytes >= 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }

        return bytes + ' B';
    }

    function setOnlineState(online) {
        const statusCard = document.getElementById('status-card');
        const statusValue = document.getElementById('stat-online');
        const statusPill = document.getElementById('stat-online-pill');
        const liveBadge = document.getElementById('portal-live-badge');

        if (liveBadge) {
            liveBadge.textContent = online ? 'LIVE' : 'OFFLINE';
            liveBadge.classList.toggle('is-live', online);
            liveBadge.classList.toggle('is-off', !online);
        }

        if (!statusCard || !statusValue || !statusPill) {
            return;
        }

        statusCard.className =
            'portal-summary-card portal-usage-stat ' +
            (online ? 'portal-summary-card--ok' : 'portal-summary-card--warn');
        statusValue.textContent = online ? 'Online' : 'Offline';
        statusPill.className =
            'portal-status-pill ' + (online ? 'portal-status-pill--success' : 'portal-status-pill--muted');
        statusPill.textContent = online ? 'Session active' : 'No live session';
    }

    function updateLiveGraphStats(data) {
        const downMbps = document.getElementById('portal-live-down-mbps');
        const upMbps = document.getElementById('portal-live-up-mbps');
        if (downMbps) {
            downMbps.textContent = formatMbps(data.download_mbps ?? (data.download_bps || 0) / 1_000_000);
        }
        if (upMbps) {
            upMbps.textContent = formatMbps(data.upload_mbps ?? (data.upload_bps || 0) / 1_000_000);
        }

        const sessDown = document.getElementById('portal-stat-session-down');
        const sessUp = document.getElementById('portal-stat-session-up');
        const sessTotal = document.getElementById('portal-stat-session-total');
        const todayTotal = document.getElementById('portal-stat-today-total');
        const uptime = document.getElementById('portal-stat-uptime');

        if (sessDown) {
            sessDown.textContent = formatBytes(data.total_download);
        }
        if (sessUp) {
            sessUp.textContent = formatBytes(data.total_upload);
        }
        if (sessTotal) {
            sessTotal.textContent = formatBytes(data.session_total ?? (data.total_download || 0) + (data.total_upload || 0));
        }
        if (todayTotal) {
            todayTotal.textContent = formatBytes(data.today_total ?? (data.today_download || 0) + (data.today_upload || 0));
        }
        if (uptime && data.uptime) {
            uptime.textContent = data.uptime;
        }
    }

    let chart = null;

    function buildChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: window.innerWidth < 640 ? 'bottom' : 'top',
                    align: 'center',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        boxWidth: 10,
                        boxHeight: 10,
                        padding: 14,
                        font: { size: 11, weight: '600' },
                    },
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(15, 23, 42, 0.94)',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { size: 12, weight: 'bold' },
                    bodyFont: { size: 11 },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxTicksLimit: window.innerWidth < 640 ? 5 : 8, font: { size: 10 } },
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Mbps', font: { size: 11, weight: '600' } },
                    grid: { color: 'rgba(148, 163, 184, 0.25)' },
                    ticks: { font: { size: 10 } },
                },
            },
        };
    }

    function initChart() {
        const ctx = document.getElementById('usage-chart');
        if (!ctx || typeof Chart === 'undefined') {
            return;
        }

        const chartData = initial.chart || { labels: [], download_mbps: [], upload_mbps: [] };

        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: [
                    {
                        label: 'Download (Mbps)',
                        data: chartData.download_mbps || [],
                        borderColor: COLOR_DOWN,
                        backgroundColor: FILL_DOWN,
                        tension: 0.42,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        borderWidth: 2.5,
                    },
                    {
                        label: 'Upload (Mbps)',
                        data: chartData.upload_mbps || [],
                        borderColor: COLOR_UP,
                        backgroundColor: FILL_UP,
                        tension: 0.42,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        borderWidth: 2.5,
                    },
                ],
            },
            options: buildChartOptions(),
        });
    }

    async function refreshLive() {
        const updatedEl = document.getElementById('usage-updated');
        try {
            const res = await fetch(liveUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                if (updatedEl) {
                    updatedEl.textContent = 'Could not refresh';
                }

                return;
            }
            const data = await res.json();
            setOnlineState(Boolean(data.online));
            updateLiveGraphStats(data);

            const downEl = document.getElementById('stat-download');
            const upEl = document.getElementById('stat-upload');
            if (downEl) {
                downEl.textContent = formatBps(data.download_bps);
            }
            if (upEl) {
                upEl.textContent = formatBps(data.upload_bps);
            }
            const todayEl = document.getElementById('stat-today');
            if (todayEl) {
                todayEl.textContent =
                    '↓ ' + formatBytes(data.today_download) + ' · ↑ ' + formatBytes(data.today_upload);
            }
            const ipEl = document.getElementById('stat-ip');
            if (ipEl) {
                ipEl.textContent = data.framed_ip || '—';
            }
            const sessDown = document.getElementById('stat-session-down');
            const sessUp = document.getElementById('stat-session-up');
            if (sessDown) {
                sessDown.textContent = formatBytes(data.total_download);
            }
            if (sessUp) {
                sessUp.textContent = formatBytes(data.total_upload);
            }
            if (chart && data.chart) {
                chart.data.labels = data.chart.labels;
                chart.data.datasets[0].data = data.chart.download_mbps;
                chart.data.datasets[1].data = data.chart.upload_mbps;
                chart.update('none');
            }
            if (updatedEl) {
                updatedEl.textContent = 'Live · ' + new Date().toLocaleTimeString();
            }
        } catch (e) {
            if (updatedEl) {
                updatedEl.textContent = 'Could not refresh';
            }
        }
    }

    async function measureQuickPing() {
        const t0 = performance.now();
        await fetch(pingUrl + '?_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' });
        return performance.now() - t0;
    }

    async function measureQuickDownload() {
        const t0 = performance.now();
        const res = await fetch(quickUrl + '?_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' });
        const blob = await res.blob();
        const sec = Math.max((performance.now() - t0) / 1000, 0.001);
        return (blob.size * 8) / sec / 1_000_000;
    }

    async function runQuickSpeedTest() {
        const btn = document.getElementById('usage-quick-run');
        const status = document.getElementById('usage-quick-status');
        const pingEl = document.getElementById('usage-quick-ping');
        const downEl = document.getElementById('usage-quick-down');
        const ring = document.getElementById('usage-quick-ring');

        if (!btn || !quickUrl || !pingUrl) {
            return;
        }

        btn.disabled = true;
        if (status) {
            status.textContent = 'Testing (~1 sec)...';
        }
        if (ring) {
            ring.classList.add('is-running');
        }
        if (pingEl) {
            pingEl.textContent = '…';
        }
        if (downEl) {
            downEl.textContent = '…';
        }

        try {
            const [ping, down] = await Promise.all([measureQuickPing(), measureQuickDownload()]);
            if (pingEl) {
                pingEl.textContent = ping.toFixed(0);
            }
            if (downEl) {
                downEl.textContent = formatMbps(down);
            }
            if (status) {
                status.textContent = 'Done · ' + new Date().toLocaleTimeString();
            }
        } catch (e) {
            if (status) {
                status.textContent = 'Test failed — try again';
            }
            if (pingEl) {
                pingEl.textContent = '—';
            }
            if (downEl) {
                downEl.textContent = '—';
            }
        }

        if (ring) {
            ring.classList.remove('is-running');
        }
        btn.disabled = false;
    }

    const quickBtn = document.getElementById('usage-quick-run');
    if (quickBtn) {
        quickBtn.addEventListener('click', runQuickSpeedTest);
    }

    const updatedEl = document.getElementById('usage-updated');
    if (updatedEl) {
        updatedEl.textContent = 'Live · ' + new Date().toLocaleTimeString();
    }
    setOnlineState(Boolean(initial.online));
    updateLiveGraphStats(initial);
    initChart();
    setInterval(refreshLive, pollMs);

    if (panel.dataset.autoQuick === '1') {
        runQuickSpeedTest();
    }
})();
