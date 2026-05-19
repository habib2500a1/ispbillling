@php $stats = $this->getStats(); @endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero title="Technician dashboard" description="Your assigned tickets, SLA deadlines, and field jobs." class="isp-hub-hero--amber" />

        <div class="isp-hub-stat-grid">
            <div class="isp-hub-stat"><span class="isp-hub-stat-label">Open assigned</span><strong>{{ $stats['assigned_open'] }}</strong></div>
            <div class="isp-hub-stat"><span class="isp-hub-stat-label">Due today</span><strong>{{ $stats['due_today'] }}</strong></div>
            <div class="isp-hub-stat"><span class="isp-hub-stat-label">Resolved (month)</span><strong>{{ $stats['resolved_month'] }}</strong></div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ \App\Filament\Pages\TaskKanbanBoard::getUrl() }}" class="isp-quick-pill">Kanban board</a>
            <a href="{{ \App\Filament\Resources\SupportTicketResource::getUrl('index') }}" class="isp-quick-pill">All tickets</a>
        </div>

        <div class="isp-module-card overflow-hidden">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 dark:bg-gray-900"><tr><th class="p-3">Ticket</th><th>Priority</th><th>Status</th><th>SLA</th></tr></thead>
                <tbody>
                    @forelse ($stats['tickets'] as $t)
                        <tr class="border-t dark:border-gray-800">
                            <td class="p-3"><a href="{{ \App\Filament\Resources\SupportTicketResource::getUrl('view', ['record' => $t]) }}" class="font-semibold text-teal-600">#{{ $t->ticket_number }}</a> {{ $t->subject }}</td>
                            <td>{{ $t->priority }}</td>
                            <td>{{ $t->status }}</td>
                            <td>{{ $t->sla_resolve_due_at?->format('d M H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center text-gray-500">No open assignments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
