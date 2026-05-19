<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            title="MikroTik dashboard"
            description="PPPoE sessions, router API status, traffic and queue health."
            class="isp-hub-hero--slate"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Resources\MikrotikServerResource::getUrl('index') }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon">
                        <x-filament::icon icon="heroicon-o-server" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Routers</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">MikroTik servers</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Credentials & sync</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon">
                        <x-filament::icon icon="heroicon-o-signal" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Sessions</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Online clients</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Live PPPoE</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Pages\BandwidthMonitor::getUrl() }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon">
                        <x-filament::icon icon="heroicon-o-chart-bar-square" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Traffic</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Bandwidth monitor</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Graphs & abuse</p>
                    </div>
                </div>
            </a>
        </div>

        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columns="['default' => 1, 'lg' => 2]"
        />
    </div>
</x-filament-panels::page>
