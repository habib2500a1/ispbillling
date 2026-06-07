(function () {
    'use strict';

    function initMapElement(el) {
        if (typeof window.L === 'undefined') {
            return;
        }

        if (el.dataset.mapReady === '1') {
            return;
        }

        var lat = parseFloat(el.dataset.lat);
        var lng = parseFloat(el.dataset.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        el.dataset.mapReady = '1';

        var label = el.dataset.label || 'Client';
        var map = L.map(el, {
            scrollWheelZoom: false,
            zoomControl: true,
            attributionControl: true,
        }).setView([lat, lng], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        L.marker([lat, lng]).addTo(map).bindPopup(label);

        setTimeout(function () {
            map.invalidateSize();
        }, 120);

        el._leafletMap = map;
    }

    function initMaps(root) {
        root = root || document;
        root.querySelectorAll('[data-subscriber-view-map]').forEach(initMapElement);
    }

    function openFullscreenModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('isp-cv-map-modal-open');

        var mapEl = modal.querySelector('[data-subscriber-view-map]');
        if (mapEl) {
            initMapElement(mapEl);
            setTimeout(function () {
                if (mapEl._leafletMap) {
                    mapEl._leafletMap.invalidateSize();
                }
            }, 180);
        }
    }

    function closeFullscreenModal(modal) {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.isp-cv-map-modal:not([hidden])')) {
            document.body.classList.remove('isp-cv-map-modal-open');
        }
    }

    function initFullscreenControls(root) {
        root = root || document;

        root.querySelectorAll('[data-map-open-fullscreen]').forEach(function (button) {
            if (button.dataset.mapBound === '1') {
                return;
            }

            button.dataset.mapBound = '1';
            button.addEventListener('click', function () {
                openFullscreenModal(button.dataset.mapTarget);
            });
        });

        root.querySelectorAll('[data-map-close-fullscreen]').forEach(function (button) {
            if (button.dataset.mapBound === '1') {
                return;
            }

            button.dataset.mapBound = '1';
            button.addEventListener('click', function () {
                closeFullscreenModal(button.closest('.isp-cv-map-modal'));
            });
        });
    }

    function boot() {
        initMaps();
        initFullscreenControls();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', boot);
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.isp-cv-map-modal:not([hidden])').forEach(closeFullscreenModal);
    });
})();
