<x-filament-panels::page class="isp-olt-mac-page">
    <div class="space-y-5">
        <section class="isp-olt-mac-hero">
            <div>
                <p class="isp-olt-mac-hero__eyebrow">OLT &amp; Tools</p>
                <h2 class="isp-olt-mac-hero__title">OLT MAC table</h2>
                <p class="isp-olt-mac-hero__sub">
                    Learned MAC addresses from OLT SNMP sync — filter by OLT, search by MAC, port, or host.
                </p>
            </div>
            <div class="isp-olt-mac-hero__total">
                <span class="isp-olt-mac-hero__total-label">Total MAC</span>
                <strong>{{ number_format($this->totalMacs) }}</strong>
            </div>
        </section>

        <section class="isp-olt-mac-filters">
            <div class="isp-olt-mac-filters__grid">
                <div>
                    <label class="isp-olt-mac-filters__label" for="filter-olt">OLT</label>
                    <select id="filter-olt" wire:model.live="filterOlt" class="isp-olt-mac-filters__input">
                        <option value="">All OLT</option>
                        @foreach ($this->oltOptions as $olt)
                            <option value="{{ $olt['id'] }}">{{ $olt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="isp-olt-mac-filters__label" for="filter-search">Search</label>
                    <input
                        id="filter-search"
                        type="search"
                        wire:model.live.debounce.400ms="macTableSearch"
                        placeholder="MAC, port, ONU, OLT name, host…"
                        class="isp-olt-mac-filters__input"
                    />
                </div>
            </div>
            <div class="isp-olt-mac-filters__actions">
                <x-filament::button type="button" wire:click="applyFilters" icon="heroicon-o-funnel" size="sm">
                    Filter
                </x-filament::button>
                <x-filament::button type="button" wire:click="resetFilters" color="gray" size="sm">
                    Reset
                </x-filament::button>
            </div>
        </section>

        @if ($this->oltSummary->isNotEmpty())
            <div class="isp-olt-mac-summary">
                @foreach ($this->oltSummary->take(8) as $card)
                    <button
                        type="button"
                        wire:click="$set('filterOlt', '{{ $card['olt_id'] }}')"
                        @class([
                            'isp-olt-mac-summary__card',
                            'isp-olt-mac-summary__card--active' => (string) $filterOlt === (string) $card['olt_id'],
                        ])
                    >
                        <span class="isp-olt-mac-summary__olt">{{ $card['olt_label'] }}</span>
                        <strong class="isp-olt-mac-summary__count">{{ number_format($card['mac_count']) }} MAC</strong>
                        <span class="isp-olt-mac-summary__seen">
                            Last seen {{ $card['last_seen']?->format('d/m/y H:i') ?? '—' }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="isp-olt-mac-table-wrap">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
