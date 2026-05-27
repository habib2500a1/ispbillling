@extends('portal.layout')

@section('title', __('portal.support'))

@section('content')
    @php
        $ticketItems = method_exists($tickets, 'getCollection') ? $tickets->getCollection() : collect($tickets);
        $openCount = $ticketItems->filter(fn ($ticket) => method_exists($ticket, 'isOpen') ? $ticket->isOpen() : in_array($ticket->status, ['open', 'pending', 'in_progress'], true))->count();
        $urgentCount = $ticketItems->whereIn('priority', ['high', 'urgent'])->count();
        $resolvedCount = $ticketItems->whereIn('status', ['resolved', 'closed'])->count();
        $slaRiskCount = $ticketItems->filter(fn ($ticket) => $ticket->sla_resolve_due_at && method_exists($ticket, 'isSlaBreached') && $ticket->isSlaBreached())->count();

        $statusClasses = [
            'open' => 'portal-status-pill--success',
            'pending' => 'portal-status-pill--warning',
            'in_progress' => 'portal-status-pill--info',
            'resolved' => 'portal-status-pill--success',
            'closed' => 'portal-status-pill--muted',
        ];

        $priorityClasses = [
            'low' => 'portal-status-pill--muted',
            'medium' => 'portal-status-pill--info',
            'high' => 'portal-status-pill--warning',
            'urgent' => 'portal-status-pill--danger',
        ];
    @endphp

    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">{{ __('portal.support') }}</h1>
            <p class="portal-page-lead">Track issue progress, reply to support, and keep an eye on tickets that need faster attention.</p>
        </div>
        <a href="{{ route('portal.tickets.create') }}" class="portal-btn-primary portal-btn-ticket">
            New ticket
        </a>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article class="portal-summary-card {{ $openCount > 0 ? 'portal-summary-card--warn' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Open tickets</p>
            <p class="portal-summary-card__value">{{ $openCount }}</p>
            <p class="portal-summary-card__meta">Active conversations waiting on either you or support.</p>
        </article>
        <article class="portal-summary-card {{ $urgentCount > 0 ? 'portal-summary-card--due' : 'portal-summary-card--info' }}">
            <p class="portal-summary-card__eyebrow">High priority</p>
            <p class="portal-summary-card__value">{{ $urgentCount }}</p>
            <p class="portal-summary-card__meta">Tickets marked high or urgent on this page.</p>
        </article>
        <article class="portal-summary-card {{ $slaRiskCount > 0 ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">SLA alerts</p>
            <p class="portal-summary-card__value">{{ $slaRiskCount }}</p>
            <p class="portal-summary-card__meta">Tickets that already crossed the response or resolve target.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Resolved here</p>
            <p class="portal-summary-card__value">{{ $resolvedCount }}</p>
            <p class="portal-summary-card__meta">Closed or resolved items in the current listing.</p>
        </article>
    </div>

    @if ($tickets->isEmpty())
        <div class="portal-surface-card">
            <p class="portal-empty-state">No tickets yet. Open one if you need help with billing, speed, ONU signal, or equipment.</p>
            <div class="portal-form-actions">
                <a href="{{ route('portal.tickets.create') }}" class="portal-btn-primary portal-btn-ticket">Open your first ticket</a>
            </div>
        </div>
    @else
        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Ticket queue</h2>
                    <p class="portal-surface-card__meta">Latest updates, status, and priority for your recent support requests.</p>
                </div>
                <div class="portal-inline-list">
                    <span class="portal-inline-chip">Showing {{ $ticketItems->count() }} ticket{{ $ticketItems->count() === 1 ? '' : 's' }}</span>
                    <span class="portal-inline-chip">Page {{ $tickets->currentPage() }} of {{ max(1, $tickets->lastPage()) }}</span>
                </div>
            </div>

            <div class="portal-table-wrap">
                <table class="portal-billing-table portal-table-compact">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Updated</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td class="portal-mono">
                                    <a href="{{ route('portal.tickets.show', $ticket) }}" class="portal-table-title">
                                        {{ $ticket->ticket_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="portal-label-stack">
                                        <span class="font-semibold text-slate-900">{{ $ticket->subject }}</span>
                                        <span class="text-xs text-slate-500">
                                            {{ $ticket->updated_at?->diffForHumans() ?? 'Just now' }}
                                            @if ($ticket->sla_resolve_due_at && method_exists($ticket, 'isOpen') && $ticket->isOpen())
                                                · SLA {{ $ticket->slaRemainingLabel() }}
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="portal-status-pill {{ $statusClasses[$ticket->status] ?? 'portal-status-pill--muted' }}">
                                        {{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}
                                    </span>
                                </td>
                                <td>
                                    <span class="portal-status-pill {{ $priorityClasses[$ticket->priority] ?? 'portal-status-pill--muted' }}">
                                        {{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}
                                    </span>
                                </td>
                                <td class="text-slate-500">{{ $ticket->updated_at?->format('M j, Y g:i A') }}</td>
                                <td>
                                    <a href="{{ route('portal.tickets.show', $ticket) }}" class="portal-card-button">View thread</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $tickets->links() }}
            </div>
        </section>
    @endif
@endsection
