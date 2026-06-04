@extends('reseller.layout')

@section('title', $ticket->ticket_number)

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">{{ $ticket->subject }}</h1>
        <p class="rsl-subtitle">{{ $ticket->ticket_number }} · {{ $ticket->customer?->name }} · <span class="capitalize">{{ str_replace('_', ' ', $ticket->status) }}</span></p>
        <p class="mt-4 rsl-text whitespace-pre-wrap">{{ $ticket->description }}</p>
    </div>
    @if ($ticket->publicMessagesForCustomer->isNotEmpty())
        <div class="rsl-card mt-6 p-6 space-y-4">
            <h2 class="rsl-heading">Conversation</h2>
            @foreach ($ticket->publicMessagesForCustomer as $msg)
                <div class="rounded-lg border border-[var(--rsl-border)] p-3">
                    <p class="text-xs rsl-text-muted">{{ $msg->created_at?->format('d M Y H:i') }}</p>
                    <p class="mt-1 rsl-text whitespace-pre-wrap">{{ $msg->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
    @if (!in_array($ticket->status, ['closed', 'resolved']))
        <div class="rsl-card mt-6 p-6 max-w-2xl">
            <form method="post" action="{{ route('reseller.tickets.reply', $ticket) }}">
                @csrf
                <label class="block text-xs font-bold uppercase rsl-text-muted">Reply</label>
                <textarea name="body" required rows="4" class="rsl-input mt-1"></textarea>
                <button type="submit" class="rsl-btn-sm mt-3">Send reply</button>
            </form>
        </div>
    @endif
@endsection
