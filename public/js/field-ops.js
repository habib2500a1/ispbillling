/**
 * Field Technician Operating System — UI layer (no API changes).
 */
(function () {
    'use strict';

    var qrScanner = null;

    function getLivewire() {
        if (typeof window.Livewire === 'undefined') {
            return null;
        }
        var el = document.querySelector('.field-ops-page [wire\\:id], .field-ops-page[wire\\:id], [wire\\:id].fi-page');
        if (!el) {
            el = document.querySelector('[wire\\:id]');
        }
        if (!el) {
            return null;
        }
        return window.Livewire.find(el.getAttribute('wire:id'));
    }

    function setPanel(name) {
        document.querySelectorAll('[data-field-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-field-panel') !== name;
        });
        document.querySelectorAll('[data-field-nav]').forEach(function (btn) {
            btn.classList.toggle('field-nav-item--active', btn.getAttribute('data-field-nav') === name);
        });
        if (name === 'scan') {
            initScanner();
        } else {
            stopScanner();
        }
    }

    function initNav() {
        document.querySelectorAll('[data-field-nav]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setPanel(btn.getAttribute('data-field-nav'));
            });
        });
    }

    function initOffline() {
        var banner = document.querySelector('[data-field-offline]');
        if (!banner) {
            return;
        }
        function sync() {
            banner.hidden = navigator.onLine;
        }
        sync();
        window.addEventListener('online', sync);
        window.addEventListener('offline', sync);
    }

    function initTheme() {
        var root = document.querySelector('.field-ops-module') || document.body;
        var stored = localStorage.getItem('field-ops-theme');
        if (stored === 'light') {
            root.classList.add('field-theme-light');
        }
        document.querySelectorAll('[data-field-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                root.classList.toggle('field-theme-light');
                localStorage.setItem('field-ops-theme', root.classList.contains('field-theme-light') ? 'light' : 'dark');
            });
        });
    }

    function renderC360(data) {
        var body = document.querySelector('[data-field-drawer-body]');
        if (!body || !data || !data.c360) {
            return;
        }
        var c = data.c360;
        var onu = c.onu || {};
        var hints = (data.hints || []).map(function (h) {
            return '<div class="field-c360-rca"><strong>' + escapeHtml(h.title || '') + '</strong><p>' + escapeHtml(h.cause || '') + '</p><span style="font-size:0.68rem;color:var(--field-muted)">' + escapeHtml(h.confidence || '') + ' confidence</span></div>';
        }).join('');

        body.innerHTML =
            '<div class="field-c360-grid">' +
            row('Customer', c.name) +
            row('ID', c.code) +
            row('Username', c.ppp_login || '—') +
            row('Phone', c.phone) +
            row('Package', c.package) +
            row('Due', c.billing_due_fmt) +
            row('Last payment', c.last_payment) +
            row('Tickets', String(c.ticket_count || 0)) +
            row('PPP', c.ppp_online ? 'Online' : 'Offline') +
            row('ONU', onu.status || '—') +
            row('RX dBm', onu.rx_dbm != null ? String(onu.rx_dbm) : '—') +
            row('OLT', onu.olt || '—') +
            row('PON', onu.pon || '—') +
            '</div>' + hints +
            (data.ticket && data.ticket.url ? '<a href="' + escapeHtml(data.ticket.url) + '" class="field-btn field-btn--primary" style="margin-top:0.75rem;display:flex">Open ticket workspace</a>' : '');

        function row(label, value) {
            return '<div class="field-c360-row"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(String(value || '—')) + '</strong></div>';
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openDrawer(ticketId) {
        var drawer = document.querySelector('[data-field-drawer]');
        if (!drawer) {
            return;
        }
        drawer.hidden = false;
        var lw = getLivewire();
        if (!lw) {
            return;
        }
        lw.call('loadCustomer360', parseInt(ticketId, 10)).then(function (data) {
            renderC360(data);
        });
    }

    function initDrawer() {
        document.querySelectorAll('[data-field-c360]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openDrawer(btn.getAttribute('data-field-c360'));
            });
        });
        document.querySelectorAll('[data-field-drawer-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                var drawer = document.querySelector('[data-field-drawer]');
                if (drawer) {
                    drawer.hidden = true;
                }
            });
        });
    }

    function loadHtml5Qrcode(callback) {
        if (window.Html5Qrcode) {
            callback();
            return;
        }
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    function initScanner() {
        var viewport = document.getElementById('field-qr-reader');
        if (!viewport || qrScanner) {
            return;
        }
        loadHtml5Qrcode(function () {
            try {
                qrScanner = new window.Html5Qrcode('field-qr-reader');
                qrScanner.start(
                    { facingMode: 'environment' },
                    { fps: 8, qrbox: { width: 200, height: 200 } },
                    onScan,
                    function () {}
                ).catch(function () {
                    viewport.innerHTML = '<p style="padding:1rem;color:#94a3b8;font-size:0.8rem">Camera unavailable — use manual entry below.</p>';
                });
            } catch (e) {
                /* noop */
            }
        });

        var manual = document.querySelector('[data-field-manual-scan]');
        if (manual) {
            manual.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && manual.value.trim()) {
                    onScan(manual.value.trim());
                }
            });
        }
    }

    function stopScanner() {
        if (qrScanner) {
            qrScanner.stop().catch(function () {}).finally(function () {
                qrScanner = null;
            });
        }
    }

    function onScan(code) {
        var results = document.querySelector('[data-field-scan-results]');
        if (results) {
            results.hidden = false;
            results.innerHTML = '<p style="font-size:0.82rem;margin:0.5rem 0 0"><strong>Scanned:</strong> ' + escapeHtml(code) + '</p>' +
                '<a class="field-btn field-btn--sm field-btn--primary" style="margin-top:0.5rem" href="/admin/optical-noc?search=' + encodeURIComponent(code) + '">ONU lookup</a> ' +
                '<a class="field-btn field-btn--sm field-btn--ghost" style="margin-top:0.5rem" href="/admin/support-tickets?tableSearch=' + encodeURIComponent(code) + '">Search tickets</a>';
        }
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }
        navigator.serviceWorker.register('/sw-field.js', { scope: '/admin/field-technicians' }).catch(function () {});
    }

    function init() {
        initNav();
        initOffline();
        initTheme();
        initDrawer();
        registerServiceWorker();
        setPanel('home');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', function () {
        initNav();
        initOffline();
        initTheme();
        initDrawer();
        setPanel('home');
    });
})();
