<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            title="GPON / ONU dashboard"
            description="OLT, PON port health, signal levels, fiber cuts and LOS alerts — live optical operations."
            class="isp-hub-hero--violet"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::getUrl() }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-violet-600">
                        <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Monitor</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Optical monitoring hub</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Maps, topology, signal logs</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\DeviceResource::getUrl('index', ['tableFilters' => ['type' => ['value' => 'onu']]]) }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-violet-600">
                        <x-filament::icon icon="heroicon-o-cpu-chip" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Inventory</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">All ONUs</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Provision & signal</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\OutageResource::getUrl('index') }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-violet-600">
                        <x-filament::icon icon="heroicon-o-bolt-slash" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Alerts</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Fiber outages</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Area impact</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-filament-panels::page>
