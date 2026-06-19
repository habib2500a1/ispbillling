@php
    $diag = $diagnostics ?? null;
@endphp

@if ($diag)
    @if (! empty($diag['network']))
        <div class="isp-net-identity">
            <p class="isp-net-identity__label">Network (আপনার সেট করা নাম)</p>
            <dl class="isp-net-identity__grid">
                <div class="isp-net-identity__item">
                    <dt>PON port</dt>
                    <dd class="font-mono">{{ $diag['network']['pon_port'] }}</dd>
                </div>
                <div class="isp-net-identity__item">
                    <dt>MikroTik</dt>
                    <dd>{{ $diag['network']['mikrotik'] }}</dd>
                </div>
                <div class="isp-net-identity__item">
                    <dt>VLAN</dt>
                    <dd class="font-mono">{{ $diag['network']['vlan'] }}</dd>
                </div>
            </dl>
        </div>
    @endif
    <div class="isp-net-diag-grid">
        <article class="isp-net-diag-card isp-net-diag-card--router">
            <header class="isp-net-diag-card__head">
                <span class="isp-net-diag-card__title">Router Info</span>
                <span class="isp-net-diag-card__icon isp-net-diag-card__icon--router" aria-hidden="true"></span>
            </header>
            <p class="isp-net-diag-card__name">{{ $diag['router']['name'] }}</p>
            <dl class="isp-net-diag-card__metrics">
                <div>
                    <dt>IP Address</dt>
                    <dd class="font-mono text-sm">{{ $diag['router']['ip'] }}</dd>
                </div>
                <div>
                    <dt>MAC Address</dt>
                    <dd class="font-mono text-sm">{{ $diag['router']['mac'] }}</dd>
                </div>
                <div>
                    <dt>Uptime</dt>
                    <dd>{{ $diag['router']['uptime'] }}</dd>
                </div>
            </dl>
            @if ($diag['router']['mac_unlock'])
                <p class="isp-net-diag-card__footer isp-net-diag-card__footer--inline">
                    <x-filament::icon icon="heroicon-o-lock-open" class="h-4 w-4" />
                    MAC Unlock
                </p>
            @endif
        </article>

        <article class="isp-net-diag-card isp-net-diag-card--onu">
            <header class="isp-net-diag-card__head">
                <span class="isp-net-diag-card__title">ONU Diagnostics</span>
                <span class="isp-net-diag-card__icon isp-net-diag-card__icon--onu" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-signal" class="h-5 w-5" />
                </span>
            </header>
            <div class="isp-net-diag-card__badges">
                <span class="isp-net-diag-badge isp-net-diag-badge--vendor">{{ $diag['onu']['vendor_label'] }}</span>
                <span @class([
                    'isp-net-diag-badge',
                    'isp-net-diag-badge--online' => $diag['onu']['status_online'],
                    'isp-net-diag-badge--offline' => ! $diag['onu']['status_online'],
                ])>
                    <span class="isp-net-diag-badge__dot" aria-hidden="true"></span>
                    {{ $diag['onu']['status'] }}
                </span>
            </div>
            <dl class="isp-net-diag-card__metrics isp-net-diag-card__metrics--split">
                <div>
                    <dt>Rx Power</dt>
                    <dd @class([
                        'isp-net-diag-metric-value',
                        'isp-net-diag-metric-value--warning' => ($diag['onu']['rx_tone'] ?? '') === 'warning' || ($diag['onu']['rx_tone'] ?? '') === 'danger',
                        'isp-net-diag-metric-value--success' => ($diag['onu']['rx_tone'] ?? '') === 'success',
                    ])>{{ $diag['onu']['rx_display'] }}</dd>
                </div>
                <div>
                    <dt>Tx Power</dt>
                    <dd class="font-mono">{{ $diag['onu']['tx_display'] }}</dd>
                </div>
                <div>
                    <dt>Temp</dt>
                    <dd @class([
                        'isp-net-diag-metric-value',
                        'isp-net-diag-metric-value--warning' => ($diag['onu']['temperature_tone'] ?? '') === 'warning',
                        'isp-net-diag-metric-value--danger' => ($diag['onu']['temperature_tone'] ?? '') === 'danger',
                    ])>{{ $diag['onu']['temperature'] }}</dd>
                </div>
                <div>
                    <dt>Voltage</dt>
                    <dd class="font-mono">{{ $diag['onu']['voltage'] }}</dd>
                </div>
            </dl>
            <footer class="isp-net-diag-card__footer">
                <span class="block text-xs"><strong>OLT:</strong> {{ $diag['onu']['olt_name'] }}</span>
                <span class="mt-1 block text-xs"><strong>PON:</strong> {{ $diag['onu']['pon_port_name'] ?? $diag['onu']['port'] }}</span>
                <span class="mt-2 flex justify-between text-xs">
                    <span>{{ $diag['onu']['distance'] }}</span>
                    @if (($diag['onu']['mikrotik_name'] ?? '—') !== '—')
                        <span>MK: {{ $diag['onu']['mikrotik_name'] }}</span>
                    @endif
                </span>
            </footer>
        </article>
    </div>
@endif
