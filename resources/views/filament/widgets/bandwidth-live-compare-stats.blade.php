@php
    $live = $this->getLiveSnapshot();
    $ifaces = \App\Services\Bandwidth\BandwidthCollectionService::latestWanInterfaceSnapshots(
        \App\Support\TenantResolver::requiredTenantId()
    );
@endphp

<x-filament-widgets::widget>
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="isp-bw-compare-card isp-bw-compare-card--wan">
            <p class="isp-bw-compare-card__title">MikroTik WAN port</p>
            <p class="isp-bw-compare-card__hint">
                @if ($ifaces !== [])
                    {{ collect($ifaces)->map(fn ($i) => $i['server'].' · '.$i['interface'])->join(', ') }}
                @else
                    Router uplink (ether/WAN) — open Bandwidth monitor &amp; Sync
                @endif
            </p>
            <div class="isp-bw-compare-card__row">
                <span>↓ Download</span>
                <strong>{{ $live['wan_down_mbps'] }} Mbps/s</strong>
            </div>
            <div class="isp-bw-compare-card__row">
                <span>↑ Upload</span>
                <strong>{{ $live['wan_up_mbps'] }} Mbps/s</strong>
            </div>
        </div>
        <div class="isp-bw-compare-card isp-bw-compare-card--users">
            <p class="isp-bw-compare-card__title">All subscribers (PPPoE)</p>
            <p class="isp-bw-compare-card__hint">Sum of every online user session</p>
            <div class="isp-bw-compare-card__row">
                <span>↓ Download</span>
                <strong>{{ $live['users_down_mbps'] }} Mbps/s</strong>
            </div>
            <div class="isp-bw-compare-card__row">
                <span>↑ Upload</span>
                <strong>{{ $live['users_up_mbps'] }} Mbps/s</strong>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
