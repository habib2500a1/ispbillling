/**
 * Portal usage page: quick ~1s speed test + live router stats polling.
 */
(function () {
    const panel = document.getElementById('usage-panel');
    if (!panel) {
        return;
    }

    const initial = JSON.parse(panel.dataset.stats || '{}');
    const liveUrl = panel.dataset.liveUrl;
    const pollMs = Math.max(3000, parseInt(panel.dataset.pollMs || '5000', 10));
    const quickUrl = panel.dataset.quickUrl;
    const pingUrl = panel.dataset.pingUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

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
            return '—';
        }

        return mbps.toFixed(2);
    }

    function formatBytes(bytes) {
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

    let chart = null;

    function initChart() {
        const ctx = document.getElementById('usage-chart');
        if (!ctx || typeof Chart === 'undefined' || !initial.chart) {
            return;
        }

        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: initial.chart.labels || [],
                datasets: [
                    {
                        label: 'Download',
                        data: initial.chart.download_mbps || [],
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.08)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 0,
                        borderWidth: 2,
                    },
                    {
                        label: 'Upload',
                        data: initial.chart.upload_mbps || [],
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.08)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 0,
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: window.innerWidth < 640 ? 'bottom' : 'top',
                        labels: { boxWidth: 12, padding: 14, font: { size: 11 } },
                    },
                },
                scales: {
                    x: {
                        ticks: { maxTicksLimit: window.innerWidth < 640 ? 5 : 8, font: { size: 10 } },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 } },
                    },
                },
            },
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
    initChart();
    setInterval(refreshLive, pollMs);

    if (panel.dataset.autoQuick === '1') {
        runQuickSpeedTest();
    }
})();
