(function () {
    const root = document.getElementById('isp-attendance-geofence');
    if (!root) {
        return;
    }

    const offices = JSON.parse(root.dataset.offices || '[]');
    const clientIp = root.dataset.clientIp || '';
    const defaultRadius = parseInt(root.dataset.defaultRadius || '10', 10);

    const distanceEl = document.getElementById('isp-attendance-distance');
    const radiusEl = document.getElementById('isp-attendance-radius');
    const statusEl = document.getElementById('isp-attendance-gps-status');
    const hintEl = document.getElementById('isp-attendance-gps-hint');
    const gpsBtn = document.getElementById('isp-attendance-gps-btn');

    function findInput(name) {
        return document.querySelector(`[name="data[${name}]"], [wire\\:model="data.${name}"], [wire\\:model\\.live="data.${name}"]`);
    }

    function readOfficeId() {
        const select = findInput('attendance_office_location_id');
        if (!select) {
            return null;
        }

        return select.value ? parseInt(select.value, 10) : null;
    }

    function readStatus() {
        const select = findInput('status');
        return select ? select.value : 'present';
    }

    function officeById(id) {
        return offices.find((o) => o.id === id);
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

    function ipAllowed(office) {
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
        if (!input) {
            return;
        }

        input.value = value ?? '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function updateUi(office, lat, lng, accuracy) {
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
            hintEl.textContent = office.name + ` · max ${radius} m · IP: ${ipAllowed(office) ? 'OK' : 'not allowed'}`;
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
        const ipOk = ipAllowed(office);

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
        if (readStatus() !== 'present') {
            root.style.display = 'none';
            return;
        }

        root.style.display = '';

        const office = officeById(readOfficeId());
        const latInput = findInput('latitude');
        const lngInput = findInput('longitude');
        const accInput = findInput('accuracy_meters');

        const lat = latInput?.value ? parseFloat(latInput.value) : null;
        const lng = lngInput?.value ? parseFloat(lngInput.value) : null;
        const acc = accInput?.value ? parseInt(accInput.value, 10) : null;

        updateUi(office, lat, lng, acc);
    }

    function captureGps() {
        const office = officeById(readOfficeId());
        if (!office) {
            alert('Select an office location first.');
            return;
        }

        if (!navigator.geolocation) {
            alert('Geolocation is not supported in this browser.');
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
                setHidden('client_ip', clientIp);

                updateUi(office, lat, lng, acc);
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

    gpsBtn?.addEventListener('click', captureGps);

    ['attendance_office_location_id', 'status'].forEach((name) => {
        const el = findInput(name);
        el?.addEventListener('change', refreshFromForm);
    });

    document.addEventListener('livewire:navigated', refreshFromForm);
    refreshFromForm();
})();
