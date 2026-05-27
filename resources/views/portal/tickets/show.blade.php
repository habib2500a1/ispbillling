@extends('portal.layout')

@section('title', 'Ticket '.$ticket->ticket_number)

@section('content')
    @php
        $isLiveChat = $ticket->channel === 'live_chat';
        $statusClass = match ($ticket->status) {
            'open', 'resolved' => 'portal-status-pill--success',
            'pending' => 'portal-status-pill--warning',
            'in_progress' => 'portal-status-pill--info',
            'closed' => 'portal-status-pill--muted',
            default => 'portal-status-pill--muted',
        };
        $priorityClass = match ($ticket->priority) {
            'urgent' => 'portal-status-pill--danger',
            'high' => 'portal-status-pill--warning',
            'medium' => 'portal-status-pill--info',
            'low' => 'portal-status-pill--muted',
            default => 'portal-status-pill--muted',
        };
        $channelLabel = $isLiveChat ? 'Live chat' : 'Support ticket';
    @endphp

    <div class="portal-page-head">
        <div>
            <a href="{{ route('portal.tickets.index') }}" class="portal-link">← All tickets</a>
            <h1 class="portal-page-title mt-3">{{ $ticket->subject }}</h1>
            <p class="portal-page-lead">Ticket {{ $ticket->ticket_number }} · Created {{ $ticket->created_at?->format('M j, Y g:i A') }}</p>
        </div>
        <div class="portal-status-group">
            <span class="portal-status-pill {{ $statusClass }}">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</span>
            <span class="portal-status-pill {{ $priorityClass }}">{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</span>
        </div>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article class="portal-summary-card {{ $ticket->isOpen() ? 'portal-summary-card--warn' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Conversation type</p>
            <p class="portal-summary-card__value">{{ $channelLabel }}</p>
            <p class="portal-summary-card__meta">{{ $isLiveChat ? 'Fast back-and-forth replies in one thread.' : 'Structured support case with full history.' }}</p>
        </article>
        <article class="portal-summary-card {{ $ticket->isOpen() ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Current state</p>
            <p class="portal-summary-card__value">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</p>
            <p class="portal-summary-card__meta">
                @if ($ticket->sla_resolve_due_at && $ticket->isOpen())
                    SLA {{ $ticket->slaRemainingLabel() }}
                @else
                    Last updated {{ $ticket->updated_at?->diffForHumans() ?? 'recently' }}
                @endif
            </p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Priority</p>
            <p class="portal-summary-card__value">{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</p>
            <p class="portal-summary-card__meta">Use reply updates here if the situation changes or gets worse.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Messages</p>
            <p class="portal-summary-card__value">{{ $ticket->publicMessagesForCustomer->count() + 1 }}</p>
            <p class="portal-summary-card__meta">Includes your opening message and all public support replies.</p>
        </article>
    </div>

    <div class="portal-section-grid portal-section-grid--2">
        <div>
            <section class="portal-surface-card">
                <div class="portal-section-head">
                    <div class="portal-label-stack">
                        <h2 class="portal-surface-card__title">Conversation timeline</h2>
                        <p class="portal-surface-card__meta">Every public reply stays in this thread so you can review the full support history.</p>
                    </div>
                    @if ($isLiveChat)
                        <span class="portal-inline-chip">Live chat session</span>
                    @endif
                </div>

                <div class="portal-ticket-thread">
                    <article class="portal-ticket-bubble portal-ticket-bubble--self">
                        <div class="portal-ticket-bubble__meta">
                            <span class="portal-ticket-bubble__author">You</span>
                            <span>{{ $ticket->created_at?->format('M j, g:i A') }}</span>
                        </div>
                        <p class="portal-ticket-bubble__body">{{ $ticket->description }}</p>
                    </article>

                    @foreach ($ticket->publicMessagesForCustomer as $msg)
                        @php $fromStaff = $msg->user_id !== null; @endphp
                        <article class="portal-ticket-bubble {{ $fromStaff ? 'portal-ticket-bubble--staff' : 'portal-ticket-bubble--self' }}">
                            <div class="portal-ticket-bubble__meta">
                                <span class="portal-ticket-bubble__author">{{ $fromStaff ? 'Support team' : 'You' }}</span>
                                <span>{{ $msg->created_at?->format('M j, g:i A') }}</span>
                            </div>
                            <p class="portal-ticket-bubble__body">{{ $msg->body }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            @if ($ticket->isOpen())
                <section class="portal-surface-card">
                    <div class="portal-section-head">
                        <div class="portal-label-stack">
                            <h2 class="portal-surface-card__title">{{ $isLiveChat ? 'Send a message' : 'Add a reply' }}</h2>
                            <p class="portal-surface-card__meta">Reply here with any new information, screenshots, or troubleshooting update.</p>
                        </div>
                    </div>

                    <form method="post" action="{{ route('portal.tickets.reply', $ticket) }}" class="portal-form-grid">
                        @csrf
                        <div>
                            <label for="body">{{ $isLiveChat ? 'Message' : 'Reply' }}</label>
                            <textarea name="body" id="body" rows="{{ $isLiveChat ? 3 : 5 }}" required maxlength="5000" placeholder="{{ $isLiveChat ? 'Type your message here' : 'Add any extra details that will help support' }}">{{ old('body') }}</textarea>
                        </div>
                        <div class="portal-form-actions">
                            <button type="submit" class="portal-btn-primary portal-btn-ticket">{{ $isLiveChat ? 'Send message' : 'Send reply' }}</button>
                        </div>
                    </form>
                </section>
            @endif

            @if (in_array($ticket->status, ['resolved', 'closed'], true))
                <section class="portal-surface-card">
                    <div class="portal-section-head">
                        <div class="portal-label-stack">
                            <h2 class="portal-surface-card__title">Rate this support</h2>
                            <p class="portal-surface-card__meta">Your feedback helps improve future response quality and resolution time.</p>
                        </div>
                    </div>

                    @if ($ticket->customer_rating !== null)
                        <p class="text-slate-700">You rated this ticket <strong>{{ $ticket->customer_rating }}</strong> / 5.</p>
                        @if ($ticket->customer_rating_comment)
                            <p class="portal-surface-card__meta">{{ $ticket->customer_rating_comment }}</p>
                        @endif
                    @else
                        <form method="post" action="{{ route('portal.tickets.rate', $ticket) }}" class="portal-form-grid">
                            @csrf
                            <div>
                                <label for="customer_rating">Support rating</label>
                                <select name="customer_rating" id="customer_rating" required>
                                    @for ($i = 5; $i >= 1; $i--)
                                        <option value="{{ $i }}">{{ $i }} - {{ ['','Poor','Fair','Good','Very good','Excellent'][$i] }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div>
                                <label for="customer_rating_comment">Comment</label>
                                <textarea name="customer_rating_comment" id="customer_rating_comment" rows="3" maxlength="2000" placeholder="Optional feedback"></textarea>
                            </div>
                            <div class="portal-form-actions">
                                <button type="submit" class="portal-card-button portal-card-button--primary">Submit rating</button>
                            </div>
                        </form>
                    @endif
                </section>
            @endif
        </div>

        <div>
            <section class="portal-surface-card">
                <div class="portal-section-head">
                    <div class="portal-label-stack">
                        <h2 class="portal-surface-card__title">Ticket details</h2>
                        <p class="portal-surface-card__meta">Quick metadata for support routing and tracking.</p>
                    </div>
                </div>

                <dl class="portal-detail-list">
                    <div class="portal-detail-list__item">
                        <dt>Ticket ID</dt>
                        <dd class="portal-mono">{{ $ticket->ticket_number }}</dd>
                    </div>
                    <div class="portal-detail-list__item">
                        <dt>Channel</dt>
                        <dd>{{ $channelLabel }}</dd>
                    </div>
                    <div class="portal-detail-list__item">
                        <dt>Department</dt>
                        <dd>{{ $ticket->department ? \Illuminate\Support\Str::headline((string) $ticket->department) : 'General support' }}</dd>
                    </div>
                    <div class="portal-detail-list__item">
                        <dt>Issue type</dt>
                        <dd>{{ $ticket->issue_type ? \Illuminate\Support\Str::headline((string) $ticket->issue_type) : 'Not specified' }}</dd>
                    </div>
                    <div class="portal-detail-list__item">
                        <dt>Updated</dt>
                        <dd>{{ $ticket->updated_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($ticket->sla_resolve_due_at && $ticket->isOpen())
                    <div class="portal-note-banner {{ $ticket->isSlaBreached() ? 'portal-note-banner--danger' : '' }}">
                        SLA target: {{ $ticket->slaRemainingLabel() }}
                    </div>
                @endif
            </section>

            @if ($ticket->uploads->isNotEmpty())
                <section class="portal-surface-card">
                    <div class="portal-section-head">
                        <div class="portal-label-stack">
                            <h2 class="portal-surface-card__title">Attachments</h2>
                            <p class="portal-surface-card__meta">Files shared with this ticket for faster troubleshooting.</p>
                        </div>
                    </div>

                    <div class="portal-attachment-list">
                        @foreach ($ticket->uploads as $up)
                            <div class="portal-attachment-item">
                                <div class="portal-label-stack">
                                    <span class="font-semibold text-slate-900">{{ $up->original_name ?: basename($up->path) }}</span>
                                    <span class="text-xs text-slate-500">Shared in ticket thread</span>
                                </div>
                                <a href="{{ $up->publicUrl() }}" target="_blank" rel="noopener" class="portal-card-button">Open file</a>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
