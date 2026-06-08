/**
 * ISP Fiber plant map — Leaflet editor for outside plant (nodes + cables).
 */
(function () {
    'use strict';

    const TYPE_ICONS = {
        olt: '📡',
        pop: '📦',
        splitter: '🔀',
        pole: '🪵',
        junction: '🔗',
        closure: '🧰',
        customer: '🏠',
        other: '📍',
    };

    let map;
    let wire;
    let payload;
    let mode = 'view';
    let cableFromId = null;
    let nodeMarkers = {};
    let edgeLayers = {};
    let dropLayers = [];
    let baseLayers = {};
    let activeBase = 'street';
    let uiBound = false;
    let statusFilter = 'all';
    let searchQuery = '';
    let searchHighlightId = null;
    let pathLayers = [];
    let labelLayers = [];

    function init(options) {
        wire = options.wire;
        payload = options.payload;
        mode = 'view';

        if (typeof L === 'undefined') {
            return;
        }

        const el = document.getElementById(options.mapEl);
        if (!el) {
            return;
        }

        if (map) {
            map.remove();
            map = null;
            nodeMarkers = {};
            edgeLayers = {};
            dropLayers = [];
            pathLayers = [];
            labelLayers = [];
            baseLayers = {};
        }

        activeBase = 'street';
        const center = payload.center || { lat: 23.8103, lng: 90.4125, zoom: 12 };
        map = L.map(el, { zoomControl: true }).setView([center.lat, center.lng], center.zoom);
        baseLayers.street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 20,
            attribution: '© OpenStreetMap',
        });
        baseLayers.satellite = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            { maxZoom: 20, attribution: '© Esri' },
        );
        baseLayers.street.addTo(map);

        renderAll();
        bindUi();
        renderSearchResults();
        fitBounds();
    }

    function renderAll() {
        Object.values(nodeMarkers).forEach((m) => map.removeLayer(m));
        Object.values(edgeLayers).forEach((l) => map.removeLayer(l));
        dropLayers.forEach((l) => map.removeLayer(l));
        pathLayers.forEach((l) => map.removeLayer(l));
        labelLayers.forEach((l) => map.removeLayer(l));
        nodeMarkers = {};
        edgeLayers = {};
        dropLayers = [];
        pathLayers = [];
        labelLayers = [];

        const pathCustomerIds = new Set(
            (payload.ops?.fiber_paths || []).map((p) => p.customer_id).filter(Boolean),
        );

        (payload.ops?.fiber_paths || []).forEach((path) => {
            if (!path.points || path.points.length < 2) {
                return;
            }

            const line = L.polyline(path.points, {
                color: path.color || '#0ea5e9',
                weight: 5,
                opacity: 0.75,
            }).addTo(map);

            const segHtml = (path.segments || [])
                .map(
                    (s) =>
                        `<li><span style="color:${escapeHtml(s.color || '#2563eb')}">●</span> ${escapeHtml(s.from)} → ${escapeHtml(s.to)} · <strong>${Math.round(s.length_m || 0)}m</strong>${s.direction ? ' · ' + escapeHtml(s.direction) : ''}${s.pon ? ' · PON ' + escapeHtml(s.pon) : ''}</li>`,
                )
                .join('');

            line.bindPopup(
                `<div class="fpm-popup-card"><strong>Fiber path</strong>${path.olt ? `<br>OLT: ${escapeHtml(path.olt)}` : ''}${path.pon ? ` · PON ${escapeHtml(path.pon)}` : ''}${path.total_m != null ? `<br>Total: <strong>${Math.round(path.total_m)}m</strong>` : ''}<ul class="fpm-path-list">${segHtml}</ul></div>`,
                { maxWidth: 320, className: 'fpm-popup' },
            );
            pathLayers.push(line);

            (path.segments || []).forEach((seg, idx) => {
                const from = path.points[idx];
                const to = path.points[idx + 1];
                if (!from || !to) {
                    return;
                }
                addCableLabel(from, to, seg.length_m, seg.color, seg.direction, seg.from, seg.to);
            });
        });

        (payload.ops?.drop_lines || []).forEach((drop, idx) => {
            if (drop.customer_id && pathCustomerIds.has(drop.customer_id)) {
                return;
            }
            if (!drop.from || !drop.to) {
                return;
            }
            const line = L.polyline([drop.from, drop.to], {
                color: drop.color || '#2563eb',
                weight: drop.virtual ? 3 : 4,
                opacity: 0.9,
                dashArray: drop.dashed ? '8 6' : null,
            }).addTo(map);
            const len = drop.length_m != null ? Math.round(drop.length_m) : null;
            const srcBadge =
                drop.source === 'auto'
                    ? '<span class="fpm-badge fpm-badge--muted">auto</span>'
                    : '<span class="fpm-badge fpm-badge--ok">manual</span>';
            line.bindPopup(
                `<div class="fpm-popup-card"><strong>${escapeHtml(drop.cable_type || 'Drop fiber')}</strong> ${srcBadge}<br>${escapeHtml(drop.from_name || 'Splitter')} → ${escapeHtml(drop.to_name || 'Customer')}${len != null ? `<br><strong>${len}m</strong>` : ''}${drop.direction ? `<br>Direction: ${escapeHtml(drop.direction)}` : ''}${drop.pon ? `<br>PON: ${escapeHtml(drop.pon)}` : ''}${drop.olt ? `<br>OLT: ${escapeHtml(drop.olt)}` : ''}</div>`,
                { maxWidth: 300, className: 'fpm-popup' },
            );
            dropLayers.push(line);

            if (len != null) {
                addCableLabel(drop.from, drop.to, len, drop.color, drop.direction, drop.from_name, drop.to_name, drop.pon);
            }
        });

        (payload.edges || []).forEach((edge) => {
            if (!edge.from || !edge.to) {
                return;
            }
            const color = edge.cable_color_hex || '#2563eb';
            const weight = edge.highlighted ? 6 : 4;
            const line = L.polyline([edge.from, edge.to], {
                color,
                weight,
                opacity: edge.highlighted ? 1 : 0.85,
                dashArray: edge.cable_type === 'drop' ? '6 4' : null,
            }).addTo(map);

            line.bindPopup(buildEdgePopup(edge));
            line.on('click', () => selectEdge(edge));
            edgeLayers[edge.id] = line;

            addCableLabel(
                edge.from,
                edge.to,
                Math.round(edge.length_m),
                color,
                edge.direction_display || edge.direction_label,
                edge.from_name,
                edge.to_name,
            );
        });

        (payload.nodes || []).forEach((node) => {
            if (node.lat == null || node.lng == null) {
                return;
            }

            if (!nodePassesFilter(node)) {
                return;
            }

            const status = node.status || 'unknown';
            const dotColor = node.color || node.ops?.map_color || '#64748b';
            const isCustomer = node.type === 'customer';
            const meter = node.ops?.fiber_meter;

            const searchMatch = nodeSearchMatch(node);
            const dimClass = searchQuery && !searchMatch && node.type === 'customer' ? ' fpm-marker--dim' : '';
            const pulseClass = searchHighlightId && (node.customer_id === searchHighlightId || node.id === searchHighlightId)
                ? ' fpm-marker--pulse'
                : '';

            let markerHtml;
            if (isCustomer && node.ops) {
                const meterHtml = meter
                    ? `<em class="fpm-marker-meter" style="color:${meter.color}">${escapeHtml(meter.value)}</em>`
                    : '';
                const onuTag =
                    node.ops.onu_online === null
                        ? ''
                        : `<span class="fpm-marker-onu">${node.ops.onu_online ? 'ONU↑' : 'ONU↓'}</span>`;
                const ponTag = node.ops.pon
                    ? `<span class="fpm-marker-pon">${escapeHtml(node.ops.pon)}</span>`
                    : '';
                markerHtml = `<div class="fpm-marker fpm-marker--${status}${dimClass}${pulseClass}" style="--node-color:${dotColor}">
                    <span class="fpm-marker-dot"></span>
                    <span class="fpm-marker-icon">${TYPE_ICONS.customer}</span>
                    ${onuTag}
                    ${ponTag}
                    ${meterHtml}
                    <small>${escapeHtml(node.ops.ppp_login || node.code || '')}</small>
                </div>`;
            } else if (node.type === 'olt' && node.onu_total != null) {
                markerHtml = `<div class="fpm-marker fpm-marker--infra fpm-marker--olt${dimClass}" style="--node-color:${node.color}">
                    <span>${TYPE_ICONS.olt}</span>
                    <span class="fpm-marker-onu-count">${node.onu_online}/${node.onu_total} ONU</span>
                    <small>${escapeHtml(node.code || '')}</small>
                </div>`;
            } else if (node.type === 'splitter') {
                const ratio = node.splitter_ratio ? `1:${node.splitter_ratio}` : '';
                const dir = node.splitter_direction_label || node.splitter_direction || '';
                const pon = node.pon_label ? escapeHtml(node.pon_label) : '';
                const subs =
                    node.downstream_customers != null
                        ? `<span class="fpm-marker-subs">${node.downstream_customers} sub</span>`
                        : '';
                markerHtml = `<div class="fpm-marker fpm-marker--infra fpm-marker--splitter${dimClass}" style="--node-color:${node.color}">
                    <span>${TYPE_ICONS.splitter}</span>
                    ${pon ? `<em class="fpm-marker-pon">${pon}</em>` : ''}
                    ${ratio ? `<span class="fpm-marker-ratio">${ratio}</span>` : ''}
                    ${dir ? `<span class="fpm-marker-dir">${escapeHtml(dir)}</span>` : ''}
                    ${subs}
                    <small>${escapeHtml(node.code || node.name || '')}</small>
                </div>`;
            } else {
                const pon = node.pon_label ? `<em class="fpm-marker-pon">${escapeHtml(node.pon_label)}</em>` : '';
                markerHtml = `<div class="fpm-marker fpm-marker--infra fpm-marker--${node.type}${dimClass}" style="--node-color:${node.color}">
                    <span>${TYPE_ICONS[node.type] || '📍'}</span>
                    ${pon}
                    <small>${escapeHtml(node.code || '')}</small>
                </div>`;
            }

            const icon = L.divIcon({
                className: `fpm-node-marker fpm-node-marker--${node.type}`,
                html: markerHtml,
                iconSize: [40, 40],
                iconAnchor: [20, 20],
            });

            const canDrag = mode === 'view' && !node.unmapped && typeof node.id === 'number';
            const marker = L.marker([node.lat, node.lng], { icon, draggable: canDrag })
                .addTo(map)
                .bindPopup(buildNodePopup(node), { maxWidth: 320, className: 'fpm-popup' });

            marker.on('click', () => onNodeClick(node));
            if (canDrag) {
                marker.on('dragend', () => onNodeDrag(node, marker));
            }

            nodeMarkers[node.id] = marker;
        });
    }

    function nodeSearchMatch(node) {
        if (!searchQuery) {
            return true;
        }
        const q = searchQuery.toLowerCase();
        const hay = [
            node.name,
            node.ops?.ppp_login,
            node.customer_code,
            node.phone,
            node.ops?.onu_serial,
            node.code,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return hay.includes(q);
    }

    function nodePassesFilter(node) {
        if (node.type !== 'customer' || !node.ops) {
            return statusFilter === 'all';
        }

        const status = node.status || 'unknown';
        if (statusFilter === 'all') {
            return true;
        }
        if (statusFilter === 'online') {
            return status === 'online';
        }
        if (statusFilter === 'ppp_offline') {
            return status === 'ppp_offline';
        }
        if (statusFilter === 'onu_offline') {
            return status === 'onu_offline';
        }
        if (statusFilter === 'weak') {
            return status === 'weak' || status === 'critical';
        }

        return true;
    }

    function buildNodePopup(node) {
        if (node.type === 'customer' && node.ops) {
            const o = node.ops;
            const pppBadge = o.ppp_online
                ? '<span class="fpm-badge fpm-badge--ok">PPP online</span>'
                : `<span class="fpm-badge fpm-badge--bad">PPP offline</span>`;
            const onuBadge =
                o.onu_online === null
                    ? '<span class="fpm-badge fpm-badge--muted">No ONU</span>'
                    : o.onu_online
                      ? '<span class="fpm-badge fpm-badge--ok">ONU up</span>'
                      : '<span class="fpm-badge fpm-badge--bad">ONU down</span>';
            const meter = o.fiber_meter || {};
            const txVal = o.tx_dbm != null ? `${Number(o.tx_dbm).toFixed(1)} dBm` : '—';
            const meterRow = `<div class="fpm-popup-meter" style="--meter-color:${meter.color || '#64748b'}">
                <span>RX / TX</span>
                <strong>${escapeHtml(meter.value || '—')} / ${escapeHtml(txVal)}</strong>
            </div>`;
            const distM = o.fiber_distance_m != null ? `${Math.round(o.fiber_distance_m)} m` : '—';

            let html = `<div class="fpm-popup-card">
                <div class="fpm-popup-head">
                    <strong>${escapeHtml(node.name)}</strong>
                    <div class="fpm-popup-badges">${pppBadge}${onuBadge}</div>
                </div>
                <dl class="fpm-popup-dl">
                    <dt>PPP login</dt><dd><code>${escapeHtml(o.ppp_login || '—')}</code></dd>
                    <dt>Radius</dt><dd>${escapeHtml(o.radius_username || '—')}</dd>
                    <dt>Customer ID</dt><dd>${escapeHtml(node.customer_code || '—')}</dd>
                    <dt>Package</dt><dd>${escapeHtml(o.package || '—')}</dd>
                    <dt>MikroTik (TG)</dt><dd>${escapeHtml(o.mikrotik || '—')}</dd>
                    <dt>OLT / PON</dt><dd>${escapeHtml(o.olt || '—')}${o.pon ? ' · ' + escapeHtml(o.pon) : ''}</dd>
                    <dt>Upstream</dt><dd>${escapeHtml(o.upstream || '—')}</dd>
                    <dt>Fiber drop</dt><dd>${escapeHtml(distM)}</dd>
                    <dt>Zone</dt><dd>${escapeHtml(o.zone || '—')}${o.subzone ? ' · ' + escapeHtml(o.subzone) : ''}</dd>
                    <dt>ONU</dt><dd>${escapeHtml(o.onu_serial || '—')} · ${escapeHtml(o.onu_oper_status || '—')}${o.onu_online === true ? ' ✓' : o.onu_online === false ? ' ✗' : ''}</dd>
                </dl>
                ${meterRow}`;

            if (!o.ppp_online && o.ppp_offline_reason) {
                html += `<p class="fpm-popup-reason"><strong>কেন offline:</strong> ${escapeHtml(o.ppp_offline_reason)}</p>`;
            }
            if (o.onu_online === false && o.onu_offline_reason) {
                html += `<p class="fpm-popup-reason"><strong>ONU reason:</strong> ${escapeHtml(o.onu_offline_reason)}</p>`;
            }
            if (o.last_logout_at) {
                html += `<p class="fpm-popup-muted"><strong>Last logout:</strong> ${escapeHtml(o.last_logout_at)} (${escapeHtml(o.last_logout_ago || '')})</p>`;
            } else if (o.ppp_last_seen_at) {
                html += `<p class="fpm-popup-muted"><strong>Last seen:</strong> ${escapeHtml(o.ppp_last_seen_at)}</p>`;
            }
            if (o.onu_last_polled) {
                html += `<p class="fpm-popup-muted">ONU polled: ${escapeHtml(o.onu_last_polled)}</p>`;
            }
            if (node.address) {
                html += `<p class="fpm-popup-muted">${escapeHtml(node.address)}</p>`;
            }
            if (o.customer_url) {
                html += `<a class="fpm-popup-link" href="${escapeHtml(o.customer_url)}">Open subscriber →</a>`;
            }
            if (o.olt_url) {
                html += ` <a class="fpm-popup-link" href="${escapeHtml(o.olt_url)}">OLT →</a>`;
            }
            html += '</div>';

            return html;
        }

        let html = `<strong>${escapeHtml(node.name)}</strong><br><span class="text-xs">${escapeHtml(node.type_label)}</span>`;
        if (node.pon_label) {
            html += `<br>PON: <strong>${escapeHtml(node.pon_label)}</strong>${node.pon_source ? ` <small>(${escapeHtml(node.pon_source)})</small>` : ''}`;
        }
        if (node.olt_label) {
            html += `<br>OLT: ${escapeHtml(node.olt_label)}`;
        }
        if (node.splitter_ratio) {
            html += `<br>Splitter 1:${node.splitter_ratio}`;
        }
        if (node.splitter_direction_label || node.splitter_direction) {
            html += ` · ${escapeHtml(node.splitter_direction_label || node.splitter_direction)}`;
        }
        if (node.downstream_customers != null) {
            html += `<br>Customers: ${node.downstream_customers}`;
        }
        if (node.phone) {
            html += `<br>📞 ${escapeHtml(node.phone)}`;
        }
        if (node.customer_code) {
            html += `<br>ID: ${escapeHtml(node.customer_code)}`;
        }
        if (node.address) {
            html += `<br>${escapeHtml(node.address)}`;
        }

        return html;
    }

    function onNodeClick(node) {
        if (mode === 'draw_cable') {
            if (cableFromId === null) {
                cableFromId = node.id;
                highlightNode(node.id, true);

                return;
            }
            if (cableFromId === node.id) {
                return;
            }
            openEdgeForm(cableFromId, node.id);
            highlightNode(cableFromId, false);
            cableFromId = null;
            setMode('view');

            return;
        }

        selectNode(node);
    }

    function onNodeDrag(node, marker) {
        const pos = marker.getLatLng();
        fillNodeForm({
            ...node,
            latitude: pos.lat,
            longitude: pos.lng,
        });
        showForm('node');
        const form = document.getElementById('fpm-node-form');
        if (form) {
            form.querySelector('[name=latitude]').value = pos.lat.toFixed(7);
            form.querySelector('[name=longitude]').value = pos.lng.toFixed(7);
        }
    }

    function bindUi() {
        if (!uiBound) {
            document.querySelectorAll('.fpm-tool[data-mode]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    setMode(btn.dataset.mode);
                });
            });

            const nodeForm = document.getElementById('fpm-node-form');
            if (nodeForm) {
                nodeForm.addEventListener('submit', (ev) => {
                    ev.preventDefault();
                    saveNodeForm(nodeForm);
                });
                nodeForm.querySelector('[name=type]')?.addEventListener('change', toggleSplitterFields);
            }

            const edgeForm = document.getElementById('fpm-edge-form');
            if (edgeForm) {
                edgeForm.addEventListener('submit', (ev) => {
                    ev.preventDefault();
                    saveEdgeForm(edgeForm);
                });
            }

            document.getElementById('fpm-delete-node')?.addEventListener('click', deleteSelectedNode);
            document.getElementById('fpm-delete-edge')?.addEventListener('click', deleteSelectedEdge);

            document.querySelectorAll('.fpm-filter[data-filter]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    statusFilter = btn.dataset.filter || 'all';
                    document.querySelectorAll('.fpm-filter[data-filter]').forEach((b) => {
                        b.classList.toggle('fpm-filter--active', b === btn);
                    });
                    renderAll();
                    fitBounds();
                });
            });

            document.querySelectorAll('.fpm-map-tool[data-basemap]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const next = btn.dataset.basemap || 'street';
                    if (!map || !baseLayers[next] || activeBase === next) {
                        return;
                    }
                    map.removeLayer(baseLayers[activeBase]);
                    baseLayers[next].addTo(map);
                    activeBase = next;
                    document.querySelectorAll('.fpm-map-tool[data-basemap]').forEach((b) => {
                        b.classList.toggle('fpm-map-tool--active', b === btn);
                    });
                });
            });

            const searchEl = document.getElementById('fpm-search');
            if (searchEl) {
                let searchTimer;
                searchEl.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        searchQuery = (searchEl.value || '').trim();
                        searchHighlightId = null;
                        renderSearchResults();
                        renderAll();
                        if (searchQuery) {
                            focusSearchMatch();
                        }
                    }, 200);
                });
                searchEl.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        focusSearchMatch();
                    }
                });
            }

            document.getElementById('fpm-search-results')?.addEventListener('click', (ev) => {
                const btn = ev.target.closest('[data-search-pick]');
                if (!btn) {
                    return;
                }
                const customerId = parseInt(btn.dataset.customerId, 10);
                const nodeId = btn.dataset.nodeId || null;
                pickSearchResult(customerId, nodeId);
            });

            uiBound = true;
        }

        if (!map) {
            return;
        }

        map.off('click');
        map.on('click', (e) => {
            if (mode !== 'add_node') {
                return;
            }
            openNewNodeForm(e.latlng.lat, e.latlng.lng);
            setMode('view');
        });
    }

    function setMode(next) {
        mode = next;
        cableFromId = null;
        document.querySelectorAll('.fpm-tool[data-mode]').forEach((btn) => {
            btn.classList.toggle('fpm-tool--active', btn.dataset.mode === mode);
        });
        Object.keys(nodeMarkers).forEach((id) => highlightNode(id, false));

        const help = document.getElementById('fpm-help');
        if (help) {
            help.hidden = mode === 'view';
        }
    }

    function openNewNodeForm(lat, lng) {
        fillNodeForm({
            id: '',
            name: 'New node',
            code: '',
            type: 'splitter',
            latitude: lat,
            longitude: lng,
            address: '',
            splitter_ratio: '',
            splitter_direction: '',
            notes: '',
        });
        showForm('node');
    }

    function selectNode(node) {
        if (node.type === 'customer' && node.ops) {
            renderCustomerDetail(node);
            showForm('customer');
            return;
        }
        fillNodeForm(node);
        showForm('node');
    }

    function renderCustomerDetail(node) {
        const el = document.getElementById('fpm-customer-detail');
        if (!el || !node.ops) {
            return;
        }
        const o = node.ops;
        const meter = o.fiber_meter || {};
        const distM = o.fiber_distance_m != null ? `${Math.round(o.fiber_distance_m)} m` : '—';
        const txVal = o.tx_dbm != null ? `${Number(o.tx_dbm).toFixed(1)} dBm` : '—';

        const pathSegs = (o.fiber_segments || [])
            .map(
                (s) =>
                    `<li><span style="color:${escapeHtml(s.cable_color_hex || '#2563eb')}">●</span> ${escapeHtml(s.from || '—')} → ${escapeHtml(s.to || '—')} · <strong>${Math.round(s.length_m || 0)}m</strong>${s.direction_display || s.direction ? ' · ' + escapeHtml(s.direction_display || s.direction) : ''}</li>`,
            )
            .join('');
        const pathBlock =
            pathSegs !== ''
                ? `<div class="fpm-customer-path"><p class="fpm-customer-path__title">Cable path (manual / auto)</p><ul>${pathSegs}</ul></div>`
                : '';

        el.innerHTML = `
            <h3 class="fpm-customer-detail__title">${escapeHtml(node.name)}</h3>
            <div class="fpm-customer-detail__status fpm-customer-detail__status--${escapeHtml(node.status || 'unknown')}">
                ${o.ppp_online ? 'PPP ONLINE' : 'PPP OFFLINE'} · ${o.onu_online === false ? 'ONU DOWN' : o.onu_online ? 'ONU UP' : 'NO ONU'}
            </div>
            <div class="fpm-popup-meter" style="--meter-color:${meter.color || '#64748b'}">
                <span>Laser RX / TX</span>
                <strong>${escapeHtml(meter.value || '—')} / ${escapeHtml(txVal)}</strong>
            </div>
            <dl class="fpm-popup-dl">
                <dt>Login</dt><dd><code>${escapeHtml(o.ppp_login || '—')}</code></dd>
                <dt>MikroTik</dt><dd>${escapeHtml(o.mikrotik || '—')}</dd>
                <dt>OLT / PON</dt><dd>${escapeHtml(o.olt || '—')}${o.pon ? ' · ' + escapeHtml(o.pon) : ''}</dd>
                <dt>Fiber drop</dt><dd>${escapeHtml(distM)}</dd>
                <dt>Upstream</dt><dd>${escapeHtml(o.upstream || '—')}</dd>
                <dt>Last logout</dt><dd>${escapeHtml(o.last_logout_at || o.ppp_last_seen_at || '—')}</dd>
                <dt>ONU status</dt><dd>${o.onu_online === null ? 'No ONU' : o.onu_online ? 'Online' : 'Offline'} · RX ${escapeHtml(meter.value || '—')}</dd>
                <dt>Offline reason</dt><dd>${escapeHtml(o.ppp_offline_reason || o.onu_offline_reason || '—')}</dd>
            </dl>
            ${pathBlock}
            <div class="fpm-form__actions">
                ${o.customer_url ? `<a class="fpm-btn fpm-btn--primary" href="${escapeHtml(o.customer_url)}">Subscriber</a>` : ''}
                ${o.olt_url ? `<a class="fpm-btn fpm-btn--ghost" href="${escapeHtml(o.olt_url)}">OLT</a>` : ''}
                <button type="button" class="fpm-btn fpm-btn--ghost" id="fpm-edit-node-btn">Edit pin</button>
            </div>
        `;

        el.querySelector('#fpm-edit-node-btn')?.addEventListener('click', () => {
            fillNodeForm(node);
            showForm('node');
        });
    }

    function selectEdge(edge) {
        fillEdgeForm(edge);
        showForm('edge');
    }

    function fillNodeForm(node) {
        const form = document.getElementById('fpm-node-form');
        if (!form) {
            return;
        }
        form.querySelector('[name=id]').value = node.id || '';
        form.querySelector('[name=name]').value = node.name || '';
        form.querySelector('[name=code]').value = node.code || '';
        form.querySelector('[name=type]').value = node.type || 'other';
        form.querySelector('[name=latitude]').value = node.lat ?? node.latitude ?? '';
        form.querySelector('[name=longitude]').value = node.lng ?? node.longitude ?? '';
        form.querySelector('[name=address]').value = node.address || '';
        form.querySelector('[name=splitter_ratio]').value = node.splitter_ratio || '';
        form.querySelector('[name=splitter_direction]').value = node.splitter_direction || '';
        form.querySelector('[name=pon_label]').value = node.pon_label || '';
        form.querySelector('[name=olt_label]').value = node.olt_label || '';
        form.querySelector('[name=notes]').value = node.notes || '';
        document.getElementById('fpm-delete-node').hidden = !node.id;
        toggleSplitterFields();
    }

    function fillEdgeForm(edge) {
        const form = document.getElementById('fpm-edge-form');
        if (!form) {
            return;
        }
        form.querySelector('[name=id]').value = edge.id || '';
        form.querySelector('[name=from_node_id]').value = edge.from_node_id || '';
        form.querySelector('[name=to_node_id]').value = edge.to_node_id || '';
        form.querySelector('[name=cable_type]').value = edge.cable_type || 'distribution';
        form.querySelector('[name=length_m]').value = edge.length_m ?? '';
        form.querySelector('[name=cable_color]').value = edge.cable_color || 'blue';
        form.querySelector('[name=tube_color]').value = edge.tube_color || '';
        form.querySelector('[name=direction_label]').value = edge.direction_label || '';
        form.querySelector('[name=fiber_count]').value = edge.fiber_count || 2;
        form.querySelector('[name=notes]').value = edge.notes || '';
        document.getElementById('fpm-delete-edge').hidden = !edge.id;

        const from = (payload.nodes || []).find((n) => n.id === edge.from_node_id);
        const to = (payload.nodes || []).find((n) => n.id === edge.to_node_id);
        const hint = document.getElementById('fpm-edge-endpoints');
        if (hint && from && to) {
            hint.textContent = `${from.name} → ${to.name}`;
        }
    }

    function openEdgeForm(fromId, toId) {
        const from = (payload.nodes || []).find((n) => n.id === fromId);
        const to = (payload.nodes || []).find((n) => n.id === toId);
        if (!from || !to) {
            return;
        }

        let length = 0;
        if (from.lat != null && to.lat != null) {
            length = haversine(from.lat, from.lng, to.lat, to.lng);
        }

        fillEdgeForm({
            id: '',
            from_node_id: fromId,
            to_node_id: toId,
            cable_type: to.type === 'customer' ? 'drop' : 'distribution',
            length_m: Math.round(length),
            cable_color: 'blue',
            fiber_count: 2,
        });
        showForm('edge');
    }

    function showForm(which) {
        document.getElementById('fpm-node-form').hidden = which !== 'node';
        document.getElementById('fpm-edge-form').hidden = which !== 'edge';
        document.getElementById('fpm-customer-detail').hidden = which !== 'customer';
        document.getElementById('fpm-help').hidden = which !== 'help';
    }

    function toggleSplitterFields() {
        const type = document.querySelector('#fpm-node-form [name=type]')?.value;
        document.querySelectorAll('.fpm-field--splitter').forEach((el) => {
            el.hidden = type !== 'splitter';
        });
        document.querySelectorAll('.fpm-field--pon').forEach((el) => {
            el.hidden = !['splitter', 'olt', 'pop'].includes(type);
        });
    }

    function buildEdgePopup(edge) {
        const src = edge.auto_linked
            ? '<span class="fpm-badge fpm-badge--muted">auto</span>'
            : '<span class="fpm-badge fpm-badge--ok">manual</span>';

        return `<div class="fpm-popup-card">
            <strong>${escapeHtml(edge.cable_type_label || 'Cable')}</strong> ${src}
            <br>${escapeHtml(edge.from_name || '—')} → ${escapeHtml(edge.to_name || '—')}
            <br><strong>${Math.round(edge.length_m || 0)}m</strong>
            ${edge.direction_display || edge.direction_label ? `<br>Direction: ${escapeHtml(edge.direction_display || edge.direction_label)}` : ''}
            ${edge.cable_color ? `<br>Color: ${escapeHtml(edge.cable_color)}` : ''}
            ${edge.fiber_count ? `<br>Fibers: ${edge.fiber_count}` : ''}
            ${edge.notes ? `<br><small>${escapeHtml(edge.notes)}</small>` : ''}
        </div>`;
    }

    function addCableLabel(from, to, lengthM, color, direction, fromName, toName, pon) {
        if (!from || !to || lengthM == null) {
            return;
        }

        const mid = [(from[0] + to[0]) / 2, (from[1] + to[1]) / 2];
        const arrow = directionArrow(from, to, direction);
        const title = fromName && toName ? `${fromName}→${toName}` : '';
        const ponHtml = pon ? `<em class="fpm-edge-label-pon">${escapeHtml(pon)}</em>` : '';

        const marker = L.marker(mid, {
            icon: L.divIcon({
                className: 'fpm-edge-label',
                html: `<span class="fpm-edge-label__pill" style="--edge-color:${color || '#2563eb'}">${arrow}<strong>${lengthM}m</strong>${ponHtml}${title ? `<small>${escapeHtml(title)}</small>` : ''}</span>`,
                iconSize: [0, 0],
            }),
            interactive: false,
        }).addTo(map);

        labelLayers.push(marker);
    }

    function directionArrow(from, to, direction) {
        if (direction) {
            const short = String(direction).split(' ')[0];

            return `<span class="fpm-edge-label__dir">${escapeHtml(short)}</span>`;
        }

        const bearing = Math.atan2(to[1] - from[1], to[0] - from[0]) * (180 / Math.PI);
        const arrows = ['→', '↗', '↑', '↖', '←', '↙', '↓', '↘'];
        const idx = Math.round(((bearing + 360) % 360) / 45) % 8;

        return `<span class="fpm-edge-label__dir">${arrows[idx]}</span>`;
    }

    function formData(form) {
        const data = {};
        new FormData(form).forEach((val, key) => {
            if (val === '') {
                return;
            }
            if (['latitude', 'longitude', 'length_m'].includes(key)) {
                data[key] = parseFloat(val);
            } else if (['splitter_ratio', 'fiber_count', 'bearing_deg'].includes(key)) {
                data[key] = parseInt(val, 10);
            } else {
                data[key] = val;
            }
        });

        return data;
    }

    async function saveNodeForm(form) {
        const data = formData(form);
        const id = data.id ? parseInt(data.id, 10) : null;
        delete data.id;

        const result = await wire.saveNode(id, data);
        if (result.ok) {
            await refreshPayload(result.payload || null);
            showForm('help');
        } else {
            alert(result.message || 'Save failed');
        }
    }

    async function saveEdgeForm(form) {
        const data = formData(form);
        const id = data.id ? parseInt(data.id, 10) : null;
        delete data.id;

        const result = await wire.saveEdge(id, data);
        if (result.ok) {
            await refreshPayload(result.payload);
            showForm('help');
        } else {
            alert(result.message || 'Save failed');
        }
    }

    async function deleteSelectedNode() {
        const id = parseInt(document.querySelector('#fpm-node-form [name=id]').value, 10);
        if (!id || !confirm('Delete this node and its cables?')) {
            return;
        }
        const result = await wire.deleteNode(id);
        if (result.ok) {
            await refreshPayload(result.payload);
            showForm('help');
        }
    }

    async function deleteSelectedEdge() {
        const id = parseInt(document.querySelector('#fpm-edge-form [name=id]').value, 10);
        if (!id || !confirm('Delete this cable?')) {
            return;
        }
        const result = await wire.deleteEdge(id);
        if (result.ok) {
            await refreshPayload(result.payload);
            showForm('help');
        }
    }

    async function refreshPayload(next) {
        if (next) {
            payload = next;
        } else if (wire?.getMapPayload) {
            payload = await wire.getMapPayload();
        }

        if (!map) {
            return;
        }

        renderSearchResults();
        renderAll();
        fitBounds();
    }

    async function importInfra() {
        if (!wire?.importInfrastructure) {
            return;
        }
        await wire.importInfrastructure();
    }

    function fitBounds() {
        const coords = (payload.nodes || [])
            .filter((n) => n.lat != null && nodePassesFilter(n))
            .map((n) => [n.lat, n.lng]);
        if (coords.length > 1) {
            map.fitBounds(coords, { padding: [40, 40] });
        } else if (coords.length === 1) {
            map.setView(coords[0], 17);
        }
    }

    function renderSearchResults() {
        const el = document.getElementById('fpm-search-results');
        if (!el) {
            return;
        }

        if (!searchQuery) {
            el.hidden = true;
            el.innerHTML = '';

            return;
        }

        const q = searchQuery.toLowerCase();
        const matches = (payload.ops?.search_index || [])
            .filter((row) => {
                const hay = [row.label, row.login, row.code, row.phone].filter(Boolean).join(' ').toLowerCase();

                return hay.includes(q);
            })
            .slice(0, 15);

        if (matches.length === 0) {
            el.hidden = false;
            el.innerHTML = '<p class="fpm-search-results__empty">কোনো user পাওয়া যায়নি</p>';

            return;
        }

        el.hidden = false;
        el.innerHTML = matches
            .map((row) => {
                const onu =
                    row.onu_online === null
                        ? 'No ONU'
                        : row.onu_online
                          ? 'ONU online'
                          : 'ONU offline';
                const mapNote = row.on_map
                    ? '<span class="fpm-search-results__map">Map এ আছে</span>'
                    : '<span class="fpm-search-results__nomap">GPS নেই</span>';
                const rx =
                    row.rx_dbm != null ? `${Number(row.rx_dbm).toFixed(1)} dBm` : '—';

                return `<button type="button" class="fpm-search-results__item" data-search-pick data-customer-id="${row.id}" data-node-id="${escapeHtml(String(row.node_id || ''))}">
                    <span class="fpm-search-results__name">${escapeHtml(row.label)}</span>
                    <span class="fpm-search-results__meta"><code>${escapeHtml(row.login || '')}</code> · ${escapeHtml(row.code || '')}</span>
                    <span class="fpm-search-results__tags">${mapNote} · ${escapeHtml(onu)} · RX ${escapeHtml(rx)}</span>
                </button>`;
            })
            .join('');
    }

    function pickSearchResult(customerId, nodeId) {
        searchHighlightId = customerId;
        const node = findNodeForCustomer(customerId, nodeId);

        if (!node || node.lat == null) {
            const row = (payload.ops?.search_index || []).find((r) => r.id === customerId);
            if (row?.edit_url) {
                window.location.href = row.edit_url;
            }

            return;
        }

        renderAll();
        map.setView([node.lat, node.lng], 18);
        const marker = nodeMarkers[node.id];
        if (marker) {
            marker.openPopup();
        }
        selectNode(node);
        renderSearchResults();
    }

    function findNodeForCustomer(customerId, nodeId) {
        const nodes = payload.nodes || [];
        if (nodeId) {
            const byId = nodes.find((n) => String(n.id) === String(nodeId));
            if (byId) {
                return byId;
            }
        }

        return nodes.find((n) => n.customer_id === customerId && n.lat != null);
    }

    function focusSearchMatch() {
        const index = payload.ops?.search_index || [];
        const q = searchQuery.toLowerCase();
        const first = index.find((row) => {
            const hay = [row.label, row.login, row.code, row.phone].filter(Boolean).join(' ').toLowerCase();

            return hay.includes(q);
        });

        if (first) {
            pickSearchResult(first.id, first.node_id);
        }
    }

    function highlightNode(id, on) {
        const el = nodeMarkers[id]?.getElement()?.querySelector('div');
        if (el) {
            el.classList.toggle('fpm-node-marker--active', on);
        }
    }

    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const dLat = ((lat2 - lat1) * Math.PI) / 180;
        const dLng = ((lng2 - lng1) * Math.PI) / 180;
        const a =
            Math.sin(dLat / 2) ** 2 +
            Math.cos((lat1 * Math.PI) / 180) *
                Math.cos((lat2 * Math.PI) / 180) *
                Math.sin(dLng / 2) ** 2;

        return (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    window.IspFiberPlantMap = { init, refreshPayload };
})();
