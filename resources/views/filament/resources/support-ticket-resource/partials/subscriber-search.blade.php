@php
    $query = trim($this->subscriberSearch);
    $searchUrl = route('filament.admin.resources.support-tickets.subscriber-search');
    $createUrl = \App\Filament\Resources\SupportTicketResource::getUrl('create');
    $livewireId = $this->getId();
    $selected = $this->selectedSubscriber;
    $selectedLabel = $selected
        ? trim((string) (($selected['username'] ?? '') !== '' ? $selected['username'] : ($selected['name'] ?? '')))
            .' ('.($selected['customer_code'] ?? $selected['id'] ?? '').')'
        : $query;
@endphp

<div class="isp-support-subscriber-search sp-create-search">
    <div class="sp-create-search__head">
        <label for="support-ticket-subscriber-search" class="isp-collection-search-label">
            User name (ID)
        </label>
        <span class="sp-create-search__badge">Step 1</span>
    </div>

    <div
        wire:ignore.self
        class="sp-create-combobox"
        x-data="{
            query: @js($selectedLabel),
            results: [],
            open: false,
            loading: false,
            activeIndex: -1,
            linked: @js($selected !== null),
            searchUrl: @js($searchUrl),
            createUrl: @js($createUrl),
            livewireId: @js($livewireId),
            debounce: null,
            queryForSearch() {
                const q = this.query.trim();
                const m = q.match(/^(.+?)\s*\(([^)]+)\)\s*$/);
                if (m && m[2].trim().length >= 2) {
                    return m[2].trim();
                }
                return q;
            },
            syncToLivewire() {
                const lw = window.Livewire?.find(this.livewireId);
                if (lw) {
                    lw.set('subscriberSearch', this.query.trim());
                }
            },
            label(row) {
                const user = row.username || row.name || 'Subscriber';
                return user + ' (' + (row.customer_code || row.id) + ')';
            },
            callSelect(row) {
                const id = Number(row?.id || 0);
                if (! id) {
                    return;
                }

                this.syncToLivewire();

                const lw = window.Livewire?.find(this.livewireId);
                if (lw && typeof lw.call === 'function') {
                    lw.call('selectSubscriber', id).then(() => {
                        this.linked = true;
                        this.open = false;
                    });

                    return;
                }

                if (typeof $wire !== 'undefined' && $wire.selectSubscriber) {
                    $wire.selectSubscriber(id);
                    this.linked = true;
                    this.open = false;

                    return;
                }

                const params = new URLSearchParams({
                    customer_id: String(id),
                    q: this.query.trim(),
                });
                window.location.assign(this.createUrl + '?' + params.toString());
            },
            tryAutoPick() {
                const q = this.query.trim().toLowerCase();
                if (! q || ! this.results.length) {
                    return false;
                }

                const exact = this.results.find((row) => this.label(row).toLowerCase() === q);
                if (exact) {
                    this.pick(exact, false);
                    return true;
                }

                const m = this.query.trim().match(/^(.+?)\s*\(([^)]+)\)\s*$/);
                if (m) {
                    const user = m[1].trim().toLowerCase();
                    const code = m[2].trim().toLowerCase();
                    const byBoth = this.results.find((row) => {
                        const rowCode = String(row.customer_code || '').toLowerCase();
                        const rowUser = String(row.username || row.name || '').toLowerCase();
                        return rowCode === code && (rowUser === user || rowUser.includes(user));
                    });
                    if (byBoth) {
                        this.pick(byBoth, false);
                        return true;
                    }

                    const byCode = this.results.find((row) => String(row.customer_code || '').toLowerCase() === code);
                    if (byCode) {
                        this.pick(byCode, false);
                        return true;
                    }
                }

                if (this.results.length === 1) {
                    this.pick(this.results[0], false);
                    return true;
                }

                return false;
            },
            async fetchResults() {
                const q = this.queryForSearch();
                if (q.length < 2) {
                    this.results = [];
                    this.open = false;
                    return;
                }
                this.loading = true;
                try {
                    const response = await fetch(this.searchUrl + '?' + new URLSearchParams({ q }), {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }
                    const payload = await response.json();
                    this.results = Array.isArray(payload.data) ? payload.data : [];
                    this.open = this.results.length > 0;
                    this.activeIndex = this.results.length ? 0 : -1;
                    this.syncToLivewire();
                    this.tryAutoPick();
                } catch (error) {
                    this.results = [];
                    this.open = false;
                    console.error('[ticket-subscriber-search]', error);
                } finally {
                    this.loading = false;
                }
            },
            onInput() {
                this.linked = false;
                clearTimeout(this.debounce);
                this.debounce = setTimeout(() => this.fetchResults(), 280);
            },
            pick(row, closeDropdown = true) {
                this.query = this.label(row);
                if (closeDropdown) {
                    this.open = false;
                }
                this.callSelect(row);
            },
            async onEnter(event) {
                event.preventDefault();
                if (this.open && this.activeIndex >= 0 && this.results.length) {
                    this.pick(this.results[this.activeIndex]);
                    return;
                }
                await this.fetchResults();
                if (! this.tryAutoPick()) {
                    this.open = this.results.length > 0;
                }
            },
            onKeydown(event) {
                if (event.key === 'Enter') {
                    this.onEnter(event);
                    return;
                }
                if (!this.open || !this.results.length) {
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.activeIndex = Math.max(this.activeIndex - 1, 0);
                } else if (event.key === 'Escape') {
                    this.open = false;
                }
            },
            reset() {
                this.query = '';
                this.results = [];
                this.open = false;
                this.activeIndex = -1;
                this.linked = false;
            },
        }"
        x-init="
            if (@js($selectedLabel !== '' && $selected === null && mb_strlen($query) >= 2)) {
                fetchResults();
            }
            $wire.on('subscriber-search-reset', () => reset());
            document.getElementById('form')?.addEventListener('submit', () => syncToLivewire());
        "
        @click.outside="open = false"
    >
        <div class="sp-create-combobox__field">
            <span class="sp-create-combobox__icon" aria-hidden="true">⌕</span>
            <input
                id="support-ticket-subscriber-search"
                type="text"
                x-model="query"
                @input="onInput()"
                @keydown="onKeydown($event)"
                @blur="syncToLivewire()"
                @focus="if (results.length) open = true; else if (query.trim().length >= 2) fetchResults()"
                placeholder="Type username (ID) e.g. habib3.kp (0603) — Enter to link"
                class="sp-create-combobox__input"
                autocomplete="off"
                autofocus
                maxlength="200"
            />
            <span x-show="loading" x-cloak class="sp-create-combobox__spinner" aria-hidden="true"></span>
        </div>

        <ul
            x-show="open && results.length"
            x-cloak
            class="sp-create-combobox__dropdown"
            role="listbox"
        >
            <template x-for="(row, index) in results" :key="row.id">
                <li role="option" :aria-selected="index === activeIndex ? 'true' : 'false'">
                    <button
                        type="button"
                        class="sp-create-combobox__option"
                        :class="{ 'is-active': index === activeIndex }"
                        @mousedown.prevent="pick(row)"
                        x-text="label(row)"
                    ></button>
                </li>
            </template>
        </ul>
    </div>

    <p class="isp-collection-search-hint">
        Type <strong>username (ID)</strong> — e.g. <code class="text-xs">habib3.kp (0603)</code> — press <strong>Enter</strong> or submit ticket.
        @if (\App\Support\CustomerSearchSettings::useScout())
            <span class="text-emerald-700 dark:text-emerald-400">Meilisearch enabled.</span>
        @endif
    </p>

    @unless ($selected)
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-300" id="support-ticket-search-prompt">
            Type full username (ID) and press Enter — or pick from the dropdown.
        </p>
    @endunless
</div>

@if ($selected)
    <div class="isp-support-subscriber-picked sp-create-picked mt-3 rounded-xl border-2 border-emerald-400 bg-emerald-50/90 px-3 py-2 dark:border-emerald-600 dark:bg-emerald-950/40">
        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">✓ Subscriber linked — you can save the ticket</p>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ ($selected['username'] ?? '') !== '' ? $selected['username'] : $selected['name'] }}
            <span class="font-mono text-violet-600 dark:text-violet-400">({{ $selected['customer_code'] }})</span>
        </p>
        <button
            type="button"
            wire:click="clearSubscriberSelection"
            class="mt-1 text-xs font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
        >
            Change subscriber
        </button>
    </div>
@endif
