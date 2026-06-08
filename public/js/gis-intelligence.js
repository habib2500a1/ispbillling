/**
 * GIS Intelligence Center — drawer, faults, heatmaps, timeline, technicians, PWA.
 */
(function () {
    'use strict';

    let intelLayers = { faults: [], techs: [], heatOffline: null, heatWeak: null };
    let timelineTimer = null;
    let timelineIndex = 0;
    let baseLayersRef = null;

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getMapApi() {
        return window.IspFiberPlantMap || null;
    }

    function getPayload() {
        return getMapApi()?.getPayload?.() || null;
    }

    function getMap() {
        return getMapApi()?.getMap?.() || null;
    }

    function intelligence() {
        return getPayload()?.ops?.intelligence || {};
    }

    function init() {
        bindDrawer();
        bindTabs();
        bindLayerToggles();
        bindTimeline();
        bindCoreMap();
        bindMobileFab();
        setupMapboxDark();
        renderFaultCenter();
        renderRcaPlaceholder();
        registerPwa();
        document.addEventListener('isp-gis-refresh', refreshIntelligence);
        toggleFaults(true);
    }

    function refreshIntelligence() {
        renderFaultCenter();
        const offlineOn = document.getElementById('gis-layer-offline')?.checked;
        const weakOn = document.getElementById('gis-layer-weak')?.checked;
        const faultOn = document.getElementById('gis-layer-faults')?.checked;
        const techOn = document.getElementById('gis-layer-techs')?.checked;
        if (offlineOn) {
            toggleHeatmap('offline', true);
        }
        if (weakOn) {
            toggleHeatmap('weak_rx', true);
        }
        if (faultOn) {
            toggleFaults(true);
        }
        if (techOn) {
            toggleTechnicians(true);
        }
    }

    function bindDrawer() {
        const drawer = document.getElementById('gis-drawer');
        const toggle = document.getElementById('gis-drawer-toggle');
        const close = document.getElementById('gis-drawer-close');
        if (!drawer || !toggle) {
            return;
        }
        toggle.addEventListener('click', () => {
            drawer.classList.toggle('gis-drawer--open');
            document.body.classList.toggle('gis-drawer-active');
        });
        close?.addEventListener('click', () => {
            drawer.classList.remove('gis-drawer--open');
            document.body.classList.remove('gis-drawer-active');
        });
    }

    function bindTabs() {
        document.querySelectorAll('[data-gis-tab]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.gisTab;
                document.querySelectorAll('[data-gis-tab]').forEach((b) => {
                    b.classList.toggle('gis-tab--active', b === btn);
                });
                document.querySelectorAll('[data-gis-panel]').forEach((panel) => {
                    panel.hidden = panel.dataset.gisPanel !== tab;
                });
            });
        });
    }

    function bindLayerToggles() {
        document.getElementById('gis-layer-offline')?.addEventListener('change', (e) => {
            toggleHeatmap('offline', e.target.checked);
        });
        document.getElementById('gis-layer-weak')?.addEventListener('change', (e) => {
            toggleHeatmap('weak_rx', e.target.checked);
        });
        document.getElementById('gis-layer-faults')?.addEventListener('change', (e) => {
            toggleFaults(e.target.checked);
        });
        document.getElementById('gis-layer-techs')?.addEventListener('change', (e) => {
            toggleTechnicians(e.target.checked);
        });
        document.getElementById('gis-basemap-dark')?.addEventListener('click', () => {
            switchBasemap('dark');
        });
    }

    function bindMobileFab() {
        document.getElementById('gis-mobile-search')?.addEventListener('click', () => {
            document.getElementById('fpm-search')?.focus();
            document.getElementById('gis-drawer')?.classList.remove('gis-drawer--open');
        });
        document.getElementById('gis-mobile-layers')?.addEventListener('click', () => {
            const drawer = document.getElementById('gis-drawer');
            drawer?.classList.add('gis-drawer--open');
            document.body.classList.add('gis-drawer-active');
            document.querySelector('[data-gis-tab="layers"]')?.click();
        });
    }

    function setupMapboxDark() {
        const map = getMap();
        const token = intelligence().config?.mapbox_token;
        const btn = document.getElementById('gis-basemap-dark');
        if (!map || !token || typeof L === 'undefined') {
            btn?.setAttribute('hidden', 'hidden');

            return;
        }
        btn?.removeAttribute('hidden');
        if (!map.gisDarkLayer) {
            map.gisDarkLayer = L.tileLayer(
                'https://api.mapbox.com/styles/v1/mapbox/dark-v11/tiles/{z}/{x}/{y}?access_token=' + token,
                { maxZoom: 20, tileSize: 512, zoomOffset: -1, attribution: '© Mapbox © OSM' },
            );
        }
    }

    function switchBasemap(key) {
        const map = getMap();
        if (!map) {
            return;
        }
        if (key === 'dark' && map.gisDarkLayer) {
            document.querySelectorAll('.fpm-map-tool[data-basemap]').forEach((b) => {
                b.classList.remove('fpm-map-tool--active');
            });
            Object.keys(map._layers || {}).forEach((id) => {
                const layer = map._layers[id];
                if (layer instanceof L.TileLayer && layer !== map.gisDarkLayer) {
                    map.removeLayer(layer);
                }
            });
            if (!map.hasLayer(map.gisDarkLayer)) {
                map.gisDarkLayer.addTo(map);
            }
        }
    }

    function toggleHeatmap(kind, on) {
        const map = getMap();
        if (!map || typeof L.heatLayer !== 'function') {
            return;
        }
        const data = intelligence().heatmaps?.[kind] || [];
        const key = kind === 'offline' ? 'heatOffline' : 'heatWeak';
        if (intelLayers[key]) {
            map.removeLayer(intelLayers[key]);
            intelLayers[key] = null;
        }
        if (!on || data.length === 0) {
            return;
        }
        intelLayers[key] = L.heatLayer(data, { radius: 28, blur: 22, maxZoom: 17 });
        intelLayers[key].addTo(map);
    }

    function toggleFaults(on) {
        const map = getMap();
        if (!map) {
            return;
        }
        intelLayers.faults.forEach((m) => map.removeLayer(m));
        intelLayers.faults = [];
        if (!on) {
            return;
        }
        (intelligence().faults || []).forEach((fault) => {
            if (fault.lat == null || fault.lng == null) {
                return;
            }
            const color = fault.severity === 'critical' ? '#ef4444' : fault.severity === 'high' ? '#f97316' : '#eab308';
            const icon = L.divIcon({
                className: 'gis-fault-marker',
                html: `<div class="gis-fault-marker__dot" style="--c:${color}">⚡</div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 16],
            });
            const marker = L.marker([fault.lat, fault.lng], { icon })
                .bindPopup(
                    `<div class="gis-popup"><strong>${escapeHtml(fault.title)}</strong><br>${escapeHtml(fault.description || '')}<br><em>${fault.affected_customers || 0} customers</em></div>`,
                )
                .addTo(map);
            marker.on('click', () => showRcaForFault(fault));
            intelLayers.faults.push(marker);
        });
    }

    function toggleTechnicians(on) {
        const map = getMap();
        if (!map) {
            return;
        }
        intelLayers.techs.forEach((m) => map.removeLayer(m));
        intelLayers.techs = [];
        if (!on) {
            return;
        }
        (intelligence().technicians || []).forEach((tech) => {
            const icon = L.divIcon({
                className: 'gis-tech-marker',
                html: `<div class="gis-tech-marker__dot">🧑‍🔧</div>`,
                iconSize: [28, 28],
                iconAnchor: [14, 14],
            });
            const marker = L.marker([tech.lat, tech.lng], { icon })
                .bindPopup(
                    `<div class="gis-popup"><strong>${escapeHtml(tech.name)}</strong><br>${escapeHtml(tech.purpose || '')}<br><em>${escapeHtml(tech.status)}</em></div>`,
                )
                .addTo(map);
            intelLayers.techs.push(marker);
        });
    }

    function renderFaultCenter() {
        const el = document.getElementById('gis-fault-list');
        const countEl = document.getElementById('gis-fault-count');
        if (!el) {
            return;
        }
        const faults = intelligence().faults || [];
        if (countEl) {
            countEl.textContent = String(faults.length);
        }
        if (faults.length === 0) {
            el.innerHTML = '<p class="gis-empty">No active faults detected.</p>';

            return;
        }
        el.innerHTML = faults
            .map(
                (f) => `<button type="button" class="gis-fault-card gis-fault-card--${escapeHtml(f.severity || 'medium')}" data-fault-id="${escapeHtml(f.id)}">
                <strong>${escapeHtml(f.title)}</strong>
                <span>${escapeHtml(f.description || '')}</span>
                <em>${f.affected_customers || 0} affected · ${escapeHtml(f.type || '')}</em>
            </button>`,
            )
            .join('');
        el.querySelectorAll('[data-fault-id]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const fault = faults.find((x) => x.id === btn.dataset.faultId);
                if (fault?.lat != null && fault?.lng != null) {
                    getMap()?.setView([fault.lat, fault.lng], 16);
                }
                showRcaForFault(fault);
            });
        });
    }

    function showRcaForFault(fault) {
        const el = document.getElementById('gis-rca-cards');
        if (!el || !fault) {
            return;
        }
        el.innerHTML = `<article class="gis-rca-card gis-rca-card--${escapeHtml(fault.severity)}">
            <h4>${escapeHtml(fault.title)}</h4>
            <p>${escapeHtml(fault.description || 'Network fault cluster')}</p>
            <ul>
                <li>OLT: ${escapeHtml(fault.olt || '—')}</li>
                <li>PON: ${escapeHtml(fault.pon || '—')}</li>
                <li>Affected: ${fault.affected_customers || 0} customers</li>
            </ul>
            <p class="gis-rca-hint">Check upstream OLT port, fiber cut, and power at splitter.</p>
        </article>`;
        document.querySelector('[data-gis-tab="rca"]')?.click();
    }

    function renderRcaPlaceholder() {
        document.getElementById('fpm-customer-detail')?.addEventListener('click', async (ev) => {
            const link = ev.target.closest('[data-gis-rca-customer]');
            if (!link) {
                return;
            }
            const customerId = parseInt(link.dataset.gisRcaCustomer, 10);
            if (!customerId) {
                return;
            }
            await loadRca(customerId);
        });
    }

    async function loadRca(customerId) {
        const el = document.getElementById('gis-rca-cards');
        if (!el) {
            return;
        }
        el.innerHTML = '<p class="gis-empty">Loading RCA…</p>';
        document.querySelector('[data-gis-tab="rca"]')?.click();
        try {
            let data = null;
            if (window.__gisWire?.getCustomerRca) {
                data = await window.__gisWire.getCustomerRca(customerId);
            } else {
                const res = await fetch(`/api/v1/staff/gis/customers/${customerId}/rca`, {
                    headers: { Accept: 'application/json', Authorization: 'Bearer ' + (localStorage.getItem('staff_token') || '') },
                });
                if (!res.ok) {
                    throw new Error('RCA fetch failed');
                }
                data = await res.json();
            }
            renderRcaCards(data);
        } catch {
            el.innerHTML = '<p class="gis-empty">RCA unavailable.</p>';
        }
    }

    function renderRcaCards(data) {
        const el = document.getElementById('gis-rca-cards');
        if (!el) {
            return;
        }
        if (!data?.cards?.length) {
            el.innerHTML = '<p class="gis-empty">No RCA data.</p>';

            return;
        }
        el.innerHTML = (data.cards || [])
            .map(
                (c) => `<article class="gis-rca-card gis-rca-card--${escapeHtml(c.severity)}">
                    <h4>${escapeHtml(c.title)}</h4>
                    <p>${escapeHtml(c.detail)}</p>
                    ${c.action ? `<p class="gis-rca-hint">${escapeHtml(c.action)}</p>` : ''}
                </article>`,
            )
            .join('');
        if (data.chain?.length) {
            el.innerHTML +=
                '<div class="gis-chain"><h4>Dependency chain</h4><ol>' +
                data.chain.map((s) => `<li><strong>${escapeHtml(s.label)}</strong> — ${escapeHtml(s.detail || s.status)}</li>`).join('') +
                '</ol></div>';
        }
    }

    function bindTimeline() {
        const play = document.getElementById('gis-timeline-play');
        const slider = document.getElementById('gis-timeline-slider');
        if (!play || !slider) {
            return;
        }
        const events = intelligence().timeline || [];
        slider.max = String(Math.max(events.length - 1, 0));
        slider.addEventListener('input', () => {
            timelineIndex = parseInt(slider.value, 10) || 0;
            showTimelineEvent(events[timelineIndex]);
        });
        play.addEventListener('click', () => {
            if (timelineTimer) {
                clearInterval(timelineTimer);
                timelineTimer = null;
                play.textContent = '▶ Play';

                return;
            }
            play.textContent = '⏸ Pause';
            timelineTimer = setInterval(() => {
                timelineIndex = (timelineIndex + 1) % Math.max(events.length, 1);
                slider.value = String(timelineIndex);
                showTimelineEvent(events[timelineIndex]);
            }, 1800);
        });
        renderTimelineList(events);
    }

    function renderTimelineList(events) {
        const el = document.getElementById('gis-timeline-list');
        if (!el) {
            return;
        }
        if (!events.length) {
            el.innerHTML = '<p class="gis-empty">No timeline events yet.</p>';

            return;
        }
        el.innerHTML = events
            .slice(0, 20)
            .map(
                (e) => `<div class="gis-timeline-item gis-timeline-item--${escapeHtml(e.severity || 'info')}">
                <time>${escapeHtml((e.at || '').replace('T', ' ').slice(0, 16))}</time>
                <span>${escapeHtml(e.label)}</span>
            </div>`,
            )
            .join('');
    }

    function showTimelineEvent(event) {
        const label = document.getElementById('gis-timeline-label');
        if (label && event) {
            label.textContent = event.label || '';
        }
        if (event?.lat != null && event?.lng != null) {
            getMap()?.setView([event.lat, event.lng], 15);
        }
        if (event?.customer_id) {
            getMapApi()?.pickSearchResult?.(event.customer_id, null);
        }
    }

    function bindCoreMap() {
        document.addEventListener('click', (ev) => {
            const btn = ev.target.closest('[data-gis-core-map]');
            if (!btn) {
                return;
            }
            const edgeId = btn.dataset.gisCoreMap;
            const coreMaps = intelligence().core_maps || [];
            const entry = coreMaps.find((c) => String(c.edge_id) === String(edgeId));
            if (!entry) {
                return;
            }
            showCoreMapModal(entry);
        });
    }

    function showCoreMapModal(entry) {
        const modal = document.getElementById('gis-core-modal');
        const body = document.getElementById('gis-core-modal-body');
        if (!modal || !body) {
            return;
        }
        const cores = entry.core_map || {};
        const fibers = Array.isArray(cores.fibers) ? cores.fibers : Object.entries(cores);
        body.innerHTML = `<h3>${escapeHtml(entry.label || 'Cable')} · ${escapeHtml(entry.cable_type || '')}</h3>
            <div class="gis-core-grid">${fibers
                .map((f) => {
                    const label = Array.isArray(f) ? f[0] : f.label || f.id;
                    const status = Array.isArray(f) ? f[1] : f.status || 'unknown';

                    return `<div class="gis-core-cell gis-core-cell--${escapeHtml(String(status))}">${escapeHtml(String(label))}</div>`;
                })
                .join('')}</div>`;
        modal.hidden = false;
        modal.querySelector('[data-gis-core-close]')?.addEventListener('click', () => {
            modal.hidden = true;
        }, { once: true });
    }

    function registerPwa() {
        if (!intelligence().config?.pwa?.enabled || !('serviceWorker' in navigator)) {
            return;
        }
        navigator.serviceWorker.register('/sw-gis.js', { scope: '/admin/fiber-plant-map' }).catch(() => {});
    }

    function waitForMap(retries) {
        if (getMap()) {
            init();
            document.dispatchEvent(new CustomEvent('isp-gis-ready'));

            return;
        }
        if (retries <= 0) {
            return;
        }
        setTimeout(() => waitForMap(retries - 1), 300);
    }

    document.addEventListener('DOMContentLoaded', () => waitForMap(40));
    document.addEventListener('livewire:navigated', () => waitForMap(40));

    window.IspGisIntelligence = { refresh: refreshIntelligence, loadRca };
})();
