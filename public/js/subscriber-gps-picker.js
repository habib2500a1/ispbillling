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
        const selectors = [
            `[wire\\:model="${dotted}"]`,
            `[wire\\:model\\.live="${dotted}"]`,
            `[wire\\:model\\.defer="${dotted}"]`,
            `[wire\\:model\\.blur="${dotted}"]`,
            `[name="data[meta][${key}]"]`,
            `input[id$="meta.gps_${key === 'gps_lat' ? 'lat' : 'lng'}"]`,
        ];
        for (let i = 0; i < selectors.length; i++) {
            const el = document.querySelector(selectors[i]);
            if (el) {
                return el;
            }
        }

        return null;
    }

    function livewireComponent() {
        const wireEl = document.querySelector('[wire\\:id]');
        const id = wireEl?.getAttribute('wire:id');
        if (!id || typeof window.Livewire === 'undefined') {
            return null;
        }

        return window.Livewire.find(id);
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
                wire.set(`data.meta.${key}`, value ?? '');
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
        setMetaInput('gps_lat', latStr);
        setMetaInput('gps_lng', lngStr);
        setCombinedDisplay(latN, lngN);
        if (marker) {
            marker.setLatLng([latN, lngN]);
        }
        if (map) {
            map.panTo([latN, lngN]);
        }
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
            setStatus('Pin moved');
        });

        map.on('click', function (e) {
            setCoords(e.latlng.lat, e.latlng.lng);
            marker.setLatLng(e.latlng);
            setStatus('Location set on map');
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
                setStatus('Location captured');
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

    function bindCombinedManualEdit() {
        const combined = combinedInput();
        if (!combined || combined.dataset.ispGpsWatched) {
            return;
        }
        combined.dataset.ispGpsWatched = '1';
        combined.addEventListener('change', function () {
            const parsed = parseCombined(combined.value);
            if (parsed) {
                setCoords(parsed.lat, parsed.lng);
                setStatus('Coordinates updated');
                if (!map) {
                    initMap();
                }
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
            setCombinedDisplay(def.lat, def.lng);
            setMetaInput('gps_lat', def.lat.toFixed(7));
            setMetaInput('gps_lng', def.lng.toFixed(7));
            if (combinedInput()) {
                combinedInput().placeholder = 'lat, long';
            }
            setStatus('Click the pin to allow GPS, or tap the map to set location.');
            initMap();
        }
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
