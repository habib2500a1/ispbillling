<div class="isp-support-subscriber-search isp-collection-search-wrap sp-create-search">
    <div class="sp-create-search__head">
        <label for="support-ticket-subscriber-search" class="isp-collection-search-label">
            Find subscriber
        </label>
        <span class="sp-create-search__badge">Step 1</span>
    </div>
    <div class="isp-collection-search-row">
        <div class="isp-collection-search-field">
            <span class="isp-collection-search-field__icon" aria-hidden="true">⌕</span>
            <input
                id="support-ticket-subscriber-search"
                type="search"
                wire:model.live.debounce.350ms="subscriberSearch"
                placeholder="ID, phone, name, PPP username, address, invoice #…"
                class="isp-collection-search-input"
                autocomplete="off"
                autofocus
                wire:keydown.enter.prevent="runSubscriberSearch"
            />
            <span wire:loading wire:target="subscriberSearch,runSubscriberSearch" class="isp-collection-search-loading" aria-hidden="true"></span>
        </div>
        <button type="button" wire:click="runSubscriberSearch" class="isp-collection-search-btn" wire:loading.attr="disabled" wire:target="runSubscriberSearch">
            <span wire:loading.remove wire:target="runSubscriberSearch">Search</span>
            <span wire:loading wire:target="runSubscriberSearch">…</span>
        </button>
        @if ($this->subscriberSearch !== '')
            <button type="button" wire:click="$set('subscriberSearch', '')" class="isp-collection-search-clear" title="Clear search">×</button>
        @endif
    </div>

    <p class="isp-collection-search-hint">
        Type at least 2 characters — customer code, mobile, name, MikroTik/RADIUS username, or address.
        <span class="isp-collection-search-hint__links">Press <kbd>Enter</kbd> to search.</span>
    </p>

    @if ($this->subscriberSearching)
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400" wire:loading wire:target="subscriberSearch,runSubscriberSearch">
            Searching subscribers…
        </p>
    @endif

    @if ($this->subscriberSearch !== '' && ! $this->subscriberSearching && $this->subscriberResults->isEmpty())
        <div class="isp-collection-empty mt-4 rounded-xl border border-dashed border-gray-300 p-6 text-center dark:border-gray-600">
            <p class="font-medium text-gray-700 dark:text-gray-300">No subscriber found</p>
            <p class="mt-1 text-sm text-gray-500">Try phone number, customer ID (e.g. TST0001), or PPP username.</p>
        </div>
    @endif

    @if ($this->subscriberResults->isNotEmpty())
        <div class="isp-collection-results mt-4 {{ $this->selectedSubscriber ? 'isp-collection-results--with-panel' : '' }}">
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                {{ $this->subscriberResults->count() }} result(s) — tap to link ticket
            </p>
            <ul class="space-y-2 {{ $this->selectedSubscriber ? 'max-h-52 overflow-y-auto' : '' }}" role="listbox">
                @foreach ($this->subscriberResults as $row)
                    <li role="option" aria-selected="{{ (int) ($this->selectedSubscriberId ?? 0) === (int) $row['id'] ? 'true' : 'false' }}">
                        <button
                            type="button"
                            wire:click="selectSubscriber({{ $row['id'] }})"
                            class="isp-collection-result-card sp-create-result-card w-full text-left {{ (int) ($this->selectedSubscriberId ?? 0) === (int) $row['id'] ? 'ring-2 ring-primary-500 dark:ring-primary-400 sp-create-result-card--active' : '' }}"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ $row['name'] }}
                                        <span class="font-mono text-sm font-normal text-violet-600 dark:text-violet-400">#{{ $row['customer_code'] }}</span>
                                    </p>
                                    <div class="mt-1 grid gap-0.5 text-xs text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                                        <span><strong class="text-gray-500">Phone:</strong> {{ $row['phone'] ?: '—' }}</span>
                                        <span><strong class="text-gray-500">Username:</strong> <span class="font-mono">{{ $row['username'] }}</span></span>
                                        @if (! empty($row['package']))
                                            <span><strong class="text-gray-500">Package:</strong> {{ $row['package'] }}</span>
                                        @endif
                                        @if (! empty($row['area']))
                                            <span><strong class="text-gray-500">Area:</strong> {{ $row['area'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    @php
                                        $conn = $row['connection'] ?? [];
                                        $due = (float) ($row['balance_due'] ?? 0);
                                    @endphp
                                    <p class="text-xs font-semibold {{ $due > 0.009 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        Due {{ number_format($due, 0) }} BDT
                                    </p>
                                    <p class="text-xs font-semibold {{ ($conn['online'] ?? false) ? 'text-emerald-600' : 'text-gray-500' }}">
                                        PPP {{ ($conn['online'] ?? false) ? 'Online' : 'Offline' }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ ucfirst((string) ($row['status'] ?? '—')) }}</p>
                                </div>
                            </div>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

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
    @elseif ($this->subscriberSearch === '')
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">
            Search and pick a subscriber before saving the ticket.
        </p>
    @endif
</div>
