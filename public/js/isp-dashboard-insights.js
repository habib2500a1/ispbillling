/**
 * Dashboard insights row — revenue + online charts (Chart.js).
 */
(function () {
    'use strict';

    function theme() {
        var dark = document.documentElement.classList.contains('dark')
            || document.documentElement.getAttribute('data-theme') === 'dark';

        return {
            grid: dark ? 'rgba(148,163,184,0.16)' : 'rgba(148,163,184,0.22)',
            text: dark ? '#cbd5e1' : '#64748b',
            collected: dark ? '#2dd4bf' : '#10b981',
            collectedFill: dark ? 'rgba(45,212,191,0.2)' : 'rgba(16,185,129,0.12)',
            invoiced: dark ? '#a78bfa' : '#6366f1',
            invoicedFill: dark ? 'rgba(167,139,250,0.18)' : 'rgba(99,102,241,0.1)',
            online: dark ? '#22d3ee' : '#06b6d4',
            onlineFill: dark ? 'rgba(34,211,238,0.18)' : 'rgba(6,182,212,0.14)',
        };
    }

    function parse(el, key) {
        try {
            return JSON.parse(el.dataset[key] || '[]');
        } catch (e) {
            return [];
        }
    }

    function setMode(wrap, mode) {
        if (!wrap) {
            return;
        }
        wrap.classList.remove('isp-dash-insights__canvas--ready', 'isp-dash-insights__canvas--fallback');
        if (mode) {
            wrap.classList.add('isp-dash-insights__canvas--' + mode);
        }
    }

    function paintRevenue() {
        var el = document.getElementById('isp-dash-revenue-chart');
        if (!el || typeof Chart === 'undefined') {
            return false;
        }

        var wrap = el.closest('[data-isp-insights-revenue]');
        var c = theme();
        var labels = parse(el, 'labels');
        var collected = parse(el, 'collected');
        var invoiced = parse(el, 'invoiced');

        if (!labels.length) {
            return false;
        }

        if (el._ispChart) {
            el._ispChart.destroy();
            el._ispChart = null;
        }

        try {
            el._ispChart = new Chart(el, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Collected',
                            data: collected,
                            borderColor: c.collected,
                            backgroundColor: c.collectedFill,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                        },
                        {
                            label: 'Invoiced',
                            data: invoiced,
                            borderColor: c.invoiced,
                            backgroundColor: c.invoicedFill,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: c.text, maxTicksLimit: 8 }, grid: { color: c.grid } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid }, beginAtZero: true },
                    },
                },
            });
            el._ispChart.resize();
            setMode(wrap, 'ready');
            return true;
        } catch (e) {
            return false;
        }
    }

    function paintOnline() {
        var el = document.getElementById('isp-dash-online-chart');
        if (!el || typeof Chart === 'undefined') {
            return false;
        }

        var wrap = el.closest('[data-isp-insights-online]');
        var c = theme();
        var labels = parse(el, 'labels');

        if (!labels.length) {
            return false;
        }

        if (el._ispChart) {
            el._ispChart.destroy();
            el._ispChart = null;
        }

        try {
            el._ispChart = new Chart(el, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Online',
                            data: parse(el, 'online'),
                            borderColor: c.online,
                            backgroundColor: c.onlineFill,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: c.text, maxTicksLimit: 6 }, grid: { color: c.grid } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid }, beginAtZero: true },
                    },
                },
            });
            el._ispChart.resize();
            setMode(wrap, 'ready');
            return true;
        } catch (e) {
            return false;
        }
    }

    function boot() {
        var okRev = paintRevenue();
        var okOn = paintOnline();

        document.querySelectorAll('[data-isp-insights-revenue]').forEach(function (w) {
            if (!okRev) {
                setMode(w, 'fallback');
            }
        });
        document.querySelectorAll('[data-isp-insights-online]').forEach(function (w) {
            if (!okOn) {
                setMode(w, 'fallback');
            }
        });
    }

    function scheduleBoot() {
        boot();
        requestAnimationFrame(function () {
            boot();
        });
        setTimeout(boot, 350);
        setTimeout(boot, 1200);
    }

    window.ispBootDashboardInsights = scheduleBoot;
    window.ispPaintDashboardInsights = scheduleBoot;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleBoot);
    } else {
        scheduleBoot();
    }

    document.addEventListener('livewire:navigated', scheduleBoot);
    window.addEventListener('isp-theme-changed', scheduleBoot);
})();
