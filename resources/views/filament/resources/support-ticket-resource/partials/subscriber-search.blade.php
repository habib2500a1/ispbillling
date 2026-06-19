@php
    $query = trim($this->subscriberSearch);
    $createUrl = \App\Filament\Resources\SupportTicketResource::getUrl('create');
@endphp

<div class="isp-support-subscriber-search isp-collection-search-wrap sp-create-search">
    <div class="sp-create-search__head">
        <label for="support-ticket-subscriber-search" class="isp-collection-search-label">
            Find subscriber
        </label>
        <span class="sp-create-search__badge">Step 1</span>
    </div>

    <form
        method="GET"
        action="{{ $createUrl }}"
        id="support-ticket-search-form"
        class="isp-collection-search-row"
        data-navigate="false"
    >
        @if ($this->selectedSubscriberId)
            <input type="hidden" name="customer_id" value="{{ $this->selectedSubscriberId }}" />
        @endif
        <div class="isp-collection-search-field">
            <span class="isp-collection-search-field__icon" aria-hidden="true">⌕</span>
            <input
                id="support-ticket-subscriber-search"
                name="q"
                type="search"
                value="{{ $query }}"
                placeholder="ID, phone, name, PPP username, address, invoice #…"
                class="isp-collection-search-input"
                autocomplete="off"
                autofocus
                maxlength="200"
            />
        </div>
        <button type="submit" class="isp-collection-search-btn">Search</button>
        @if ($query !== '')
            <a href="{{ $createUrl }}" class="isp-collection-search-clear" data-navigate="false" title="Clear search">×</a>
        @endif
    </form>

    <p class="isp-collection-search-hint">
        Type at least 2 characters — results update as you type.
        @if (\App\Support\CustomerSearchSettings::useScout())
            <span class="text-emerald-700 dark:text-emerald-400">Meilisearch — fast, typo-tolerant.</span>
        @endif
    </p>

    @if ($query !== '')
        <p class="isp-collection-search-active mt-2 text-xs text-gray-500 dark:text-gray-400" role="status">
            @if (count($this->subscriberResults) > 0)
                Showing results for “{{ $query }}” · {{ count($this->subscriberResults) }} match(es)
            @elseif (mb_strlen($query) >= 2)
                No match for “{{ $query }}”
            @else
                Type at least 2 characters
            @endif
        </p>
    @endif

    <div id="support-ticket-search-results" class="isp-collection-results mt-4">
        @if (count($this->subscriberResults) > 0)
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">
                {{ count($this->subscriberResults) }} result(s) — tap to link ticket
            </p>
            <ul class="isp-collection-results-list space-y-2" role="listbox">
                @foreach ($this->subscriberResults as $row)
                    @php
                        $due = (float) ($row['balance_due'] ?? 0);
                        $online = (bool) (($row['connection']['online'] ?? false));
                        $isSelected = (int) ($this->selectedSubscriberId ?? 0) === (int) ($row['id'] ?? 0);
                    @endphp
                    <li role="option" wire:key="sp-search-row-{{ $row['id'] }}">
                        <a
                            href="{{ $this->pickSubscriberUrl((int) $row['id']) }}"
                            data-navigate="false"
                            @class([
                                'isp-collection-result-card sp-create-result-card block w-full text-left no-underline',
                                'sp-create-result-card--active ring-2 ring-primary-500 dark:ring-primary-400' => $isSelected,
                            ])
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ $row['name'] }}
                                        <span class="font-mono text-sm font-normal text-violet-600 dark:text-violet-400">#{{ $row['customer_code'] }}</span>
                                    </p>
                                    <div class="mt-1 grid gap-0.5 text-xs text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                                        <span><strong class="text-gray-500">Phone:</strong> {{ $row['phone'] ?: '—' }}</span>
                                        <span><strong class="text-gray-500">Username:</strong> <span class="font-mono">{{ $row['username'] ?: '—' }}</span></span>
                                        @if (! empty($row['package']))
                                            <span><strong class="text-gray-500">Package:</strong> {{ $row['package'] }}</span>
                                        @endif
                                        @if (! empty($row['area']))
                                            <span><strong class="text-gray-500">Area:</strong> {{ $row['area'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p @class([
                                        'text-xs font-semibold',
                                        'text-amber-600 dark:text-amber-400' => $due > 0.009,
                                        'text-emerald-600 dark:text-emerald-400' => $due <= 0.009,
                                    ])>
                                        Due {{ number_format($due, 0) }} BDT
                                    </p>
                                    <p @class([
                                        'text-xs font-semibold',
                                        'text-emerald-600' => $online,
                                        'text-gray-500' => ! $online,
                                    ])>
                                        PPP {{ $online ? 'Online' : 'Offline' }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $row['status'] ?? '—' }}</p>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @elseif (mb_strlen($query) >= 2)
            <div class="isp-collection-empty rounded-xl border border-dashed border-gray-300 p-6 text-center dark:border-gray-600">
                <p class="font-medium text-gray-700 dark:text-gray-300">No subscriber found</p>
                <p class="mt-1 text-sm text-gray-500">Try phone, customer ID, or PPP username.</p>
            </div>
        @endif
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
