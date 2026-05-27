@extends('portal.layout')

@section('title', 'Live chat')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Live chat</h1>
            <p class="portal-page-lead">Open a fast support conversation. The chat runs inside a support ticket so the full message history stays saved.</p>
        </div>
        <a href="{{ route('portal.tickets.index') }}" class="portal-card-button">All tickets</a>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article class="portal-summary-card {{ $openTicket ? 'portal-summary-card--ok' : 'portal-summary-card--info' }}">
            <p class="portal-summary-card__eyebrow">Open chat</p>
            <p class="portal-summary-card__value">{{ $openTicket ? $openTicket->ticket_number : 'None' }}</p>
            <p class="portal-summary-card__meta">{{ $openTicket ? 'Continue your active live chat session from the same ticket thread.' : 'No active live chat session is open right now.' }}</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Recent live chats</p>
            <p class="portal-summary-card__value">{{ ($recentChats ?? collect())->count() }}</p>
            <p class="portal-summary-card__meta">Latest live chat ticket history found in your customer account.</p>
        </article>
    </div>

    <div class="portal-section-grid portal-section-grid--2">
        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Chat session</h2>
                    <p class="portal-surface-card__meta">Start or continue a chat session with your ISP support team.</p>
                </div>
            </div>

            @if ($openTicket)
                <div class="portal-note-banner" style="border-color: rgba(16, 185, 129, 0.24); background: linear-gradient(135deg, rgba(236, 253, 245, 0.94), rgba(255, 255, 255, 0.98)); color: #065f46;">
                    You already have an active live chat session: <strong>{{ $openTicket->ticket_number }}</strong>.
                </div>
                <div class="portal-info-grid">
                    <div class="portal-field-card">
                        <p class="portal-field-card__label">Status</p>
                        <p class="portal-field-card__value">{{ \App\Models\SupportTicket::STATUSES[$openTicket->status] ?? $openTicket->status }}</p>
                    </div>
                    <div class="portal-field-card">
                        <p class="portal-field-card__label">SLA</p>
                        <p class="portal-field-card__value {{ $openTicket->isSlaBreached() ? 'portal-amount-due' : '' }}">{{ $openTicket->slaRemainingLabel() }}</p>
                    </div>
                </div>
                <div class="portal-form-actions">
                    <a href="{{ route('portal.tickets.show', $openTicket) }}" class="portal-btn-primary portal-btn-ticket">Continue chat</a>
                </div>
            @else
                <div class="portal-note-banner">
                    Start a new chat when you need quick back-and-forth support. If agents are unavailable, replies will continue inside the same ticket thread.
                </div>
                <form method="post" action="{{ route('portal.live-chat.start') }}" class="portal-form-grid">
                    @csrf
                    <div class="portal-form-actions">
                        <button type="submit" class="portal-btn-primary portal-btn-ticket">Start chat session</button>
                    </div>
                </form>
            @endif
        </section>

        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">How it works</h2>
                    <p class="portal-surface-card__meta">Live chat stays connected to your support workflow and ticket history.</p>
                </div>
            </div>

            <ul class="portal-note-list">
                <li>One active live chat session is kept open at a time.</li>
                <li>All replies are saved inside the ticket thread for follow-up.</li>
                <li>If your issue needs field work, the support team can escalate it from the same case.</li>
            </ul>
        </section>
    </div>

    @if (($recentChats ?? collect())->isNotEmpty())
        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Recent chat history</h2>
                    <p class="portal-surface-card__meta">Your latest live chat sessions and their current resolution state.</p>
                </div>
            </div>

            <div class="portal-chat-history">
                @foreach ($recentChats as $chat)
                    @php
                        $statusClass = match ($chat->status) {
                            'open', 'in_progress' => 'portal-status-pill--success',
                            'pending' => 'portal-status-pill--warning',
                            'resolved', 'closed' => 'portal-status-pill--muted',
                            default => 'portal-status-pill--muted',
                        };
                    @endphp
                    <div class="portal-chat-history__item">
                        <p class="portal-chat-history__title">{{ $chat->ticket_number }}</p>
                        <p class="portal-chat-history__meta">
                            {{ $chat->created_at?->format('M j, Y g:i A') ?? 'Created recently' }} · {{ $chat->subject }}
                        </p>
                        <div class="portal-alert-card__meta">
                            <span class="portal-status-pill {{ $statusClass }}">{{ \App\Models\SupportTicket::STATUSES[$chat->status] ?? $chat->status }}</span>
                            <a href="{{ route('portal.tickets.show', $chat) }}" class="portal-pro-card__link">Open thread →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
