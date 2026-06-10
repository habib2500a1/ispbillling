(function () {
    'use strict';

    function submitOnlineClientsSearch() {
        var form = document.getElementById('oc-clients-search-form');
        var input = document.getElementById('oc-clients-search-input');

        if (!form || !input) {
            return;
        }

        var base = form.getAttribute('action') || window.location.pathname;
        var term = String(input.value || '').trim();
        var focusKey = 'oc-clients-search-focus';
        var cursorKey = 'oc-clients-search-cursor';

        try {
            window.sessionStorage.setItem(focusKey, '1');
            window.sessionStorage.setItem(cursorKey, String(input.selectionStart ?? input.value.length));
        } catch (e) {}

        var url = term ? base + (base.indexOf('?') >= 0 ? '&' : '?') + 'tableSearch=' + encodeURIComponent(term) : base;
        window.location.assign(url);
    }

    function restoreOnlineClientsSearchFocus() {
        var input = document.getElementById('oc-clients-search-input');

        if (!input) {
            return;
        }

        var focusKey = 'oc-clients-search-focus';
        var cursorKey = 'oc-clients-search-cursor';

        try {
            if (window.sessionStorage.getItem(focusKey) !== '1') {
                return;
            }

            input.focus();
            var cursor = Number(window.sessionStorage.getItem(cursorKey));

            if (!Number.isNaN(cursor)) {
                input.setSelectionRange(cursor, cursor);
            }

            window.sessionStorage.removeItem(focusKey);
            window.sessionStorage.removeItem(cursorKey);
        } catch (e) {}
    }

    function bindOnlineClientsSearch() {
        var form = document.getElementById('oc-clients-search-form');
        var input = document.getElementById('oc-clients-search-input');

        if (!form || !input || input.dataset.ocSearchBound === '1') {
            restoreOnlineClientsSearchFocus();
            return;
        }

        input.dataset.ocSearchBound = '1';

        var timer;

        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(submitOnlineClientsSearch, 500);
        });

        input.addEventListener('search', function () {
            window.clearTimeout(timer);
            submitOnlineClientsSearch();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(timer);
            submitOnlineClientsSearch();
        });

        restoreOnlineClientsSearchFocus();
    }

    window.ispSubmitOnlineClientsSearch = submitOnlineClientsSearch;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindOnlineClientsSearch);
    } else {
        bindOnlineClientsSearch();
    }

    document.addEventListener('livewire:navigated', bindOnlineClientsSearch);
})();
