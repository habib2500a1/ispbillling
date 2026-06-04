@php
    $diag = $diagnostics ?? null;
@endphp

@if ($diag)
    @if (! empty($diag['network']))
        <div class="isp-net-identity mb-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/50">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Network (আপনার সেট করা নাম)</p>
            <dl class="grid gap-2 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-slate-500">PON port</dt>
                    <dd class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ $diag['network']['pon_port'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">MikroTik</dt>
                    <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ $diag['network']['mikrotik'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">VLAN</dt>
                    <dd class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ $diag['network']['vlan'] }}</dd>
                </div>
            </dl>
        </div>
    @endif
    <div class="isp-net-diag-grid mb-4">
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
                <p class="isp-net-diag-card__footer">
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
                        'font-mono font-semibold',
                        'text-amber-600' => ($diag['onu']['rx_tone'] ?? '') === 'warning' || ($diag['onu']['rx_tone'] ?? '') === 'danger',
                        'text-emerald-600' => ($diag['onu']['rx_tone'] ?? '') === 'success',
                    ])>{{ $diag['onu']['rx_display'] }}</dd>
                </div>
                <div>
                    <dt>Tx Power</dt>
                    <dd class="font-mono">{{ $diag['onu']['tx_display'] }}</dd>
                </div>
                <div>
                    <dt>Temp</dt>
                    <dd @class([
                        'font-mono',
                        'text-amber-600' => ($diag['onu']['temperature_tone'] ?? '') === 'warning',
                        'text-rose-600' => ($diag['onu']['temperature_tone'] ?? '') === 'danger',
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
