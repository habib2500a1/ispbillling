<div class="isp-support-subscriber-search isp-collection-search-wrap sp-create-search">
    <div class="sp-create-search__head">
        <label for="support-ticket-subscriber-search" class="isp-collection-search-label">
            Find subscriber
        </label>
        <span class="sp-create-search__badge">Step 1</span>
    </div>

    {{-- wire:ignore — Livewire morph was wiping typed text + fetch results on every request --}}
    <div wire:ignore id="support-ticket-search-shell" data-search-url="{{ route('admin.support-tickets.subscriber-search') }}">
        <div class="isp-collection-search-row">
            <div class="isp-collection-search-field">
                <span class="isp-collection-search-field__icon" aria-hidden="true">⌕</span>
                <input
                    id="support-ticket-subscriber-search"
                    type="search"
                    value="{{ $this->subscriberSearch }}"
                    placeholder="ID, phone, name, PPP username, address, invoice #…"
                    class="isp-collection-search-input"
                    autocomplete="off"
                    autofocus
                />
                <span id="support-ticket-search-spinner" class="isp-collection-search-loading hidden" aria-hidden="true"></span>
            </div>
            <button type="button" id="support-ticket-search-btn" class="isp-collection-search-btn">Search</button>
            <button type="button" id="support-ticket-search-clear" class="isp-collection-search-clear hidden" title="Clear search">×</button>
        </div>

        <p class="isp-collection-search-hint">
            Type at least 2 characters — results update as you type.
            @if (\App\Support\CustomerSearchSettings::useScout())
                <span class="text-emerald-700 dark:text-emerald-400">Meilisearch — fast, typo-tolerant.</span>
            @endif
        </p>

        <p id="support-ticket-search-status" class="isp-collection-search-active mt-2 hidden text-xs text-gray-500 dark:text-gray-400" role="status"></p>
        <div id="support-ticket-search-results" class="mt-4"></div>
    </div>

    @if ($this->selectedSubscriber)
        <div class="isp-support-subscriber-picked sp-create-picked mt-4 rounded-xl border border-primary-200 bg-primary-50/80 p-4 dark:border-primary-800 dark:bg-primary-950/30">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-700 dark:text-primary-300">Linked subscriber</p>
                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                        {{ $this->selectedSubscriber['name'] }}
                        <span class="font-mono text-sm font-normal text-violet-600 dark:text-violet-400">#{{ $this->selectedSubscriber['customer_code'] }}</span>
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ $this->selectedSubscriber['phone'] ?: '—' }} · {{ $this->selectedSubscriber['username'] }}
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="clearSubscriberSelection"
                    class="text-xs font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
                >
                    Change
                </button>
            </div>
        </div>
    @else
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-300" id="support-ticket-search-prompt">
            Search and pick a subscriber before saving the ticket.
        </p>
    @endif
</div>
