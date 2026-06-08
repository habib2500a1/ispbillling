/**
 * Mini GIS preview on support ticket workspace.
 */
(function () {
    'use strict';

    function initMap(el) {
        const lat = parseFloat(el.dataset.lat || '');
        const lng = parseFloat(el.dataset.lng || '');
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || typeof L === 'undefined') {
            return;
        }

        if (el._spMap) {
            el._spMap.remove();
            el._spMap = null;
        }

        const map = L.map(el, {
            zoomControl: false,
            attributionControl: false,
            dragging: true,
            scrollWheelZoom: false,
        }).setView([lat, lng], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);

        L.marker([lat, lng]).addTo(map);
        el._spMap = map;

        setTimeout(() => map.invalidateSize(), 200);
    }

    function boot() {
        document.querySelectorAll('[data-sp-mini-map]').forEach(initMap);
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('livewire:navigated', boot);
})();
