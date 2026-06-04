@extends('reseller.layout')

@section('title', 'Tickets')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Support tickets',
        'subtitle' => 'Customer-related issues.',
        'actionUrl' => route('reseller.tickets.create'),
        'actionLabel' => '+ New ticket',
    ])

    <div class="rsl-panel">
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Ticket</th>
                        <th class="px-4 py-3">Subscribers</th>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Priority</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('reseller.tickets.show', $ticket) }}" class="rsl-link-action font-mono">{{ $ticket->ticket_number }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $ticket->customer?->customer_code }}</td>
                            <td class="px-4 py-3">{{ Str::limit($ticket->subject, 40) }}</td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $ticket->status) }}</td>
                            <td class="px-4 py-3">{{ $ticket->priority }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">No tickets yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="p-4">{{ $tickets->links() }}</div>
        @endif
    </div>
@endsection
