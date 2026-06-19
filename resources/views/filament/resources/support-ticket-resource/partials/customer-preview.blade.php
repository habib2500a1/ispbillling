@php
    $urls = $preview['urls'] ?? [];
    $onu = $preview['onu'] ?? null;
    $connectivity = ($preview['ppp_online'] ?? false) ? 'Online' : 'Offline';
@endphp

<section class="sp-panel sp-create-preview" aria-label="Subscriber & ONU preview" wire:key="sp-create-preview-{{ $preview['id'] ?? 'none' }}">
    <h2 class="sp-panel__title">Onu informations</h2>

    <div class="sp-onu-grid">
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">Device vendor name</span>
            <span class="sp-onu-grid__value">{{ $onu['vendor'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">Connectivity status</span>
            <span @class([
                'sp-onu-grid__value sp-onu-grid__badge',
                'sp-onu-grid__badge--ok' => ($preview['ppp_online'] ?? false),
                'sp-onu-grid__badge--bad' => ! ($preview['ppp_online'] ?? true),
            ])>{{ $connectivity }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">Client MAC address</span>
            <span class="sp-onu-grid__value font-mono text-xs">{{ $onu['mac'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">IP address</span>
            <span class="sp-onu-grid__value font-mono text-xs">{{ $onu['ip'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">OLT name</span>
            <span class="sp-onu-grid__value">{{ $onu['olt'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">Optical power</span>
            <span class="sp-onu-grid__value">{{ isset($onu['rx_dbm']) ? $onu['rx_dbm'].' dBm' : '—' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">OLT port</span>
            <span class="sp-onu-grid__value">{{ $onu['pon'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">ONU MAC / serial</span>
            <span class="sp-onu-grid__value font-mono text-xs">{{ $onu['serial'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">Status</span>
            <span class="sp-onu-grid__value">{{ $onu['status'] ?? 'Not Found' }}</span>
        </div>
        <div class="sp-onu-grid__field">
            <span class="sp-onu-grid__label">Last deregister / poll</span>
            <span class="sp-onu-grid__value">{{ $onu['last_polled'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field sp-onu-grid__field--wide">
            <span class="sp-onu-grid__label">Last deregister reasons</span>
            <span class="sp-onu-grid__value">{{ $onu['offline_reason'] ?? '—' }}</span>
        </div>
        <div class="sp-onu-grid__field sp-onu-grid__field--wide">
            <span class="sp-onu-grid__label">Description</span>
            <span class="sp-onu-grid__value">{{ $preview['address'] ?? '—' }}</span>
        </div>
    </div>

    <div class="sp-360__row">
        <span class="sp-360__label">Subscriber</span>
        <span class="sp-360__value">{{ $preview['name'] }} (#{{ $preview['code'] }})</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">Phone</span>
        <span class="sp-360__value">{{ $preview['phone'] ?? '—' }}</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">Package</span>
        <span class="sp-360__value">{{ $preview['package'] ?? '—' }}</span>
    </div>
    <div class="sp-360__row">
        <span class="sp-360__label">Due</span>
        <span class="sp-360__value">{{ $preview['billing_due_fmt'] ?? '—' }}</span>
    </div>

    <div class="sp-360__actions">
        @if (! empty($urls['profile']))
            <a href="{{ $urls['profile'] }}" class="sp-360__link" target="_blank" rel="noopener">Profile</a>
        @endif
        @if (! empty($urls['collect']))
            <a href="{{ $urls['collect'] }}" class="sp-360__link" target="_blank" rel="noopener">Collect</a>
        @endif
        @if (! empty($urls['tickets']))
            <a href="{{ $urls['tickets'] }}" class="sp-360__link" target="_blank" rel="noopener">Past tickets</a>
        @endif
    </div>
</section>
