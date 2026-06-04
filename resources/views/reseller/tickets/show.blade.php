@extends('reseller.layout')

@section('title', $ticket->ticket_number)

@section('content')
    @include('reseller.partials.page-header', [
        'title' => $ticket->subject,
        'subtitle' => $ticket->ticket_number.' · '.($ticket->customer?->name ?? '—').' · '.str_replace('_', ' ', $ticket->status),
        'backUrl' => route('reseller.tickets.index'),
        'backLabel' => '← Tickets',
    ])

    <div class="rsl-panel rsl-panel-pad">
        <p class="whitespace-pre-wrap" style="color:var(--rsl-text)">{{ $ticket->description }}</p>
    </div>

    @if ($ticket->publicMessagesForCustomer->isNotEmpty())
        <div class="rsl-panel rsl-panel-pad mt-6 space-y-4">
            <h2 class="rsl-panel-title">Conversation</h2>
            @foreach ($ticket->publicMessagesForCustomer as $msg)
                <div class="rsl-settings-tile" style="cursor:default">
                    <p class="text-xs" style="color:var(--rsl-text-muted)">{{ $msg->created_at?->format('d M Y H:i') }}</p>
                    <p class="mt-1 whitespace-pre-wrap">{{ $msg->body }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if (!in_array($ticket->status, ['closed', 'resolved']))
        <div class="rsl-panel rsl-panel-pad mt-6" style="max-width:36rem">
            <form method="post" action="{{ route('reseller.tickets.reply', $ticket) }}" class="rsl-form-grid">
                @csrf
                <div class="rsl-field">
                    <label class="rsl-field-label" for="reply">Reply</label>
                    <textarea id="reply" name="body" required rows="4" class="rsl-input"></textarea>
                </div>
                <button type="submit" class="rsl-btn">Send reply</button>
            </form>
        </div>
    @endif
@endsection
