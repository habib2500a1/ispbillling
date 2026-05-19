@php
    $links = [
        ['eyebrow' => 'Monitor', 'label' => 'Online clients', 'hint' => 'Live PPPoE table', 'url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl(), 'icon' => 'heroicon-o-signal'],
        ['eyebrow' => 'GPON', 'label' => 'Optical hub', 'hint' => 'ONU signal & fiber', 'url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(), 'icon' => 'heroicon-o-cpu-chip'],
        ['eyebrow' => 'Routers', 'label' => 'MikroTik servers', 'hint' => 'API status', 'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('index'), 'icon' => 'heroicon-o-server'],
        ['eyebrow' => 'Outages', 'label' => 'Outage board', 'hint' => 'Area incidents', 'url' => \App\Filament\Resources\OutageResource::getUrl('index'), 'icon' => 'heroicon-o-megaphone'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            title="NOC dashboard"
            description="Live network health — sessions, bandwidth, routers, OLT/ONU and fiber alerts. Auto-refreshes every 30 seconds."
            class="isp-hub-hero--cyan"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="isp-module-card group">
                    <div class="flex items-start gap-3">
                        <span class="isp-module-icon text-cyan-600">
                            <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">{{ $link['eyebrow'] }}</p>
                            <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $link['label'] }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $link['hint'] }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columns="['default' => 1, 'lg' => 2]"
        />
    </div>
</x-filament-panels::page>
