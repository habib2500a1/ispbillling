@php
    $query = trim($this->subscriberSearch);
    $searchUrl = route('filament.admin.resources.support-tickets.subscriber-search');
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
            searchUrl: @js($searchUrl),
            debounce: null,
            label(row) {
                const user = row.username || row.name || 'Subscriber';
                return user + ' (' + (row.customer_code || row.id) + ')';
            },
            async fetchResults() {
                const q = this.query.trim();
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
                } catch (error) {
                    this.results = [];
                    this.open = false;
                    console.error('[ticket-subscriber-search]', error);
                } finally {
                    this.loading = false;
                }
            },
            onInput() {
                clearTimeout(this.debounce);
                this.debounce = setTimeout(() => this.fetchResults(), 280);
            },
            pick(row) {
                this.query = this.label(row);
                this.open = false;
                $wire.selectSubscriber(row.id);
            },
            onKeydown(event) {
                if (!this.open || !this.results.length) {
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.activeIndex = Math.max(this.activeIndex - 1, 0);
                } else if (event.key === 'Enter' && this.activeIndex >= 0) {
                    event.preventDefault();
                    this.pick(this.results[this.activeIndex]);
                } else if (event.key === 'Escape') {
                    this.open = false;
                }
            },
            reset() {
                this.query = '';
                this.results = [];
                this.open = false;
                this.activeIndex = -1;
            },
        }"
        x-init="
            if (@js($selectedLabel !== '' && $selected === null && mb_strlen($query) >= 2)) {
                fetchResults();
            }
            $wire.on('subscriber-search-reset', () => reset());
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
                @focus="if (results.length) open = true"
                placeholder="Type username, name, phone or customer ID…"
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
        Type 2+ characters — dropdown list like legacy support desk.
        @if (\App\Support\CustomerSearchSettings::useScout())
            <span class="text-emerald-700 dark:text-emerald-400">Meilisearch enabled.</span>
        @endif
    </p>

    @unless ($selected)
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-300" id="support-ticket-search-prompt">
            Pick a subscriber from the dropdown — ONU details fill automatically.
        </p>
    @endunless
</div>

@if ($selected)
    <div class="isp-support-subscriber-picked sp-create-picked mt-3 rounded-xl border border-primary-200 bg-primary-50/80 px-3 py-2 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="text-xs font-bold uppercase tracking-wide text-primary-700 dark:text-primary-300">Linked</p>
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
