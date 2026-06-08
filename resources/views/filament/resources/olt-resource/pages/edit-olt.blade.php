@php
    /** @var \App\Models\Device $record */
    $record = $this->record;
    $onusOnline = (int) $record->onus()->whereIn('onu_oper_status', ['online', 'active', 'up'])->count();
    $onusTotal = (int) $record->onus()->count();
    $health = is_array($record->olt_health) ? $record->olt_health : [];
    $cpu = $health['cpu_percent'] ?? null;
    $memory = $health['memory_percent'] ?? null;
    $temp = $health['temperature_c'] ?? null;
    $uptime = $health['uptime'] ?? $health['uptime_human'] ?? null;
    $fan = $health['fan_status'] ?? null;
    $power = $health['power_supply_status'] ?? null;
@endphp

{!! \App\Support\OltStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-olt-profile-page">
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
            </div>
        </header>

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
            <x-filament-panels::form wire:submit="save">
                {{ $this->form }}
                <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
            </x-filament-panels::form>
        </section>
    </div>
</x-filament-panels::page>
