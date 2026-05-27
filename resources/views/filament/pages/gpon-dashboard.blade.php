<x-filament-panels::page>
    @php
        $links = [
            ['eyebrow' => 'Monitor', 'label' => 'Optical monitoring hub', 'hint' => 'Maps, topology, signal logs', 'url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(), 'icon' => 'heroicon-o-chart-bar'],
            ['eyebrow' => 'Inventory', 'label' => 'All ONUs', 'hint' => 'Provision & signal', 'url' => \App\Filament\Resources\DeviceResource::getUrl('index', ['tableFilters' => ['type' => ['value' => 'onu']]]), 'icon' => 'heroicon-o-cpu-chip'],
            ['eyebrow' => 'Alerts', 'label' => 'Fiber outages', 'hint' => 'Area impact', 'url' => \App\Filament\Resources\OutageResource::getUrl('index'), 'icon' => 'heroicon-o-bolt-slash'],
        ];
    @endphp

    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            eyebrow="Optical operations"
            title="GPON / ONU dashboard"
            description="OLT, PON port health, signal levels, fiber cuts and LOS alerts — live optical operations."
            class="isp-hub-hero--violet"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Optical actions</h2>
                    <p class="isp-hub-section__desc">Open live signal tools, ONU inventory, and outage response workflows with one click.</p>
                </div>
                <span class="isp-hub-section__meta">Fiber live</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--3">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon text-violet-600">
                                <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="isp-module-card__eyebrow">{{ $link['eyebrow'] }}</p>
                                <p class="isp-module-card__title">{{ $link['label'] }}</p>
                                <p class="isp-module-card__desc">{{ $link['hint'] }}</p>
                            </div>
                            <span class="isp-module-card__arrow" aria-hidden="true">→</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
