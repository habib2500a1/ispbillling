/**
 * Inventory Asset Intelligence — lightweight UI enhancements (no API changes).
 */
(function () {
    'use strict';

    function markInventoryModule() {
        const path = window.location.pathname || '';
        if (/inventory-hub|warehouses|products|inventory-sales|purchase-orders|stock-movements|vendors|fixed-assets|store-device-loans|devices|pop-boxes|inventory-report|inventory-warranty/.test(path)) {
            document.body.classList.add('isp-inventory-module');
        }
    }

    function animateLifecycleCounts(root) {
        root.querySelectorAll('.iv-lifecycle__stage[data-count]').forEach(function (stage) {
            const target = parseInt(stage.getAttribute('data-count') || '0', 10);
            const el = stage.querySelector('.iv-lifecycle__count');
            if (!el || target <= 0) {
                return;
            }

            let current = 0;
            const steps = 18;
            const increment = Math.max(1, Math.ceil(target / steps));
            const timer = window.setInterval(function () {
                current = Math.min(target, current + increment);
                el.textContent = current.toLocaleString();
                if (current >= target) {
                    window.clearInterval(timer);
                }
            }, 30);
        });
    }

    function loadJsBarcode(callback) {
        if (window.JsBarcode) {
            callback();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    function initBarcodePreview() {
        const form = document.querySelector('.isp-inventory-form-page, .fi-resource-products');
        if (!form) {
            return;
        }

        let preview = form.querySelector('[data-iv-barcode-preview]');
        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'iv-barcode-preview';
            preview.setAttribute('data-iv-barcode-preview', '1');
            preview.innerHTML = '<svg></svg><span class="iv-barcode-preview__code"></span>';
            const card = form.querySelector('.iv-form-card');
            if (card) {
                card.insertBefore(preview, card.firstChild);
            }
        }

        const svg = preview.querySelector('svg');
        const codeEl = preview.querySelector('.iv-barcode-preview__code');

        function render(value) {
            const code = (value || '').trim();
            if (!code) {
                preview.style.display = 'none';
                return;
            }
            preview.style.display = 'block';
            codeEl.textContent = code;
            loadJsBarcode(function () {
                try {
                    window.JsBarcode(svg, code, {
                        format: 'CODE128',
                        width: 2,
                        height: 56,
                        displayValue: false,
                        margin: 8,
                    });
                } catch (e) {
                    preview.style.display = 'none';
                }
            });
        }

        const input = form.querySelector('input[id*="barcode"], input[wire\\:model*="barcode"]');
        if (input) {
            render(input.value);
            input.addEventListener('input', function () {
                render(input.value);
            });
        }

        document.addEventListener('livewire:navigated', function () {
            const next = document.querySelector('input[id*="barcode"], input[wire\\:model*="barcode"]');
            if (next) {
                render(next.value);
            }
        });
    }

    function init() {
        markInventoryModule();
        const lifecycle = document.querySelector('[data-iv-lifecycle]');
        if (lifecycle) {
            animateLifecycleCounts(lifecycle);
        }
        initBarcodePreview();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
})();
