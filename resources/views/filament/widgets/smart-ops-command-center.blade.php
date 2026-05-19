@php
    $b = $billing ?? [];
    $n = $noc ?? [];
    $a = $automation ?? [];
    $s = $sms ?? [];
@endphp

<x-filament-widgets::widget>
    <section class="isp-ops-center" wire:poll.60s>
        <header class="isp-ops-center__head">
            <div>
                <h2 class="isp-ops-center__title">Smart ops command center</h2>
                <p class="isp-ops-center__sub">Billing · Network · Automation · SMS — refreshes every minute</p>
            </div>
            <div class="isp-ops-center__actions">
                <a href="{{ $links['collection'] }}" class="isp-ops-center__btn isp-ops-center__btn--primary">Collection desk</a>
                <a href="{{ $links['automation'] }}" class="isp-ops-center__btn isp-ops-center__btn--ghost">Automatic process</a>
            </div>
        </header>

        <div class="isp-unified-metrics isp-ops-center__metrics">
            <div class="isp-unified-metric isp-ops-metric isp-ops-metric--amber">
                <span>Outstanding</span>
                <strong>{{ number_format((float) ($b['outstanding'] ?? 0), 0) }} BDT</strong>
                <p class="isp-ops-metric__meta">
                    Due tomorrow: <strong>{{ $b['due_tomorrow'] ?? 0 }}</strong>
                    · Over credit: <strong class="{{ ($b['over_credit_limit'] ?? 0) > 0 ? 'is-danger' : '' }}">{{ $b['over_credit_limit'] ?? 0 }}</strong>
                </p>
            </div>
            <div class="isp-unified-metric isp-ops-metric isp-ops-metric--emerald">
                <span>Collected today</span>
                <strong class="isp-unified-metric--ok">{{ number_format((float) ($collected_today ?? 0), 0) }} BDT</strong>
                <p class="isp-ops-metric__meta">
                    AR 60+ days: {{ number_format((float) ($b['aging']['60_plus']['amount'] ?? 0), 0) }} BDT
                </p>
            </div>
            <div class="isp-unified-metric isp-ops-metric isp-ops-metric--sky">
                <span>Network</span>
                <strong>{{ number_format($n['online_now'] ?? 0) }} online</strong>
                <p class="isp-ops-metric__meta">
                    MikroTik {{ $n['mikrotik_online'] ?? 0 }}/{{ $n['mikrotik_total'] ?? 0 }}
                    · {{ $n['bandwidth_mbps'] ?? 0 }} Mbps
                </p>
            </div>
            <div class="isp-unified-metric isp-ops-metric isp-ops-metric--violet">
                <span>Automation</span>
                <strong class="{{ ($a['failed_24h'] ?? 0) > 0 ? 'isp-unified-metric--danger' : '' }}">
                    {{ $a['failed_24h'] ?? 0 }} failed (24h)
                </strong>
                <p class="isp-ops-metric__meta">
                    {{ $a['enabled'] ?? 0 }} enabled · {{ $a['due_1h'] ?? 0 }} due in 1h
                </p>
            </div>
        </div>

        @if ($sms_provider === 'khudebarta')
            <p class="isp-ops-center__dlr">
                <strong>KhudeBarta DLR URL:</strong>
                <code>{{ $khudebarta_dlr_url }}</code>
                — paste in portal → Delivery API (Query).
                SMS sent today: {{ $s['sent_today'] ?? 0 }} · DLR failed (24h): {{ $s['failed_dlr_24h'] ?? 0 }}
            </p>
        @endif
    </section>
</x-filament-widgets::widget>
