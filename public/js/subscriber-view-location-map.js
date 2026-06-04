(function () {
    'use strict';

    function initMaps() {
        if (typeof window.L === 'undefined') {
            return;
        }

        document.querySelectorAll('[data-subscriber-view-map]').forEach(function (el) {
            if (el.dataset.mapReady === '1') {
                return;
            }

            var lat = parseFloat(el.dataset.lat);
            var lng = parseFloat(el.dataset.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            el.dataset.mapReady = '1';

            var map = L.map(el, {
                scrollWheelZoom: false,
                zoomControl: true,
                attributionControl: true,
            }).setView([lat, lng], 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            L.marker([lat, lng]).addTo(map);

            setTimeout(function () {
                map.invalidateSize();
            }, 120);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMaps);
    } else {
        initMaps();
    }

    document.addEventListener('livewire:navigated', initMaps);
})();
