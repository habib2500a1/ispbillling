/**
 * Support ticket create — fetch-based subscriber search (immune to Livewire DOM morph).
 */
(function () {
    'use strict';

    const INPUT_ID = 'support-ticket-subscriber-search';
    const SHELL_ID = 'support-ticket-search-shell';
    let debounceTimer = null;
    let activeController = null;

    function isMobile() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function closeMobileSidebar() {
        if (!isMobile()) {
            return;
        }

        const store = window.Alpine?.store?.('sidebar');
        if (store && typeof store.close === 'function') {
            store.close();
        }

        document.body.classList.remove('isp-admin-sidebar-open');
    }

    function shell() {
        return document.getElementById(SHELL_ID);
    }

    function input() {
        return document.getElementById(INPUT_ID);
    }

    function wireProxy(component) {
        if (!component) {
            return null;
        }

        return component.$wire ?? component;
    }

    function wire() {
        if (!window.Livewire) {
            return null;
        }

        const shellEl = shell();
        const componentId = shellEl?.dataset.componentId;
        if (componentId) {
            const fromShell = wireProxy(window.Livewire.find(componentId));
            if (fromShell) {
                return fromShell;
            }
        }

        const selectors = [
            '.fi-resource-create-record-page[wire\\:id]',
            '.isp-support-ticket-create .fi-page[wire\\:id]',
            '.isp-support-ticket-create [wire\\:id]',
        ];

        for (let i = 0; i < selectors.length; i++) {
            const el = document.querySelector(selectors[i]);
            const id = el?.getAttribute('wire:id');
            if (!id) {
                continue;
            }

            const component = wireProxy(window.Livewire.find(id));
            if (component) {
                return component;
            }
        }

        return null;
    }

    function callLivewire(component, method) {
        if (!component) {
            return Promise.reject(new Error('Livewire component not found'));
        }

        const args = Array.prototype.slice.call(arguments, 2);

        if (typeof component.call === 'function') {
            return component.call.apply(component, [method].concat(args));
        }

        if (typeof component[method] === 'function') {
            return component[method].apply(component, args);
        }

        return Promise.reject(new Error('Method not found: ' + method));
    }

    function setLoading(on) {
        const spinner = document.getElementById('support-ticket-search-spinner');
        const btn = document.getElementById('support-ticket-search-btn');
        if (spinner) {
            spinner.classList.toggle('hidden', !on);
        }
        if (btn) {
            btn.disabled = on;
            btn.textContent = on ? '…' : 'Search';
        }
    }

    function setStatus(text) {
        const status = document.getElementById('support-ticket-search-status');
        if (!status) {
            return;
        }

        if (!text) {
            status.textContent = '';
            status.classList.add('hidden');

            return;
        }

        status.textContent = text;
        status.classList.remove('hidden');
    }

    function toggleClear(show) {
        const clear = document.getElementById('support-ticket-search-clear');
        if (clear) {
            clear.classList.toggle('hidden', !show);
        }
    }

    function hidePrompt() {
        document.getElementById('support-ticket-search-prompt')?.classList.add('hidden');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderResults(rows) {
        const container = document.getElementById('support-ticket-search-results');
        if (!container) {
            return;
        }

        if (!rows.length) {
            container.innerHTML = '<div class="isp-collection-empty rounded-xl border border-dashed border-gray-300 p-6 text-center dark:border-gray-600">'
                + '<p class="font-medium text-gray-700 dark:text-gray-300">No subscriber found</p>'
                + '<p class="mt-1 text-sm text-gray-500">Try phone, customer ID, or PPP username.</p>'
                + '</div>';

            return;
        }

        const items = rows.map(function (row) {
            const due = Number(row.balance_due || 0);
            const online = !!(row.connection && row.connection.online);
            const dueClass = due > 0.009 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400';

            return '<li role="option">'
                + '<button type="button" data-customer-id="' + escapeHtml(row.id) + '" class="isp-collection-result-card sp-create-result-card w-full text-left">'
                + '<div class="flex flex-wrap items-start justify-between gap-2">'
                + '<div class="min-w-0 flex-1">'
                + '<p class="font-semibold text-gray-900 dark:text-white">' + escapeHtml(row.name)
                + ' <span class="font-mono text-sm font-normal text-violet-600 dark:text-violet-400">#' + escapeHtml(row.customer_code) + '</span></p>'
                + '<div class="mt-1 grid gap-0.5 text-xs text-gray-600 dark:text-gray-400 sm:grid-cols-2">'
                + '<span><strong class="text-gray-500">Phone:</strong> ' + escapeHtml(row.phone || '—') + '</span>'
                + '<span><strong class="text-gray-500">Username:</strong> <span class="font-mono">' + escapeHtml(row.username || '—') + '</span></span>'
                + (row.package ? '<span><strong class="text-gray-500">Package:</strong> ' + escapeHtml(row.package) + '</span>' : '')
                + (row.area ? '<span><strong class="text-gray-500">Area:</strong> ' + escapeHtml(row.area) + '</span>' : '')
                + '</div></div>'
                + '<div class="shrink-0 text-right">'
                + '<p class="text-xs font-semibold ' + dueClass + '">Due ' + Math.round(due).toLocaleString() + ' BDT</p>'
                + '<p class="text-xs font-semibold ' + (online ? 'text-emerald-600' : 'text-gray-500') + '">PPP ' + (online ? 'Online' : 'Offline') + '</p>'
                + '<p class="text-xs text-gray-500">' + escapeHtml(String(row.status || '—')) + '</p>'
                + '</div></div></button></li>';
        }).join('');

        container.innerHTML = '<p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">'
            + rows.length + ' result(s) — tap to link ticket</p>'
            + '<ul class="isp-collection-results-list space-y-2" role="listbox">' + items + '</ul>';
    }

    function markSelecting(button) {
        document.querySelectorAll('.sp-create-result-card--active').forEach(function (el) {
            el.classList.remove('sp-create-result-card--active');
        });

        if (button) {
            button.classList.add('sp-create-result-card--active');
        }
    }

    function scrollToLinkedSubscriber() {
        if (!isMobile()) {
            return;
        }

        document.querySelector('.sp-create-picked, .sp-create-preview')?.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        });
    }

    function syncUrl(query) {
        const url = new URL(window.location.href);
        if (query) {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }
        window.history.replaceState({}, '', url.toString());
    }

    function runSearch(forceQuery) {
        const shellEl = shell();
        const inputEl = input();
        if (!shellEl || !inputEl) {
            return;
        }

        const query = (forceQuery ?? inputEl.value ?? '').trim();
        toggleClear(query.length > 0);

        if (query.length < 2) {
            setStatus('');
            renderResults([]);
            syncUrl('');

            return;
        }

        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();
        setLoading(true);
        setStatus('Searching for “' + query + '”…');

        const url = shellEl.dataset.searchUrl + '?' + new URLSearchParams({ q: query }).toString();

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeController.signal,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Search failed (' + response.status + ')');
                }

                return response.json();
            })
            .then(function (payload) {
                const rows = Array.isArray(payload.data) ? payload.data : [];
                setStatus('Showing results for “' + query + '” · ' + rows.length + ' match(es)');
                renderResults(rows);
                syncUrl(query);

                const livewire = wire();
                if (livewire) {
                    livewire.set('subscriberSearch', query);
                }
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                setStatus('Search error — try again.');
                renderResults([]);
                console.error('[support-ticket-search]', error);
            })
            .finally(function () {
                setLoading(false);
                activeController = null;
            });
    }

    function bindResultsClick() {
        const shellEl = shell();
        if (!shellEl || shellEl.dataset.clickBound === '1') {
            return;
        }

        shellEl.dataset.clickBound = '1';

        shellEl.addEventListener('click', function (event) {
            const button = event.target.closest('[data-customer-id]');
            if (!button) {
                return;
            }

            event.preventDefault();

            const customerId = parseInt(button.getAttribute('data-customer-id'), 10);
            if (!customerId) {
                return;
            }

            markSelecting(button);

            const livewire = wire();
            if (!livewire) {
                console.error('[support-ticket-search] Livewire component not found');

                return;
            }

            callLivewire(livewire, 'selectSubscriber', customerId)
                .then(function () {
                    hidePrompt();
                    scrollToLinkedSubscriber();
                })
                .catch(function (error) {
                    console.error('[support-ticket-search]', error);
                });
        });
    }

    function bindShell() {
        const shellEl = shell();
        const inputEl = input();
        if (!shellEl || !inputEl) {
            return;
        }

        if (shellEl.dataset.bound !== '1') {
            shellEl.dataset.bound = '1';

            inputEl.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    runSearch();
                }, 280);
            });

            inputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    clearTimeout(debounceTimer);
                    runSearch();
                }
            });

            document.getElementById('support-ticket-search-btn')?.addEventListener('click', function () {
                clearTimeout(debounceTimer);
                runSearch();
            });

            document.getElementById('support-ticket-search-clear')?.addEventListener('click', function () {
                inputEl.value = '';
                toggleClear(false);
                setStatus('');
                renderResults([]);
                syncUrl('');
                const livewire = wire();
                if (livewire) {
                    livewire.set('subscriberSearch', '');
                }
                inputEl.focus();
            });
        }

        bindResultsClick();

        const initial = new URL(window.location.href).searchParams.get('q') || inputEl.value || '';
        if (initial.trim().length >= 2 && !shellEl.dataset.initialSearchDone) {
            shellEl.dataset.initialSearchDone = '1';
            if (document.querySelector('#support-ticket-search-results [data-customer-id]')) {
                return;
            }

            inputEl.value = initial.trim();
            runSearch(initial.trim());
        }
    }

    function init() {
        closeMobileSidebar();
        bindShell();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:init', init);
})();
