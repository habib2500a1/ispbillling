@extends('portal.layout')

@section('title', 'Support tickets')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Support tickets</h1>
        <a href="{{ route('portal.tickets.create') }}" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
            New ticket
        </a>
    </div>

    @if ($tickets->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-slate-600">No tickets yet. Open one if you need help.</p>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($tickets as $ticket)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs">
                                <a href="{{ route('portal.tickets.show', $ticket) }}" class="font-semibold text-amber-800 hover:underline">
                                    {{ $ticket->ticket_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-800">{{ $ticket->subject }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $ticket->updated_at?->format('M j, Y g:i A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    @endif
@endsection
