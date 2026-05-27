@php
    $stats = $this->getStats();
    $statCards = [
        ['label' => 'Open assigned', 'value' => (string) $stats['assigned_open'], 'hint' => 'Current active jobs', 'class' => 'isp-hub-stat--amber'],
        ['label' => 'Due today', 'value' => (string) $stats['due_today'], 'hint' => 'SLA deadline today', 'class' => $stats['due_today'] > 0 ? 'isp-hub-stat--danger' : 'isp-hub-stat--sky', 'valueClass' => $stats['due_today'] > 0 ? 'isp-hub-stat-value--danger' : ''],
        ['label' => 'Resolved (month)', 'value' => (string) $stats['resolved_month'], 'hint' => 'Closed by assigned tech', 'class' => 'isp-hub-stat--teal'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            eyebrow="Field operations"
            title="Technician dashboard"
            description="Your assigned tickets, SLA deadlines, and field jobs with quick access to task execution tools."
            class="isp-hub-hero--amber"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ count($stats['tickets']) }} assignments loaded</span>
                    <span class="isp-hub-section__meta">{{ $stats['due_today'] }} due today</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <div class="flex flex-wrap gap-2">
            <a href="{{ \App\Filament\Pages\TaskKanbanBoard::getUrl() }}" class="isp-quick-pill">Kanban board</a>
            <a href="{{ \App\Filament\Resources\SupportTicketResource::getUrl('index') }}" class="isp-quick-pill">All tickets</a>
        </div>

        <section class="isp-ops-panel overflow-hidden">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">Assigned tickets</h3>
                    <p class="isp-ops-panel__desc">Live assignment queue with priority, status, and resolve deadline visibility.</p>
                </div>
                <span class="isp-ops-pill {{ $stats['due_today'] > 0 ? 'isp-ops-pill--warn' : 'isp-ops-pill--ok' }}">{{ $stats['due_today'] > 0 ? 'Due today' : 'On track' }}</span>
            </div>
            <div class="overflow-x-auto px-0 pb-1">
                <table class="isp-ops-table text-sm text-left">
                    <thead>
                        <tr><th>Ticket</th><th>Priority</th><th>Status</th><th>SLA</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($stats['tickets'] as $t)
                        <tr>
                            <td>
                                <a href="{{ \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $t]) }}" class="isp-ops-table__link">#{{ $t->ticket_number }}</a>
                                <span class="isp-ops-table__sub">{{ $t->subject }}</span>
                            </td>
                            <td><span class="isp-ops-pill {{ in_array($t->priority, ['critical', 'high'], true) ? 'isp-ops-pill--danger' : ($t->priority === 'medium' ? 'isp-ops-pill--warn' : 'isp-ops-pill--ok') }}">{{ $t->priority }}</span></td>
                            <td>{{ $t->status }}</td>
                            <td>{{ $t->sla_resolve_due_at?->format('d M H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center text-gray-500">No open assignments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
