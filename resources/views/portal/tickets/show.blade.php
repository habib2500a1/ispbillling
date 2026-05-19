@extends('portal.layout')

@section('title', 'Ticket '.$ticket->ticket_number)

@section('content')
    @php
        $isLiveChat = $ticket->channel === 'live_chat';
    @endphp

    <div class="mb-6">
        <a href="{{ route('portal.tickets.index') }}" class="text-sm font-medium text-amber-800 hover:underline">← All tickets</a>
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="font-mono text-sm font-semibold text-amber-800">{{ $ticket->ticket_number }}</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $ticket->subject }}</h1>
                @if ($isLiveChat)
                    <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> Live chat session
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</span>
                <span class="rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-900">{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</span>
            </div>
        </div>
        @if ($ticket->sla_resolve_due_at && $ticket->isOpen())
            <p class="mt-3 text-sm {{ $ticket->isSlaBreached() ? 'font-medium text-red-600' : 'text-slate-500' }}">
                SLA: {{ $ticket->slaRemainingLabel() }}
            </p>
        @endif
    </div>

    <div class="mb-6 space-y-3">
        <div class="{{ $isLiveChat ? 'ml-auto max-w-[85%]' : '' }}">
            <div class="rounded-2xl {{ $isLiveChat ? 'rounded-br-md bg-amber-600 text-white' : 'border border-slate-200 bg-white' }} px-4 py-3 shadow-sm">
                <p class="text-xs {{ $isLiveChat ? 'text-amber-100' : 'text-slate-500' }}">You · {{ $ticket->created_at?->format('M j, g:i A') }}</p>
                <p class="mt-1 whitespace-pre-wrap {{ $isLiveChat ? 'text-white' : 'text-slate-800' }}">{{ $ticket->description }}</p>
            </div>
        </div>

        @foreach ($ticket->publicMessagesForCustomer as $msg)
            @php $fromStaff = $msg->user_id !== null; @endphp
            <div class="{{ $fromStaff ? '' : 'ml-auto max-w-[85%]' }}">
                <div class="rounded-2xl px-4 py-3 shadow-sm {{ $fromStaff ? 'rounded-bl-md border border-slate-200 bg-white' : ($isLiveChat ? 'rounded-br-md bg-amber-600 text-white' : 'border border-amber-100 bg-amber-50') }}">
                    <p class="text-xs {{ $fromStaff ? 'text-slate-500' : ($isLiveChat ? 'text-amber-100' : 'text-amber-800') }}">
                        {{ $fromStaff ? 'Support team' : 'You' }} · {{ $msg->created_at?->format('M j, g:i A') }}
                    </p>
                    <p class="mt-1 whitespace-pre-wrap {{ $fromStaff ? 'text-slate-800' : ($isLiveChat ? 'text-white' : 'text-slate-800') }}">{{ $msg->body }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($ticket->uploads->isNotEmpty())
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 text-sm">
            <p class="mb-2 font-semibold text-slate-700">Attachments</p>
            <ul class="space-y-1">
                @foreach ($ticket->uploads as $up)
                    <li>
                        <a href="{{ $up->publicUrl() }}" target="_blank" rel="noopener" class="text-amber-800 hover:underline">{{ $up->original_name ?: basename($up->path) }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($ticket->isOpen())
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">{{ $isLiveChat ? 'Send a message' : 'Add a reply' }}</h2>
            <form method="post" action="{{ route('portal.tickets.reply', $ticket) }}" class="space-y-3">
                @csrf
                <textarea name="body" rows="{{ $isLiveChat ? 2 : 4 }}" required maxlength="5000"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="{{ $isLiveChat ? 'Type your message…' : 'Additional details…' }}">{{ old('body') }}</textarea>
                <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">
                    {{ $isLiveChat ? 'Send' : 'Send reply' }}
                </button>
            </form>
        </div>
    @endif

    @if (in_array($ticket->status, ['resolved', 'closed'], true))
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Rate this support</h2>
            @if ($ticket->customer_rating !== null)
                <p class="text-slate-700">You rated this ticket <strong>{{ $ticket->customer_rating }}</strong> / 5.</p>
                @if ($ticket->customer_rating_comment)
                    <p class="mt-2 text-sm text-slate-600">{{ $ticket->customer_rating_comment }}</p>
                @endif
            @else
                <form method="post" action="{{ route('portal.tickets.rate', $ticket) }}" class="space-y-3">
                    @csrf
                    <select name="customer_rating" required class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ $i }} — {{ ['','Poor','Fair','Good','Very good','Excellent'][$i] }}</option>
                        @endfor
                    </select>
                    <textarea name="customer_rating_comment" rows="2" maxlength="2000" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Optional comment"></textarea>
                    <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Submit rating</button>
                </form>
            @endif
        </div>
    @endif
@endsection
