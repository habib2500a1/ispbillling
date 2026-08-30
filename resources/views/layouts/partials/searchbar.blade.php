{{-- Live customer search (name, ID, username, mobile) --}}
<ul class="navbar-nav align-items-center flex-grow-1 px-2" style="max-width: 560px;">
    <li class="nav-item w-100">
        <div class="search-box js-live-search position-relative w-100">
            <form class="position-relative" action="{{ route('customers.index') }}" method="get" autocomplete="off">
                <input class="form-control search-input js-live-search-input" type="search" name="q"
                    placeholder="{{ __('Search client, ID, mobile, username…') }}"
                    aria-label="{{ __('Search') }}"
                    autocomplete="off"
                    spellcheck="false">
                <i class="fas fa-search search-box-icon"></i>
            </form>
            <div class="js-live-search-panel dropdown-menu border font-base start-0 mt-1 py-0 overflow-hidden w-100 shadow"
                style="display:none; max-width: 560px;">
                <div class="js-live-search-list scrollbar py-2" style="max-height: 22rem;"></div>
                <div class="js-live-search-empty text-center py-3 px-3 small text-muted d-none">{{ __('No client found.') }}</div>
            </div>
        </div>
    </li>
</ul>

@once
    @push('scripts')
        <script>
            (function () {
                if (window.__anetbdLiveSearchBound) {
                    return;
                }
                window.__anetbdLiveSearchBound = true;

                var url = @json(route('search.live'));
                var timer = null;
                var lastQ = '';

                function panelEl(box) {
                    return box.querySelector('.js-live-search-panel');
                }
                function listEl(box) {
                    return box.querySelector('.js-live-search-list');
                }
                function emptyEl(box) {
                    return box.querySelector('.js-live-search-empty');
                }

                function hide(box) {
                    var p = panelEl(box);
                    if (p) p.style.display = 'none';
                }

                function show(box) {
                    var p = panelEl(box);
                    if (p) p.style.display = 'block';
                }

                function render(box, rows, listUrl, q) {
                    var list = listEl(box);
                    var empty = emptyEl(box);
                    list.innerHTML = '';
                    if (!rows.length) {
                        empty.classList.remove('d-none');
                        show(box);
                        return;
                    }
                    empty.classList.add('d-none');
                    rows.forEach(function (row) {
                        var a = document.createElement('a');
                        a.className = 'dropdown-item px-3 py-2';
                        a.href = row.url;
                        var due = Number(row.due || 0);
                        a.innerHTML =
                            '<div class="fw-semibold text-dark">' + escapeHtml(row.name || '—') + '</div>' +
                            '<div class="small text-muted">' +
                            escapeHtml(row.id || '') +
                            (row.username ? ' · ' + escapeHtml(row.username) : '') +
                            (row.mobile ? ' · ' + escapeHtml(row.mobile) : '') +
                            (due > 0 ? ' · {{ __("Due") }} ৳' + due.toFixed(0) : '') +
                            '</div>';
                        list.appendChild(a);
                    });
                    if (listUrl && q) {
                        var more = document.createElement('a');
                        more.className = 'dropdown-item px-3 py-2 border-top small fw-semibold';
                        more.href = listUrl;
                        more.textContent = '{{ __("See all in client list") }}';
                        list.appendChild(more);
                    }
                    show(box);
                }

                function escapeHtml(s) {
                    return String(s).replace(/[&<>"']/g, function (c) {
                        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
                    });
                }

                function query(box, q) {
                    q = (q || '').trim();
                    if (q.length < 2) {
                        hide(box);
                        return;
                    }
                    if (q === lastQ) {
                        show(box);
                        return;
                    }
                    lastQ = q;
                    fetch(url + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        var input = box.querySelector('.js-live-search-input');
                        if (!input || input.value.trim() !== q) {
                            return;
                        }
                        render(box, data.results || [], data.list_url, q);
                    }).catch(function () {
                        hide(box);
                    });
                }

                document.addEventListener('input', function (e) {
                    var input = e.target.closest('.js-live-search-input');
                    if (!input) return;
                    var box = input.closest('.js-live-search');
                    clearTimeout(timer);
                    timer = setTimeout(function () { query(box, input.value); }, 220);
                });

                document.addEventListener('focusin', function (e) {
                    var input = e.target.closest('.js-live-search-input');
                    if (!input) return;
                    var box = input.closest('.js-live-search');
                    if ((input.value || '').trim().length >= 2) {
                        query(box, input.value);
                    }
                });

                document.addEventListener('click', function (e) {
                    if (!e.target.closest('.js-live-search')) {
                        document.querySelectorAll('.js-live-search').forEach(hide);
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    document.querySelectorAll('.js-live-search').forEach(hide);
                });
            })();
        </script>
    @endpush
@endonce
