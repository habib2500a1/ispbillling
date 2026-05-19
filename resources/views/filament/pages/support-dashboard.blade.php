<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            title="Support dashboard"
            description="Tickets, SLA breaches, escalations and technician workload — live for support & call center."
            class="isp-hub-hero--amber"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Pages\SupportHub::getUrl() }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-amber-600">
                        <x-filament::icon icon="heroicon-o-lifebuoy" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Hub</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Support center</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Full tools & SLA table</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\SupportTicketResource::getUrl('index') }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-amber-600">
                        <x-filament::icon icon="heroicon-o-queue-list" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Queue</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">All tickets</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Filter & assign</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Pages\TaskKanbanBoard::getUrl() }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-amber-600">
                        <x-filament::icon icon="heroicon-o-view-columns" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Tasks</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Kanban board</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Technician jobs</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-filament-panels::page>
