@extends('portal.layout')

@section('title', __('portal.support'))

@section('content')
    <div class="portal-page-head">
        <h1 class="portal-page-title">{{ __('portal.support') }}</h1>
        <a href="{{ route('portal.tickets.create') }}" class="portal-btn-primary portal-btn-ticket">
            New ticket
        </a>
    </div>

    @if ($tickets->isEmpty())
        <p class="portal-empty-state">No tickets yet. Open one if you need help.</p>
    @else
        <div class="portal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                        <tr>
                            <td style="font-family: ui-monospace, monospace; font-size: 0.75rem;">
                                <a href="{{ route('portal.tickets.show', $ticket) }}" class="portal-link">
                                    {{ $ticket->ticket_number }}
                                </a>
                            </td>
                            <td>{{ $ticket->subject }}</td>
                            <td>
                                <span class="portal-status-pill">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</span>
                            </td>
                            <td>{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</td>
                            <td style="color: var(--portal-text-muted);">{{ $ticket->updated_at?->format('M j, Y g:i A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem;">
            {{ $tickets->links() }}
        </div>
    @endif
@endsection
