@php
    $urls = $preview['urls'] ?? [];
@endphp

<section class="sp-panel sp-create-preview" aria-label="Customer 360 preview" wire:key="sp-create-preview-{{ $preview['id'] ?? 'none' }}">
    <h2 class="sp-panel__title">Customer 360</h2>

    <section
        @class([
            'sp-live-status sp-live-status--compact',
            'sp-live-status--ok' => ($live['ppp_online'] ?? false) && (($live['onu_online'] ?? null) !== false),
            'sp-live-status--warn' => ! ($live['ppp_online'] ?? true) || ($live['onu_online'] ?? null) === false,
        ])
    >
        <div class="sp-live-status__grid">
            <div class="sp-live-status__item">
                <span class="sp-live-status__label">PPP</span>
                <span class="sp-live-status__badge @if ($live['ppp_online'] ?? false) sp-live-status__badge--online @else sp-live-status__badge--offline @endif">
                    {{ ($live['ppp_online'] ?? false) ? 'Online' : 'Offline' }}
                </span>
            </div>
            <div class="sp-live-status__item">
                <span class="sp-live-status__label">ONU</span>
                @if (($live['onu_online'] ?? null) === null)
                    <span class="sp-live-status__badge sp-live-status__badge--muted">Not mapped</span>
                @else
                    <span class="sp-live-status__badge @if ($live['onu_online']) sp-live-status__badge--online @else sp-live-status__badge--offline @endif">
                        {{ $live['onu_online'] ? 'Online' : 'Offline' }}
                    </span>
                @endif
            </div>
            <div class="sp-live-status__item">
                <span class="sp-live-status__label">Due</span>
                <span class="sp-live-status__time">{{ $preview['billing_due_fmt'] ?? '—' }}</span>
            </div>
        </div>
    </section>

    <p class="sp-360__name">{{ $preview['name'] }}</p>
    <p class="sp-360__code">#{{ $preview['code'] }}</p>

    <div class="sp-360__row">
        <span class="sp-360__label">Phone</span>
        <span class="sp-360__value">{{ $preview['phone'] ?? '—' }}</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">Package</span>
        <span class="sp-360__value">{{ $preview['package'] ?? '—' }}</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">Area</span>
        <span class="sp-360__value">{{ $preview['area'] ?? '—' }}</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">PPP login</span>
        <span class="sp-360__value font-mono text-xs">{{ $preview['ppp_login'] ?? '—' }}</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">Past tickets</span>
        <span class="sp-360__value">{{ number_format((int) ($preview['ticket_count'] ?? 0)) }}</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">Last payment</span>
        <span class="sp-360__value">{{ $preview['last_payment'] ?? '—' }}</span>
    </div>

    <div class="sp-360__actions">
        @if (! empty($urls['profile']))
            <a href="{{ $urls['profile'] }}" class="sp-360__link" target="_blank" rel="noopener">Profile</a>
        @endif
        @if (! empty($urls['collect']))
            <a href="{{ $urls['collect'] }}" class="sp-360__link" target="_blank" rel="noopener">Collect</a>
        @endif
        @if (! empty($urls['invoices']))
            <a href="{{ $urls['invoices'] }}" class="sp-360__link" target="_blank" rel="noopener">Invoices</a>
        @endif
        @if (! empty($urls['tickets']))
            <a href="{{ $urls['tickets'] }}" class="sp-360__link" target="_blank" rel="noopener">Past tickets</a>
        @endif
    </div>
</section>
