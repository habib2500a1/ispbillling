@php $wan = $this->getWanLive(); @endphp

<x-filament-widgets::widget>
    <div class="isp-wan-live-hero">
        <div class="isp-wan-live-hero__head">
            <div>
                <h2 class="isp-wan-live-hero__title">WAN port — live now</h2>
                <p class="isp-wan-live-hero__sub">Total uplink throughput (Mbps per second)</p>
            </div>
            <span class="isp-wan-live-hero__pulse">
                <span class="isp-kpi-wall__dot"></span>
                Live
            </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="isp-wan-live-stat isp-wan-live-stat--down">
                <span class="isp-wan-live-stat__label">↓ Download</span>
                <span class="isp-wan-live-stat__value">{{ $wan['down_mbps'] }}</span>
                <span class="isp-wan-live-stat__unit">Mbps/s</span>
            </div>
            <div class="isp-wan-live-stat isp-wan-live-stat--up">
                <span class="isp-wan-live-stat__label">↑ Upload</span>
                <span class="isp-wan-live-stat__value">{{ $wan['up_mbps'] }}</span>
                <span class="isp-wan-live-stat__unit">Mbps/s</span>
            </div>
        </div>

        @if (count($wan['interfaces']) > 0)
            <div class="isp-wan-live-ifaces">
                <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-2">Interfaces</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($wan['interfaces'] as $if)
                        <span class="isp-wan-live-iface-chip">
                            {{ $if['server'] }} · <strong>{{ $if['interface'] }}</strong>
                            ↓{{ $if['down_mbps'] }} ↑{{ $if['up_mbps'] }} Mbps/s
                        </span>
                    @endforeach
                </div>
            </div>
        @elseif (! $wan['has_data'])
            <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                No WAN rate yet — click <strong>Sync now</strong> twice (~{{ config('bandwidth.poll_interval_minutes', 2) }} min apart), or set
                <span class="font-mono text-xs">BANDWIDTH_WAN_INTERFACES=ether1</span> in .env.
            </p>
        @endif
    </div>
</x-filament-widgets::widget>
