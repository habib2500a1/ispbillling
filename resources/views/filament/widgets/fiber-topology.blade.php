@php
    $s = $summary;
@endphp

<x-filament-widgets::widget>
    <div class="isp-topology-widget">
        <div class="isp-topology-widget__head">
            <div>
                <h3 class="font-bold text-gray-900 dark:text-white">Fiber topology</h3>
                <p class="text-xs text-gray-500">OLT → splitter → ONU map</p>
            </div>
            <a href="{{ \App\Filament\Pages\NetworkTopology::getUrl() }}" class="text-xs font-semibold text-teal-600 hover:underline">Open full map →</a>
        </div>
        <div class="isp-topology-widget__chain">
            <span class="isp-topo-node isp-topo-node--olt">OLT <strong>{{ $s['olts'] ?? $oltCount }}</strong></span>
            <span class="isp-topo-arrow">→</span>
            <span class="isp-topo-node isp-topo-node--split">Splitters</span>
            <span class="isp-topo-arrow">→</span>
            <span class="isp-topo-node isp-topo-node--onu">ONU <strong>{{ $s['onus'] ?? 0 }}</strong></span>
            <span class="isp-topo-arrow">·</span>
            <span class="isp-topo-node">MikroTik <strong>{{ $mikrotikCount }}</strong></span>
            <span class="isp-topo-node isp-topo-node--online">Online <strong>{{ $s['onus_online'] ?? 0 }}</strong></span>
        </div>
    </div>
</x-filament-widgets::widget>
