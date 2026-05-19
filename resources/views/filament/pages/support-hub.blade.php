@php
    $stats = $this->getStats();
    $slaRows = $this->getSlaByDepartment();
    $ticketsUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            title="Support center"
            description="Portal tickets, call-center complaints, SLA tracking, technician assignment, and live chat — all in one place."
            class="isp-hub-hero--amber"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ $ticketsUrl }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-amber-600">
                        <x-filament::icon icon="heroicon-o-queue-list" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Queue</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">All tickets</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Filters · bulk assign · SLA</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\SupportTicketResource::getUrl('create') }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-amber-600">
                        <x-filament::icon icon="heroicon-o-plus-circle" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">New</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Open ticket</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Phone or walk-in</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Pages\TaskKanbanBoard::getUrl() }}" class="isp-module-card group border-amber-200/80 dark:border-amber-900/40">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-amber-600">
                        <x-filament::icon icon="heroicon-o-view-columns" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-amber-700 dark:text-amber-300">Tasks</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Kanban board</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Staff work items</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\OutageResource::getUrl('index') }}" class="isp-module-card group">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-amber-600">
                        <x-filament::icon icon="heroicon-o-megaphone" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">Network</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Outages</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Area maintenance notices</p>
                    </div>
                </div>
            </a>
        </div>

        @if (count($slaRows) > 0)
            <section class="isp-ops-section">
                <h3 class="isp-ops-section-title">SLA by department</h3>
                <div class="isp-hub-sla-table-wrap overflow-x-auto">
                    <table class="isp-hub-sla-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Department</th>
                                <th class="text-right">Open</th>
                                <th class="text-right">Overdue</th>
                                <th class="text-right">Unassigned</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($slaRows as $row)
                                <tr>
                                    <td class="font-medium text-gray-900 dark:text-white">{{ $row['label'] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['open'] }}</td>
                                    <td class="text-right tabular-nums {{ $row['breached'] > 0 ? 'isp-hub-sla-breached' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $row['breached'] }}
                                    </td>
                                    <td class="text-right tabular-nums">{{ $row['unassigned'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <x-isp.hub-module-grid group="Support" :skip-sections="['Hub']" />

        <details class="isp-ops-details">
            <summary class="isp-ops-details-summary">Webhook &amp; scheduler</summary>
            <div class="mt-3 rounded-xl border border-amber-200/80 bg-amber-50/80 p-4 text-sm dark:border-amber-900/50 dark:bg-amber-950/25">
                <p class="font-semibold text-amber-950 dark:text-amber-100">External ticket ingest</p>
                <p class="mt-2 font-mono text-xs text-amber-900 dark:text-amber-200">
                    POST {{ url('/api/webhooks/support-ticket-ingest') }}<br>
                    Header: X-ISP-Webhook-Secret
                </p>
                <p class="mt-2 text-xs text-amber-800/90 dark:text-amber-300">
                    SLA checked every 30 minutes via <span class="font-mono">isp:support-check-sla</span>.
                    @if ($stats['breached'] > 0)
                        <strong class="text-rose-700 dark:text-rose-300">{{ $stats['breached'] }} ticket(s) need attention now.</strong>
                    @endif
                </p>
            </div>
        </details>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
