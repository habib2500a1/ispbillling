(function () {
    'use strict';

    let boundRoot = null;

    function root() {
        return document.getElementById('isp-attendance-geofence');
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
                const wire = window.Livewire.find(id);
                if (wire) {
                    return wire;
                }
            }
        }

        const components = window.Livewire.all?.() ?? [];
        for (let i = 0; i < components.length; i++) {
            const wire = components[i].$wire;
            if (!wire || typeof wire.get !== 'function') {
                continue;
            }

            try {
                const value = wire.get('data.attendance_office_location_id');
                if (value !== undefined && value !== null && value !== '') {
                    return wire;
                }
            } catch (e) {
                /* ignore */
            }
        }

        const wireEl = document.querySelector('[wire\\:id]');
        const id = wireEl?.getAttribute('wire:id');
        if (!id) {
            return null;
        }

        return window.Livewire.find(id);
    }

    function findInput(name) {
        return document.querySelector(
            `[name="data[${name}]"], [wire\\:model="data.${name}"], [wire\\:model\\.live="data.${name}"], [wire\\:model\\.defer="data.${name}"]`,
        );
    }

    function readOfficeIdFromFilamentSelect() {
        const wrappers = document.querySelectorAll('[wire\\:key*="attendance_office_location_id"]');
        for (let i = 0; i < wrappers.length; i++) {
            const select = wrappers[i].querySelector('select[x-ref="input"], select');
            if (select && select.value !== '') {
                const id = parseInt(String(select.value), 10);
                if (Number.isFinite(id)) {
                    return id;
                }
            }
        }

        return null;
    }

    function getFormValue(name) {
        const r = root();
        if (r && name === 'attendance_office_location_id' && r.dataset.officeId) {
            return r.dataset.officeId;
        }

        if (r && name === 'status' && r.dataset.formStatus) {
            return r.dataset.formStatus;
        }

        const wire = livewireComponent();
        if (wire && typeof wire.get === 'function') {
            try {
                const value = wire.get(`data.${name}`);
                if (value !== undefined && value !== null && value !== '') {
                    return value;
                }
            } catch (e) {
                /* ignore */
            }
        }

        const input = findInput(name);
        if (input && input.value !== '') {
            return input.value;
        }

        return null;
    }

    function readOfficeId() {
        const raw = getFormValue('attendance_office_location_id');
        if (raw !== null && raw !== '') {
            const id = parseInt(String(raw), 10);
            if (Number.isFinite(id)) {
                return id;
            }
        }

        return readOfficeIdFromFilamentSelect();
    }

    function readStatus() {
        return getFormValue('status') || 'present';
    }

    function officeById(offices, id) {
        if (id == null) {
            return null;
        }

        return offices.find((o) => Number(o.id) === Number(id)) || null;
    }

    function distanceMeters(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const toRad = (d) => (d * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a =
            Math.sin(dLat / 2) ** 2 +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return Math.round(R * c);
    }

    function ipAllowed(office, clientIp) {
        const rules = office.allowed_ips || [];
        if (!rules.length) {
            return true;
        }

        return rules.some((rule) => {
            const r = String(rule).trim();
            if (!r) {
                return false;
            }

            return r === clientIp;
        });
    }

    function setHidden(name, value) {
        const input = findInput(name);
        if (input) {
            input.value = value ?? '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const wire = livewireComponent();
        if (wire && typeof wire.set === 'function') {
            try {
                wire.set(`data.${name}`, value ?? '');
            } catch (e) {
                /* ignore */
            }
        }
    }

    function updateUi(ctx, office, lat, lng, accuracy) {
        const { distanceEl, radiusEl, statusEl, hintEl, defaultRadius } = ctx;

        if (!office) {
            radiusEl.textContent = '—';
            distanceEl.textContent = '—';
            statusEl.textContent = 'Select office';
            statusEl.className = 'font-semibold text-gray-600';
            hintEl.textContent = '';
            return;
        }

        const radius = office.radius_meters || defaultRadius;
        radiusEl.textContent = `${radius} m`;

        if (lat == null || lng == null) {
            distanceEl.textContent = '—';
            statusEl.textContent = 'GPS not captured';
            statusEl.className = 'font-semibold text-amber-600';
            hintEl.textContent =
                office.name + ` · max ${radius} m · IP: ${ipAllowed(office, ctx.clientIp) ? 'OK' : 'not allowed'}`;
            return;
        }

        const dist = distanceMeters(
            parseFloat(office.latitude),
            parseFloat(office.longitude),
            lat,
            lng,
        );
        distanceEl.textContent = `${dist} m`;

        const gpsOk = dist <= radius;
        const ipOk = ipAllowed(office, ctx.clientIp);

        if (gpsOk && ipOk) {
            statusEl.textContent = 'Within office zone';
            statusEl.className = 'font-semibold text-emerald-600';
        } else if (!ipOk) {
            statusEl.textContent = 'IP not allowed';
            statusEl.className = 'font-semibold text-rose-600';
        } else {
            statusEl.textContent = 'Outside office zone';
            statusEl.className = 'font-semibold text-rose-600';
        }

        const accText = accuracy != null ? ` · accuracy ±${accuracy} m` : '';
        hintEl.textContent = `${office.name}: ${dist} m / ${radius} m max${accText}`;
    }

    function refreshFromForm() {
        const r = root();
        if (!r) {
            return;
        }

        const ctx = {
            offices: JSON.parse(r.dataset.offices || '[]'),
            clientIp: r.dataset.clientIp || '',
            defaultRadius: parseInt(r.dataset.defaultRadius || '10', 10),
            distanceEl: document.getElementById('isp-attendance-distance'),
            radiusEl: document.getElementById('isp-attendance-radius'),
            statusEl: document.getElementById('isp-attendance-gps-status'),
            hintEl: document.getElementById('isp-attendance-gps-hint'),
        };

        if (readStatus() !== 'present') {
            r.style.display = 'none';
            return;
        }

        r.style.display = '';

        const office = officeById(ctx.offices, readOfficeId());
        const latRaw = getFormValue('latitude');
        const lngRaw = getFormValue('longitude');
        const accRaw = getFormValue('accuracy_meters');

        const lat = latRaw != null && latRaw !== '' ? parseFloat(latRaw) : null;
        const lng = lngRaw != null && lngRaw !== '' ? parseFloat(lngRaw) : null;
        const acc = accRaw != null && accRaw !== '' ? parseInt(accRaw, 10) : null;

        updateUi(ctx, office, lat, lng, acc);
    }

    function captureGps() {
        const r = root();
        if (!r) {
            return;
        }

        window.dispatchEvent(new CustomEvent('isp-attendance-refresh-form'));

        const ctx = {
            offices: JSON.parse(r.dataset.offices || '[]'),
            clientIp: r.dataset.clientIp || '',
            defaultRadius: parseInt(r.dataset.defaultRadius || '10', 10),
            distanceEl: document.getElementById('isp-attendance-distance'),
            radiusEl: document.getElementById('isp-attendance-radius'),
            statusEl: document.getElementById('isp-attendance-gps-status'),
            hintEl: document.getElementById('isp-attendance-gps-hint'),
        };

        const office = officeById(ctx.offices, readOfficeId());
        if (!office) {
            alert('Select an office location first.');
            return;
        }

        if (!navigator.geolocation) {
            alert('Geolocation is not supported in this browser.');
            return;
        }

        const gpsBtn = document.getElementById('isp-attendance-gps-btn');
        if (!gpsBtn) {
            return;
        }

        gpsBtn.disabled = true;
        gpsBtn.textContent = 'Getting GPS…';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const acc = Math.round(pos.coords.accuracy);

                setHidden('latitude', lat);
                setHidden('longitude', lng);
                setHidden('accuracy_meters', acc);
                setHidden('client_ip', ctx.clientIp);

                updateUi(ctx, office, lat, lng, acc);
                gpsBtn.disabled = false;
                gpsBtn.innerHTML =
                    '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg> Use my GPS';
            },
            (err) => {
                gpsBtn.disabled = false;
                gpsBtn.textContent = 'Use my GPS';
                alert('Could not get GPS: ' + (err.message || 'permission denied'));
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    }

    function bindUi() {
        const r = root();
        if (!r || boundRoot === r) {
            return;
        }

        boundRoot = r;

        const gpsBtn = document.getElementById('isp-attendance-gps-btn');
        if (gpsBtn) {
            const clone = gpsBtn.cloneNode(true);
            gpsBtn.replaceWith(clone);
            clone.addEventListener('click', captureGps);
        }

        refreshFromForm();
    }

    function boot() {
        if (!root()) {
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

    document.addEventListener('DOMContentLoaded', scheduleBoot);
    document.addEventListener('livewire:initialized', scheduleBoot);
    document.addEventListener('livewire:navigated', function () {
        boundRoot = null;
        scheduleBoot();
    });
    document.addEventListener('livewire:commit', refreshFromForm);
    document.addEventListener('isp-attendance-form-changed', refreshFromForm);

    scheduleBoot();
})();
