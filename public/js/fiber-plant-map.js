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
    let uiBound = false;

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
        }

        const center = payload.center || { lat: 23.8103, lng: 90.4125, zoom: 12 };
        map = L.map(el, { zoomControl: true }).setView([center.lat, center.lng], center.zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 20,
            attribution: '© OpenStreetMap',
        }).addTo(map);

        renderAll();
        bindUi();
        fitBounds();
    }

    function renderAll() {
        Object.values(nodeMarkers).forEach((m) => map.removeLayer(m));
        Object.values(edgeLayers).forEach((l) => map.removeLayer(l));
        nodeMarkers = {};
        edgeLayers = {};

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

            line.bindPopup(`<strong>${escapeHtml(edge.label || 'Cable')}</strong><br>${escapeHtml(edge.cable_type_label || '')}`);
            line.on('click', () => selectEdge(edge));
            edgeLayers[edge.id] = line;

            const mid = [
                (edge.from[0] + edge.to[0]) / 2,
                (edge.from[1] + edge.to[1]) / 2,
            ];
            L.marker(mid, {
                icon: L.divIcon({
                    className: 'fpm-edge-label',
                    html: `<span style="background:${color}">${Math.round(edge.length_m)}m</span>`,
                    iconSize: [0, 0],
                }),
                interactive: false,
            }).addTo(map);
        });

        (payload.nodes || []).forEach((node) => {
            if (node.lat == null || node.lng == null) {
                return;
            }

            const icon = L.divIcon({
                className: `fpm-node-marker fpm-node-marker--${node.type}`,
                html: `<div style="--node-color:${node.color}"><span>${TYPE_ICONS[node.type] || '📍'}</span><small>${escapeHtml(node.code || '')}</small></div>`,
                iconSize: [36, 36],
                iconAnchor: [18, 18],
            });

            const marker = L.marker([node.lat, node.lng], { icon, draggable: mode === 'view' })
                .addTo(map)
                .bindPopup(buildNodePopup(node));

            marker.on('click', () => onNodeClick(node));
            marker.on('dragend', () => onNodeDrag(node, marker));

            nodeMarkers[node.id] = marker;
        });
    }

    function buildNodePopup(node) {
        let html = `<strong>${escapeHtml(node.name)}</strong><br><span class="text-xs">${escapeHtml(node.type_label)}</span>`;
        if (node.splitter_ratio) {
            html += `<br>Splitter 1:${node.splitter_ratio}`;
        }
        if (node.splitter_direction) {
            html += ` · ${escapeHtml(node.splitter_direction)}`;
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
        fillNodeForm(node);
        showForm('node');
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
        document.getElementById('fpm-help').hidden = which !== 'help';
    }

    function toggleSplitterFields() {
        const type = document.querySelector('#fpm-node-form [name=type]')?.value;
        document.querySelectorAll('.fpm-field--splitter').forEach((el) => {
            el.hidden = type !== 'splitter';
        });
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
            .filter((n) => n.lat != null)
            .map((n) => [n.lat, n.lng]);
        if (coords.length > 1) {
            map.fitBounds(coords, { padding: [40, 40] });
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
