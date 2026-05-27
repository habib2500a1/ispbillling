@extends('portal.layout')

@section('title', 'Notifications')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Notifications</h1>
            <p class="portal-page-lead">Bills, outages, optical alerts, payments, and service reminders in one place.</p>
        </div>
        <a href="{{ route('portal.dashboard') }}" class="portal-card-button">Back to dashboard</a>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article class="portal-summary-card {{ ($summary['action_required'] ?? 0) > 0 ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Action required</p>
            <p class="portal-summary-card__value">{{ $summary['action_required'] ?? 0 }}</p>
            <p class="portal-summary-card__meta">Danger or warning alerts that may need payment, support, or outage follow-up.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">All alerts</p>
            <p class="portal-summary-card__value">{{ $summary['total'] ?? 0 }}</p>
            <p class="portal-summary-card__meta">Combined notification feed for your account and area.</p>
        </article>
        <article class="portal-summary-card {{ ($summary['payments'] ?? 0) > 0 ? 'portal-summary-card--ok' : 'portal-summary-card--info' }}">
            <p class="portal-summary-card__eyebrow">Payment updates</p>
            <p class="portal-summary-card__value">{{ $summary['payments'] ?? 0 }}</p>
            <p class="portal-summary-card__meta">Successful payment confirmations in the recent feed.</p>
        </article>
        <article class="portal-summary-card {{ ($summary['outages'] ?? 0) > 0 ? 'portal-summary-card--warn' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Area notices</p>
            <p class="portal-summary-card__value">{{ $summary['outages'] ?? 0 }}</p>
            <p class="portal-summary-card__meta">
                @if (! empty($summary['latest_at']))
                    Latest update {{ \Carbon\Carbon::parse($summary['latest_at'])->diffForHumans() }}
                @else
                    No recent alert timestamp found.
                @endif
            </p>
        </article>
    </div>

    <section class="portal-surface-card">
        <div class="portal-section-head">
            <div class="portal-label-stack">
                <h2 class="portal-surface-card__title">Alert feed</h2>
                <p class="portal-surface-card__meta">Ordered from newest to oldest so urgent items stay at the top.</p>
            </div>
            <div class="portal-inline-list">
                <span class="portal-inline-chip">Customer {{ $customer->customer_code }}</span>
                @if (($summary['service_expiry'] ?? 0) > 0)
                    <span class="portal-inline-chip">Expiry reminder active</span>
                @endif
            </div>
        </div>

        <div class="portal-alert-feed">
            @forelse ($items as $item)
                @php
                    $pillClass = match ($item['severity']) {
                        'danger' => 'portal-status-pill--danger',
                        'warning' => 'portal-status-pill--warning',
                        'success' => 'portal-status-pill--success',
                        default => 'portal-status-pill--muted',
                    };
                @endphp
                <article class="portal-alert-card portal-alert-card--{{ $item['severity'] }}">
                    <div class="portal-alert-card__head">
                        <div>
                            <h3 class="portal-alert-card__title">{{ $item['title'] }}</h3>
                            <p class="portal-alert-card__body">{{ $item['message'] }}</p>
                        </div>
                        <span class="portal-alert-card__time">{{ \Carbon\Carbon::parse($item['at'])->diffForHumans() }}</span>
                    </div>
                    <div class="portal-alert-card__meta">
                        <span class="portal-status-pill {{ $pillClass }}">{{ \Illuminate\Support\Str::headline($item['type']) }}</span>
                    </div>
                </article>
            @empty
                <p class="portal-empty-state">No alerts right now. Bills, outage notices, optical warnings, and payment confirmations will show here.</p>
            @endforelse
        </div>
    </section>
@endsection
