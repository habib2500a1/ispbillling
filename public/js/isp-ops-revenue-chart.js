/**
 * Operations command center — revenue trend (Chart.js).
 * Works with lazy Livewire widgets and SPA navigation.
 */
(function () {
    'use strict';

    function themeColors() {
        var dark = document.documentElement.classList.contains('dark')
            || document.documentElement.getAttribute('data-theme') === 'dark';

        return {
            grid: dark ? 'rgba(148,163,184,0.18)' : 'rgba(148,163,184,0.2)',
            text: dark ? '#cbd5e1' : '#64748b',
            collected: dark ? '#2dd4bf' : '#0d9488',
            collectedFill: dark ? 'rgba(45,212,191,0.22)' : 'rgba(13,148,136,0.12)',
            invoiced: dark ? '#fb923c' : '#f97316',
            invoicedFill: dark ? 'rgba(251,146,60,0.18)' : 'rgba(249,115,22,0.08)',
        };
    }

    function parseDataset(el, key) {
        try {
            return JSON.parse(el.dataset[key] || '[]');
        } catch (e) {
            return [];
        }
    }

    function chartWrap(el) {
        return el && el.closest ? el.closest('[data-isp-chart-wrap]') : null;
    }

    function setChartMode(el, mode) {
        var wrap = chartWrap(el);
        if (!wrap) {
            return;
        }

        wrap.classList.remove('isp-cmd-chart--ready', 'isp-cmd-chart--empty', 'isp-cmd-chart--fallback');

        if (mode) {
            wrap.classList.add('isp-cmd-chart--' + mode);
        }
    }

    function paintOpsChart() {
        var el = document.getElementById('isp-cmd-revenue-chart');
        if (!el || typeof Chart === 'undefined') {
            return false;
        }

        var emptyEl = document.getElementById('isp-cmd-revenue-chart-empty');
        var labels = parseDataset(el, 'labels');
        var collected = parseDataset(el, 'collected');
        var invoiced = parseDataset(el, 'invoiced');
        var hasData = collected.some(function (v) { return Number(v) > 0; })
            || invoiced.some(function (v) { return Number(v) > 0; });

        if (!hasData) {
            if (el._ispChart) {
                el._ispChart.destroy();
                el._ispChart = null;
            }

            if (emptyEl) {
                emptyEl.hidden = false;
            }

            setChartMode(el, 'empty');

            return true;
        }

        if (emptyEl) {
            emptyEl.hidden = true;
        }

        var c = themeColors();

        if (el._ispChart) {
            el._ispChart.destroy();
            el._ispChart = null;
        }

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
                        pointHoverRadius: 4,
                    },
                    {
                        label: 'Invoiced',
                        data: invoiced,
                        borderColor: c.invoiced,
                        backgroundColor: c.invoicedFill,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 4,
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
                    y: {
                        ticks: { color: c.text },
                        grid: { color: c.grid },
                        beginAtZero: true,
                    },
                },
            },
        });

        setChartMode(el, 'ready');

        return true;
    }

    function boot() {
        var el = document.getElementById('isp-cmd-revenue-chart');

        if (el && typeof Chart === 'undefined') {
            setChartMode(el, 'fallback');
        }

        if (paintOpsChart()) {
            return;
        }

        var tries = 0;
        var timer = window.setInterval(function () {
            tries += 1;
            if (paintOpsChart() || tries >= 60) {
                window.clearInterval(timer);
            }
        }, 100);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
    window.addEventListener('isp-theme-changed', boot);

    if (window.Livewire && typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('commit', function (_ref) {
            var succeed = _ref.succeed;
            succeed(function () {
                window.requestAnimationFrame(boot);
            });
        });
    }

    window.ispPaintOpsRevenueChart = paintOpsChart;
    window.ispBootOpsRevenueChart = boot;
})();
