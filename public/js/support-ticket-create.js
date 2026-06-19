/**
 * Support ticket create — GET search (same reliable pattern as bill collection desk).
 */
(function () {
    'use strict';

    const FOCUS_KEY = 'isp-ticket-create-search-focus';
    const CURSOR_KEY = 'isp-ticket-create-search-cursor';

    function form() {
        return document.getElementById('support-ticket-search-form');
    }

    function input() {
        return document.getElementById('support-ticket-subscriber-search');
    }

    function submitTicketSearch() {
        const searchForm = form();
        const searchInput = input();

        if (!searchForm || !searchInput) {
            return;
        }

        const term = String(searchInput.value || '').trim();
        if (term.length < 2) {
            return;
        }

        const params = new URLSearchParams();
        params.set('q', term);

        const current = new URL(window.location.href);
        const customerId = current.searchParams.get('customer_id');
        if (customerId) {
            params.set('customer_id', customerId);
        }

        try {
            window.sessionStorage.setItem(FOCUS_KEY, '1');
            window.sessionStorage.setItem(CURSOR_KEY, String(searchInput.selectionStart ?? searchInput.value.length));
        } catch (error) {
            /* ignore */
        }

        const base = searchForm.getAttribute('action') || window.location.pathname;
        const query = params.toString();
        const url = query ? base + (base.indexOf('?') >= 0 ? '&' : '?') + query : base;

        window.location.assign(url);
    }

    function restoreSearchFocus() {
        const searchInput = input();

        if (!searchInput) {
            return;
        }

        try {
            if (window.sessionStorage.getItem(FOCUS_KEY) !== '1') {
                return;
            }

            searchInput.focus();
            const cursor = Number(window.sessionStorage.getItem(CURSOR_KEY));

            if (!Number.isNaN(cursor)) {
                searchInput.setSelectionRange(cursor, cursor);
            }

            window.sessionStorage.removeItem(FOCUS_KEY);
            window.sessionStorage.removeItem(CURSOR_KEY);
        } catch (error) {
            /* ignore */
        }
    }

    function bindTicketSearch() {
        const searchForm = form();
        const searchInput = input();

        if (!searchForm || !searchInput) {
            return;
        }

        if (searchInput.dataset.ticketSearchBound !== '1') {
            searchInput.dataset.ticketSearchBound = '1';

            let timer;

            searchInput.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(submitTicketSearch, 450);
            });

            searchInput.addEventListener('search', function () {
                window.clearTimeout(timer);
                submitTicketSearch();
            });

            searchForm.addEventListener('submit', function (event) {
                event.preventDefault();
                window.clearTimeout(timer);
                submitTicketSearch();
            });
        }

        restoreSearchFocus();
    }

    window.ispSubmitTicketSearch = submitTicketSearch;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTicketSearch);
    } else {
        bindTicketSearch();
    }

    document.addEventListener('livewire:navigated', bindTicketSearch);
})();
