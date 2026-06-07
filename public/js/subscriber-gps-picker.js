/**
 * Subscriber create/edit — legacy-style "GPS Coordinates (Lat, Long)" + map.
 */
(function () {
    'use strict';

    let map = null;
    let marker = null;
    let boundRoot = null;
    let observer = null;

    function root() {
        return document.getElementById('isp-subscriber-gps-picker');
    }

    function combinedInput() {
        return document.getElementById('isp-subscriber-gps-combined');
    }

    function defaults() {
        const el = root();
        return {
            lat: parseFloat(el?.dataset.defaultLat || '23.8103'),
            lng: parseFloat(el?.dataset.defaultLng || '90.4125'),
            zoom: 15,
        };
    }

    function findMetaInput(key) {
        const dotted = `data.meta.${key}`;
        const r = root();
        const scopes = r ? [r, document] : [document];

        for (let s = 0; s < scopes.length; s++) {
            const scope = scopes[s];
            const selectors = [
                `[wire\\:model="${dotted}"]`,
                `[wire\\:model\\.live="${dotted}"]`,
                `[wire\\:model\\.defer="${dotted}"]`,
                `[wire\\:model\\.blur="${dotted}"]`,
                `[wire\\:model\\.lazy="${dotted}"]`,
                `[name="data[meta][${key}]"]`,
                `input[id$="meta.${key}"]`,
                `input[id$="meta.gps_${key === 'gps_lat' ? 'lat' : key === 'gps_lng' ? 'lng' : key}"]`,
            ];
            for (let i = 0; i < selectors.length; i++) {
                const el = scope.querySelector(selectors[i]);
                if (el) {
                    return el;
                }
            }
        }

        return null;
    }

    function wireProxy(component) {
        if (!component) {
            return null;
        }

        return component.$wire ?? component;
    }

    function livewireComponent() {
        if (typeof window.Livewire === 'undefined') {
            return null;
        }

        const r = root();
        if (r) {
            const wireEl = r.closest('[wire\\:id]');
            const id = wireEl?.getAttribute('wire:id');
            if (id) {
                const wire = wireProxy(window.Livewire.find(id));
                if (wire) {
                    return wire;
                }
            }
        }

        const components = window.Livewire.all?.() ?? [];
        for (let i = 0; i < components.length; i++) {
            const wire = wireProxy(components[i].$wire ?? components[i]);
            if (!wire || typeof wire.get !== 'function') {
                continue;
            }

            try {
                const lat = wire.get('data.meta.gps_lat');
                const lng = wire.get('data.meta.gps_lng');
                const combined = wire.get('data.meta.gps_combined');
                if (lat !== undefined || lng !== undefined || combined !== undefined) {
                    return wire;
                }
            } catch (e) {
                /* ignore */
            }
        }

        const wireEl = document.querySelector('form.fi-form [wire\\:id], .fi-page [wire\\:id]');
        const id = wireEl?.getAttribute('wire:id');
        if (!id) {
            return null;
        }

        return wireProxy(window.Livewire.find(id));
    }

    function parseCombined(text) {
        if (!text || typeof text !== 'string') {
            return null;
        }
        const parts = text.split(/[,\s]+/).map((p) => p.trim()).filter(Boolean);
        if (parts.length < 2) {
            return null;
        }
        const lat = parseFloat(parts[0]);
        const lng = parseFloat(parts[1]);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }

        return { lat, lng };
    }

    function formatCombined(lat, lng) {
        return `${Number(lat)}, ${Number(lng)}`;
    }

    function syncCombinedFromHidden() {
        const lat = findMetaInput('gps_lat')?.value;
        const lng = findMetaInput('gps_lng')?.value;
        if (lat && lng) {
            const latN = parseFloat(lat);
            const lngN = parseFloat(lng);
            if (Number.isFinite(latN) && Number.isFinite(lngN)) {
                setCombinedDisplay(latN, lngN);

                return { lat: latN, lng: lngN };
            }
        }

        const wire = livewireComponent();
        if (wire && typeof wire.get === 'function') {
            try {
                const latW = parseFloat(wire.get('data.meta.gps_lat') ?? '');
                const lngW = parseFloat(wire.get('data.meta.gps_lng') ?? '');
                if (Number.isFinite(latW) && Number.isFinite(lngW)) {
                    setCombinedDisplay(latW, lngW);

                    return { lat: latW, lng: lngW };
                }
            } catch (e) {
                /* ignore */
            }
        }

        return null;
    }

    function readCoords() {
        const combined = combinedInput();
        if (combined?.value) {
            const parsed = parseCombined(combined.value);
            if (parsed) {
                return parsed;
            }
        }

        const lat = parseFloat(findMetaInput('gps_lat')?.value ?? '');
        const lng = parseFloat(findMetaInput('gps_lng')?.value ?? '');
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            return { lat, lng };
        }

        return null;
    }

    function setMetaInput(key, value) {
        const input = findMetaInput(key);
        if (input) {
            input.value = value ?? '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const wire = livewireComponent();
        if (wire && typeof wire.set === 'function') {
            try {
                wire.set(`data.meta.${key}`, value ?? '', false);
            } catch (e) {
                /* ignore */
            }
        }
    }

    function setStatus(message) {
        const el = document.getElementById('isp-subscriber-gps-status');
        if (el) {
            el.textContent = message;
        }
    }

    function setCombinedDisplay(lat, lng) {
        const el = combinedInput();
        if (el) {
            el.value = formatCombined(lat, lng);
        }
    }

    function setCoords(lat, lng) {
        const latN = Number(lat);
        const lngN = Number(lng);
        if (!Number.isFinite(latN) || !Number.isFinite(lngN)) {
            return;
        }
        const latStr = latN.toFixed(7);
        const lngStr = lngN.toFixed(7);
        const combined = formatCombined(latN, lngN);
        setMetaInput('gps_lat', latStr);
        setMetaInput('gps_lng', lngStr);
        setMetaInput('gps_combined', combined);
        setCombinedDisplay(latN, lngN);
        if (marker) {
            marker.setLatLng([latN, lngN]);
        }
        if (map) {
            map.panTo([latN, lngN]);
        }
    }

    function flushCoordsToForm() {
        const combined = combinedInput();
        if (combined?.value) {
            const parsed = parseCombined(combined.value);
            if (parsed) {
                setCoords(parsed.lat, parsed.lng);

                return parsed;
            }
        }

        const existing = readCoords();
        if (existing) {
            setCoords(existing.lat, existing.lng);
        }

        return existing;
    }

    function bindSaveFlush() {
        if (document.documentElement.dataset.ispGpsSaveBound === '1') {
            return;
        }
        document.documentElement.dataset.ispGpsSaveBound = '1';

        document.addEventListener(
            'submit',
            function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                if (!form.matches('form.fi-form, form[wire\\:submit], form[wire\\:submit\\.prevent]')) {
                    return;
                }

                if (!root()) {
                    return;
                }

                flushCoordsToForm();
            },
            true,
        );
    }

    function destroyMap() {
        if (map) {
            map.remove();
            map = null;
            marker = null;
        }
    }

    function initMap() {
        const el = document.getElementById('isp-subscriber-gps-map');
        if (!el || typeof L === 'undefined' || map) {
            return;
        }

        const def = defaults();
        const existing = readCoords();
        const lat = existing?.lat ?? def.lat;
        const lng = existing?.lng ?? def.lng;
        const zoom = existing ? 17 : def.zoom;

        map = L.map(el, { zoomControl: true }).setView([lat, lng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 20,
            attribution: '© OpenStreetMap',
        }).addTo(map);

        marker = L.marker([lat, lng], { draggable: true }).addTo(map);

        marker.on('dragend', function () {
            const p = marker.getLatLng();
            setCoords(p.lat, p.lng);
            setStatus('Pin moved — save the form to keep this location');
        });

        map.on('click', function (e) {
            setCoords(e.latlng.lat, e.latlng.lng);
            marker.setLatLng(e.latlng);
            setStatus('Location set — save the form to keep this pin');
        });

        [100, 400, 900].forEach(function (ms) {
            setTimeout(function () {
                if (map) {
                    map.invalidateSize();
                }
            }, ms);
        });
    }

    function captureGps(isAuto) {
        const combined = combinedInput();
        if (combined && isAuto) {
            combined.placeholder = 'Fetching…';
            combined.value = '';
        }

        if (!navigator.geolocation) {
            setStatus('Geolocation not supported');
            if (combined) {
                combined.placeholder = 'Enter lat, long';
            }
            initMap();

            return;
        }

        setStatus(isAuto ? 'Fetching location…' : 'Getting GPS…');

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                setCoords(pos.coords.latitude, pos.coords.longitude);
                setStatus('Location captured — save the form to keep this pin');
                if (combined) {
                    combined.placeholder = '';
                }
                if (!map) {
                    initMap();
                } else {
                    map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                }
            },
            function (err) {
                setStatus('GPS failed — type coordinates or tap pin (' + (err.message || 'denied') + ')');
                if (combined) {
                    combined.placeholder = 'lat, long';
                }
                initMap();
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    }

    function applyCombinedValue() {
        const combined = combinedInput();
        if (!combined) {
            return;
        }

        const parsed = parseCombined(combined.value);
        if (parsed) {
            setCoords(parsed.lat, parsed.lng);
            setStatus('Coordinates updated — save the form to keep this pin');
            if (!map) {
                initMap();
            }
        }
    }

    function bindCombinedManualEdit() {
        const combined = combinedInput();
        if (!combined || combined.dataset.ispGpsWatched) {
            return;
        }
        combined.dataset.ispGpsWatched = '1';
        combined.addEventListener('change', applyCombinedValue);
        combined.addEventListener('blur', applyCombinedValue);
        combined.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyCombinedValue();
            }
        });
    }

    function bindUi() {
        const r = root();
        if (!r || boundRoot === r) {
            return;
        }
        boundRoot = r;

        const btn = document.getElementById('isp-subscriber-gps-btn');
        if (btn) {
            btn.replaceWith(btn.cloneNode(true));
            document.getElementById('isp-subscriber-gps-btn')?.addEventListener('click', function () {
                captureGps(false);
            });
        }

        bindCombinedManualEdit();

        syncCombinedFromHidden();
        const existing = readCoords();
        if (existing) {
            setCombinedDisplay(existing.lat, existing.lng);
            setStatus('Coordinates loaded');
            initMap();
        } else {
            const def = defaults();
            if (combinedInput()) {
                combinedInput().placeholder = 'lat, long';
            }
            setStatus('Click the pin to allow GPS, or tap the map to set location.');
            initMap();
            if (map && marker) {
                map.setView([def.lat, def.lng], def.zoom);
                marker.setLatLng([def.lat, def.lng]);
            }
        }

        bindSaveFlush();
    }

    function boot() {
        if (!root()) {
            destroyMap();
            boundRoot = null;

            return false;
        }
        bindUi();

        return true;
    }

    function scheduleBoot() {
        if (boot()) {
            return;
        }
        let tries = 0;
        const timer = setInterval(function () {
            if (boot() || ++tries > 48) {
                clearInterval(timer);
            }
        }, 250);
    }

    function watchForPicker() {
        if (observer) {
            observer.disconnect();
        }
        observer = new MutationObserver(function () {
            if (root() && boundRoot !== root()) {
                destroyMap();
                boundRoot = null;
                scheduleBoot();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    document.addEventListener('DOMContentLoaded', scheduleBoot);
    document.addEventListener('livewire:navigated', function () {
        destroyMap();
        boundRoot = null;
        scheduleBoot();
    });
    document.addEventListener('livewire:initialized', scheduleBoot);

    watchForPicker();
    scheduleBoot();
})();
