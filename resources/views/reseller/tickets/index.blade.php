@extends('reseller.layout')

@section('title', 'Support tickets')

@section('content')
    <div class="rsl-card p-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="rsl-title">Support tickets</h1>
        <a href="{{ route('reseller.tickets.create') }}" class="rsl-btn-sm">New ticket</a>
    </div>
    <div class="rsl-card mt-6 overflow-hidden">
        <table class="rsl-table w-full text-sm">
            <thead><tr><th class="px-4 py-3">Ticket</th><th class="px-4 py-3">Subscriber</th><th class="px-4 py-3">Subject</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Priority</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('reseller.tickets.show', $ticket) }}" class="rsl-link">{{ $ticket->ticket_number }}</a></td>
                        <td class="px-4 py-3 rsl-text">{{ $ticket->customer?->customer_code }}</td>
                        <td class="px-4 py-3 rsl-text">{{ Str::limit($ticket->subject, 40) }}</td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $ticket->status) }}</td>
                        <td class="px-4 py-3 capitalize">{{ $ticket->priority }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center rsl-text-muted">No tickets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $tickets->links() }}</div>
    </div>
@endsection
