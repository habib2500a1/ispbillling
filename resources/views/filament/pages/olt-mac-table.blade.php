<x-filament-panels::page class="isp-olt-mac-page">
    @php
        $ponRows = $this->ponMacRows;
    @endphp

    <div
        class="space-y-5"
        @if ($autoRefreshSeconds > 0 && $viewMode === 'pon')
            wire:poll.{{ $autoRefreshSeconds }}s="pollRefreshPonMac"
        @endif
    >
        <section class="isp-olt-mac-hero">
            <div>
                <p class="isp-olt-mac-hero__eyebrow">OLT &amp; Tools · Aveis / ISP Digital</p>
                <h2 class="isp-olt-mac-hero__title">PON MAC table</h2>
                <p class="isp-olt-mac-hero__sub">
                    Customer router MAC behind each ONU — learned from OLT forwarding table (FDB).
                    Auto refresh uses FDB only; full ONU sync is skipped so ONUs stay stable.
                </p>
            </div>
            <div class="isp-olt-mac-hero__total">
                <span class="isp-olt-mac-hero__total-label">Total MAC</span>
                <strong>{{ number_format($this->totalMacs) }}</strong>
                @if ($autoRefreshSeconds > 0)
                    <span class="isp-olt-mac-hero__refresh-badge">Auto {{ $autoRefreshSeconds }}s</span>
                @endif
            </div>
        </section>

        <div class="isp-olt-mac-tabs">
            <button
                type="button"
                wire:click="setViewMode('pon')"
                @class(['isp-olt-mac-tabs__btn', 'isp-olt-mac-tabs__btn--active' => $viewMode === 'pon'])
            >
                PON MAC (customer)
            </button>
            <button
                type="button"
                wire:click="setViewMode('onu')"
                @class(['isp-olt-mac-tabs__btn', 'isp-olt-mac-tabs__btn--active' => $viewMode === 'onu'])
            >
                ONU inventory MAC
            </button>
        </div>

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
                        placeholder="MAC, VLAN, port, ONU, name, OLT…"
                        class="isp-olt-mac-filters__input"
                    />
                </div>
            </div>
            <div class="isp-olt-mac-filters__actions">
                @if ($viewMode === 'pon')
                    <x-filament::button
                        type="button"
                        wire:click="refreshPonMac"
                        wire:loading.attr="disabled"
                        wire:target="refreshPonMac"
                        icon="heroicon-o-arrow-path"
                        size="sm"
                    >
                        <span wire:loading.remove wire:target="refreshPonMac">Refresh PON MAC</span>
                        <span wire:loading wire:target="refreshPonMac">Refreshing…</span>
                    </x-filament::button>
                    <x-filament::button
                        type="button"
                        wire:click="openAutoRefreshModal"
                        color="gray"
                        icon="heroicon-o-clock"
                        size="sm"
                    >
                        Auto refresh
                    </x-filament::button>
                @endif
                <x-filament::button type="button" wire:click="applyFilters" icon="heroicon-o-funnel" color="gray" size="sm">
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

        @if ($viewMode === 'pon')
            <div class="isp-olt-mac-table-wrap">
                @if ($ponRows->isEmpty())
                    <div class="isp-pon-mac-empty">
                        <h3>No PON MAC entries yet</h3>
                        <p>Click <strong>Refresh PON MAC</strong> to pull the OLT forwarding table (FDB). This is lighter than full ONU sync.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="isp-pon-mac-table">
                            <thead>
                                <tr>
                                    <th>MAC</th>
                                    <th>VLAN ID</th>
                                    <th>Port ID</th>
                                    <th>ONU ID</th>
                                    <th>Name</th>
                                    <th>MAC Address Type</th>
                                    <th>OLT</th>
                                    <th>Synced</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ponRows as $row)
                                    <tr wire:key="pon-mac-{{ $row['row_key'] }}">
                                        <td><span class="isp-olt-mac-pill">{{ $row['mac'] }}</span></td>
                                        <td>{{ $row['vlan'] ?? '—' }}</td>
                                        <td class="font-mono text-sm">{{ $row['port_id'] }}</td>
                                        <td class="font-mono text-sm">{{ $row['onu_id'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $row['mac_type'] }}</td>
                                        <td>{{ $row['olt_label'] }}</td>
                                        <td class="text-xs text-gray-500">{{ $row['synced_at']?->format('d/m/y H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="isp-pon-mac-footnote">{{ number_format($ponRows->count()) }} learned MAC(s) · FDB refresh only (no ONU reprovision)</p>
                @endif
            </div>
        @else
            <div class="isp-olt-mac-table-wrap">
                {{ $this->table }}
            </div>
        @endif
    </div>

    @if ($showAutoRefreshModal)
        <div class="isp-pon-mac-modal-backdrop" wire:click="closeAutoRefreshModal"></div>
        <div class="isp-pon-mac-modal" role="dialog" aria-labelledby="pon-mac-refresh-title">
            <h3 id="pon-mac-refresh-title" class="isp-pon-mac-modal__title">Setting Auto Refresh Interval</h3>
            <label class="isp-olt-mac-filters__label" for="auto-refresh-interval">Interval (seconds)</label>
            <input
                id="auto-refresh-interval"
                type="number"
                min="0"
                max="3600"
                step="1"
                wire:model.defer="autoRefreshDraft"
                class="isp-olt-mac-filters__input"
            />
            <p class="isp-pon-mac-modal__hint">
                Page auto refresh frequency, unit second. <strong>0</strong> means no refresh. Range <strong>5 – 3600</strong>.
                Uses FDB walk only — does not re-sync ONU table.
            </p>
            <div class="isp-pon-mac-modal__actions">
                <x-filament::button type="button" wire:click="applyAutoRefreshInterval" size="sm">
                    Apply
                </x-filament::button>
                <x-filament::button type="button" wire:click="closeAutoRefreshModal" color="gray" size="sm">
                    Cancel
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
