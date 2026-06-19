@php
    /** @var \App\Models\Device $record */
    $record = $this->record;
    $onuCounts = app(\App\Services\Olt\OltOnuSnapshotCacheService::class)->counts($record);
    $onusOnline = (int) ($onuCounts['online'] ?? 0);
    $onusTotal = (int) ($onuCounts['total'] ?? 0);
    $impact = app(\App\Services\Olt\OltImpactAnalysisService::class)->forOlt($record);
    $meta = is_array($record->meta) ? $record->meta : [];
    $trafficDl = $meta['traffic_download_mbps'] ?? null;
    $trafficUl = $meta['traffic_upload_mbps'] ?? null;
    $health = is_array($record->olt_health) ? $record->olt_health : [];
    $cpu = $health['cpu_percent'] ?? null;
    $memory = $health['memory_percent'] ?? null;
    $temp = $health['temperature_c'] ?? null;
    $uptime = $health['uptime'] ?? $health['uptime_human'] ?? null;
    $fan = $health['fan_status'] ?? null;
    $power = $health['power_supply_status'] ?? null;
@endphp

{!! \App\Support\OltStyles::navigatedScript() !!}

<x-filament-panels::page
    @class([
        'isp-olt-profile-page',
        'fi-resource-edit-record-page',
        'fi-resource-olts',
        'fi-resource-record-' . $record->getKey(),
    ])
>
    <div class="olt-oc-pro">
        <header class="olt-profile-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--olt-muted);">OLT profile</p>
                <h1 class="olt-list-hero__title" style="color:var(--olt-text);">{{ $record->adminLabel() }}</h1>
                <div class="olt-profile-pills">
                    <span class="olt-profile-pill">{{ strtoupper((string) ($record->olt_driver ?? $record->vendor ?? 'OLT')) }}</span>
                    <span class="olt-profile-pill">{{ $record->management_ip ?? '—' }}</span>
                    <span class="olt-profile-pill">{{ ucfirst((string) ($record->status ?? 'unknown')) }}</span>
                </div>
            </div>
            <div class="olt-profile-metrics">
                <div class="olt-profile-metric">
                    <strong>{{ $onusOnline }}/{{ $onusTotal }}</strong>
                    <span>ONUs online</span>
                </div>
                <div class="olt-profile-metric">
                    <strong>{{ $record->ports()->count() }}</strong>
                    <span>PON ports</span>
                </div>
                @if ($cpu !== null)
                    <div class="olt-profile-metric">
                        <strong>{{ $cpu }}%</strong>
                        <span>CPU</span>
                    </div>
                @endif
                @if ($memory !== null)
                    <div class="olt-profile-metric">
                        <strong>{{ $memory }}%</strong>
                        <span>Memory</span>
                    </div>
                @endif
                @if ($temp !== null)
                    <div class="olt-profile-metric">
                        <strong>{{ $temp }} °C</strong>
                        <span>Temperature</span>
                    </div>
                @endif
                @if ($uptime !== null)
                    <div class="olt-profile-metric">
                        <strong style="font-size:0.95rem;">{{ $uptime }}</strong>
                        <span>Uptime</span>
                    </div>
                @endif
                @if ($trafficDl !== null || $trafficUl !== null)
                    <div class="olt-profile-metric">
                        <strong>{{ $trafficDl !== null ? number_format((float) $trafficDl, 1) : '—' }} ↓</strong>
                        <span>{{ $trafficUl !== null ? number_format((float) $trafficUl, 1).' ↑ Mbps' : 'Traffic Mbps' }}</span>
                    </div>
                @endif
            </div>
        </header>

        <section class="olt-oc-panel" style="margin-bottom:1rem;">
            <div class="olt-oc-panel__head">Revenue impact &amp; ONU snapshot</div>
            <div style="padding:0.85rem 1rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;font-size:0.875rem;">
                <div><strong>{{ number_format($impact['affected_customers'] ?? 0) }}</strong><br><span class="text-gray-500">Subscribers on OLT</span></div>
                <div><strong>{{ number_format($impact['offline_customers'] ?? 0) }}</strong><br><span class="text-gray-500">PPP offline</span></div>
                <div><strong>{{ number_format($impact['monthly_revenue_tk'] ?? 0, 0) }} BDT</strong><br><span class="text-gray-500">Monthly revenue</span></div>
                <div><strong>{{ number_format($impact['at_risk_revenue_tk'] ?? 0, 0) }} BDT</strong><br><span class="text-gray-500">At-risk (offline ONU)</span></div>
                <div><strong>{{ number_format($onuCounts['unauthorized'] ?? 0) }}</strong><br><span class="text-gray-500">Unauthorized ONUs</span></div>
                <div>
                    <a href="{{ \App\Filament\Pages\OltLiveTraffic::getUrl() }}?filterOlt={{ $record->id }}" class="text-primary-600 hover:underline dark:text-primary-400">Live traffic →</a>
                </div>
            </div>
        </section>

        @if ($fan !== null || $power !== null)
            <section class="olt-oc-panel" style="margin-bottom:1rem;">
                <div class="olt-oc-panel__head">Uplink &amp; chassis health</div>
                <div style="padding:0.85rem 1rem;display:flex;flex-wrap:wrap;gap:1rem;font-size:0.875rem;">
                    @if ($fan !== null)
                        <span><strong>Fan:</strong> {{ $fan }}</span>
                    @endif
                    @if ($power !== null)
                        <span><strong>Power:</strong> {{ $power }}</span>
                    @endif
                    @if ($record->last_health_polled_at)
                        <span class="text-gray-500">Last polled {{ $record->last_health_polled_at->diffForHumans() }}</span>
                    @endif
                </div>
            </section>
        @endif

        <section class="olt-profile-form">
            <x-filament-panels::form
                id="form"
                :wire:key="'olt-edit-' . $record->getKey() . '.' . $this->getFormStatePath()"
                wire:submit.prevent="save"
            >
                {{ $this->form }}
                <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
            </x-filament-panels::form>
        </section>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
