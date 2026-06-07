(function () {
    'use strict';

    var MOBILE_QUERY = window.matchMedia('(max-width: 767px)');

    function labelCells(root) {
        var table = root.querySelector('.cl-dir-table .fi-ta-table');
        if (!table) {
            return;
        }

        var headerCells = table.querySelectorAll('.fi-ta-header-row .fi-ta-header-cell, .fi-ta-header-row .fi-ta-actions-header-cell, .fi-ta-header-row .fi-ta-record-checkbox-cell');
        if (!headerCells.length) {
            headerCells = table.querySelectorAll('thead th, thead .fi-ta-header-cell');
        }

        headerCells.forEach(function (headerCell, index) {
            var label = headerCell.textContent.replace(/\s+/g, ' ').trim();
            if (!label) {
                return;
            }

            root.querySelectorAll('.cl-dir-table .fi-ta-row').forEach(function (row) {
                var cell = row.children[index];
                if (cell) {
                    cell.setAttribute('data-column-label', label);
                }
            });
        });

        root.querySelectorAll('.cl-dir-table .fi-ta-record-checkbox-cell').forEach(function (cell) {
            if (!cell.getAttribute('data-column-label')) {
                cell.setAttribute('data-column-label', 'Select');
            }
        });
    }

    function applyResponsiveView(root) {
        if (MOBILE_QUERY.matches) {
            root.classList.add('cl-dir-v2--cards');
            root.setAttribute('data-view', 'cards');
            return;
        }

        var storedView = null;
        try {
            storedView = localStorage.getItem('isp-clients-view');
        } catch (e) {}

        var view = storedView === 'cards' ? 'cards' : 'table';
        root.setAttribute('data-view', view);
        root.classList.toggle('cl-dir-v2--cards', view === 'cards');
    }

    function initClientsDirectoryV2() {
        var root = document.querySelector('.cl-dir-v2');
        if (!root) {
            return;
        }

        applyResponsiveView(root);
        labelCells(root);

        if (root.dataset.clDirBound === '1') {
            return;
        }

        root.dataset.clDirBound = '1';

        var toggleButtons = root.querySelectorAll('[data-cl-view]');
        toggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (MOBILE_QUERY.matches) {
                    return;
                }

                var next = button.getAttribute('data-cl-view') || 'table';
                root.setAttribute('data-view', next);
                root.classList.toggle('cl-dir-v2--cards', next === 'cards');
                toggleButtons.forEach(function (peer) {
                    peer.classList.toggle('cl-dir-view-toggle__btn--active', peer === button);
                });

                try {
                    localStorage.setItem('isp-clients-view', next);
                } catch (e) {}
            });
        });

        if (typeof MOBILE_QUERY.addEventListener === 'function') {
            MOBILE_QUERY.addEventListener('change', function () {
                applyResponsiveView(root);
                labelCells(root);
            });
        }

        var filterBtn = root.querySelector('[data-cl-filter-toggle]');
        var filterDrawer = root.querySelector('[data-cl-filter-drawer]');
        if (filterBtn && filterDrawer) {
            filterBtn.addEventListener('click', function () {
                var open = filterDrawer.hasAttribute('hidden');
                if (open) {
                    filterDrawer.removeAttribute('hidden');
                    filterBtn.setAttribute('aria-expanded', 'true');
                } else {
                    filterDrawer.setAttribute('hidden', 'hidden');
                    filterBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initClientsDirectoryV2);
    document.addEventListener('livewire:navigated', initClientsDirectoryV2);

    if (window.Livewire) {
        window.Livewire.hook('morph.updated', function () {
            window.requestAnimationFrame(initClientsDirectoryV2);
        });
    }
})();
