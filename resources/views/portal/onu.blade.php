@extends('portal.layout')

@section('title', 'ONU status')

@section('content')
    @php
        $signalColor = match ($onu['color'] ?? 'gray') {
            'success' => 'portal-signal-emerald',
            'warning' => 'portal-signal-amber',
            'danger' => 'portal-signal-rose',
            default => 'portal-signal-slate',
        };
        $statusPill = match (strtolower((string) ($onu['oper_status'] ?? ''))) {
            'up', 'online', 'active' => 'portal-status-pill--success',
            'down', 'offline', 'los' => 'portal-status-pill--danger',
            'degraded', 'warning' => 'portal-status-pill--warning',
            default => 'portal-status-pill--muted',
        };
    @endphp

    <div id="onu-panel" data-live-url="{{ route('portal.onu.live') }}" data-poll-ms="{{ (int) config('portal.poll_seconds', 5) * 1000 }}">
        <div class="portal-page-head">
            <div>
                <h1 class="portal-page-title">ONU optical status</h1>
                <p class="portal-page-lead">Live RX, TX, and fiber health data pulled from your linked OLT path.</p>
            </div>
            <p id="onu-updated" class="portal-live-badge">Live</p>
        </div>

        @if (! ($onu['linked'] ?? false))
            <div class="portal-summary-grid">
                <article class="portal-summary-card portal-summary-card--warn">
                    <p class="portal-summary-card__eyebrow">ONU link status</p>
                    <p class="portal-summary-card__value">Not linked</p>
                    <p class="portal-summary-card__meta">{{ $onu['hint'] ?? 'No ONU is currently linked to this customer account.' }}</p>
                </article>
            </div>

            <section class="portal-surface-card">
                <div class="portal-section-head">
                    <div class="portal-label-stack">
                        <h2 class="portal-surface-card__title">Need optical support?</h2>
                        <p class="portal-surface-card__meta">Open a support ticket so your ISP team can link the ONU or verify the OLT path.</p>
                    </div>
                </div>
                <div class="portal-note-banner">
                    {{ $onu['hint'] ?? 'If your ONU is already installed, ask support to verify serial, MAC, and OLT-side mapping.' }}
                </div>
                <div class="portal-form-actions">
                    <a href="{{ route('portal.tickets.create') }}" class="portal-btn-primary portal-btn-ticket">Open support ticket</a>
                </div>
            </section>
        @else
            <div class="portal-summary-grid portal-summary-grid--wide">
                <article class="portal-summary-card portal-summary-card--info">
                    <p class="portal-summary-card__eyebrow">RX power</p>
                    <p id="onu-rx" class="portal-summary-card__value">{{ $onu['rx_dbm'] ?? '-' }} dBm</p>
                    <p class="portal-summary-card__meta">OLT to ONU downstream optical receive level.</p>
                </article>
                <article class="portal-summary-card portal-summary-card--info">
                    <p class="portal-summary-card__eyebrow">TX power</p>
                    <p id="onu-tx" class="portal-summary-card__value">{{ $onu['tx_dbm'] ?? '-' }} dBm</p>
                    <p class="portal-summary-card__meta">ONU transmit level reported from the latest poll.</p>
                </article>
                <article class="portal-summary-card {{ (($onu['fiber_health_percent'] ?? 0) >= 80) ? 'portal-summary-card--ok' : 'portal-summary-card--warn' }}">
                    <p class="portal-summary-card__eyebrow">Health score</p>
                    <p id="onu-health" class="portal-summary-card__value">{{ $onu['fiber_health_percent'] ?? 0 }}%</p>
                    <p class="portal-summary-card__meta">Combined signal quality estimate from optical readings.</p>
                </article>
                <article class="portal-summary-card {{ in_array(strtolower((string) ($onu['oper_status'] ?? '')), ['up', 'online', 'active'], true) ? 'portal-summary-card--ok' : 'portal-summary-card--due' }}">
                    <p class="portal-summary-card__eyebrow">ONU status</p>
                    <p id="onu-oper-text" class="portal-summary-card__value">{{ $onu['oper_status'] ?? 'Unknown' }}</p>
                    <p class="portal-summary-card__meta">
                        <span id="onu-oper-pill" class="portal-status-pill {{ $statusPill }}">{{ $onu['rx_level_label'] ?? 'Signal pending' }}</span>
                    </p>
                </article>
            </div>

            <div class="portal-section-grid portal-section-grid--2">
                <section class="portal-surface-card">
                    <div class="portal-section-head">
                        <div class="portal-label-stack">
                            <h2 class="portal-surface-card__title">Signal quality</h2>
                            <p class="portal-surface-card__meta">Optical strength and stability indicators for the linked ONU.</p>
                        </div>
                        <span id="onu-rx-label" class="portal-status-pill {{ $signalColor }}">{{ $onu['rx_level_label'] ?? 'Unknown' }}</span>
                    </div>

                    <div class="portal-metric-inline">
                        <div>
                            <p class="portal-summary-card__eyebrow">Fiber stability</p>
                            <p class="portal-metric-inline__value"><span id="onu-stability">{{ $onu['stability_percent'] ?? 0 }}</span>%</p>
                        </div>
                    </div>

                    <div class="portal-meter">
                        <div class="portal-meter__track">
                            <div id="onu-stability-bar" class="portal-meter__fill" data-width="{{ $onu['stability_percent'] ?? 0 }}"></div>
                        </div>
                        <div class="portal-meter__label">
                            <span>Low fluctuation is better</span>
                            <span>Target: 80%+</span>
                        </div>
                    </div>

                    @if (! empty($onu['root_cause']))
                        <div id="onu-hint" class="portal-note-banner portal-note-banner--danger">
                            Hint: {{ str_replace('_', ' ', $onu['root_cause']) }}
                        </div>
                    @endif
                </section>

                <section class="portal-surface-card">
                    <div class="portal-section-head">
                        <div class="portal-label-stack">
                            <h2 class="portal-surface-card__title">ONU details</h2>
                            <p class="portal-surface-card__meta">Identifiers and last synced OLT metadata for your connection.</p>
                        </div>
                    </div>

                    <dl class="portal-detail-list">
                        <div class="portal-detail-list__item">
                            <dt>Username</dt>
                            <dd class="portal-mono">{{ $onu['username'] ?? '-' }}</dd>
                        </div>
                        <div class="portal-detail-list__item">
                            <dt>Port</dt>
                            <dd class="portal-mono">{{ $onu['port'] ?? '-' }}</dd>
                        </div>
                        <div class="portal-detail-list__item">
                            <dt>MAC</dt>
                            <dd class="portal-mono">{{ $onu['mac'] ?? '-' }}</dd>
                        </div>
                        <div class="portal-detail-list__item">
                            <dt>Model</dt>
                            <dd>{{ $onu['model'] ?? '-' }}</dd>
                        </div>
                        <div class="portal-detail-list__item">
                            <dt>Vendor</dt>
                            <dd>{{ $onu['vendor'] ?? '-' }}</dd>
                        </div>
                        <div class="portal-detail-list__item">
                            <dt>Distance</dt>
                            <dd>{{ isset($onu['distance_m']) && $onu['distance_m'] ? $onu['distance_m'].' m' : '-' }}</dd>
                        </div>
                        <div class="portal-detail-list__item">
                            <dt>Serial</dt>
                            <dd class="portal-mono">{{ $onu['serial'] ?? '-' }}</dd>
                        </div>
                        <div class="portal-detail-list__item">
                            <dt>Label</dt>
                            <dd>{{ $onu['label'] ?? '-' }}</dd>
                        </div>
                        @if (! empty($onu['cust_mac_found']))
                            <div class="portal-detail-list__item">
                                <dt>Customer MAC seen</dt>
                                <dd>{{ $onu['cust_mac_found'] }}</dd>
                            </div>
                        @endif
                        <div class="portal-detail-list__item">
                            <dt>Last OLT poll</dt>
                            <dd id="onu-polled">{{ $onu['last_polled'] ?? '-' }}</dd>
                        </div>
                        @if (! empty($onu['detected_label']))
                            <div class="portal-detail-list__item">
                                <dt>Detection</dt>
                                <dd>{{ $onu['detected_label'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            const onuPanel = document.getElementById('onu-panel');
            const onuLiveUrl = onuPanel.dataset.liveUrl;
            const onuPollMs = Number(onuPanel.dataset.pollMs || 5000);
            const onuStabilityBar = document.getElementById('onu-stability-bar');

            onuStabilityBar.style.width = (onuStabilityBar.dataset.width || 0) + '%';

            function signalClass(color) {
                if (color === 'success') return 'portal-status-pill portal-signal-emerald';
                if (color === 'warning') return 'portal-status-pill portal-signal-amber';
                if (color === 'danger') return 'portal-status-pill portal-signal-rose';
                return 'portal-status-pill portal-signal-slate';
            }

            function operClass(status) {
                const value = String(status || '').toLowerCase();
                if (['up', 'online', 'active'].includes(value)) return 'portal-status-pill portal-status-pill--success';
                if (['degraded', 'warning'].includes(value)) return 'portal-status-pill portal-status-pill--warning';
                if (['down', 'offline', 'los'].includes(value)) return 'portal-status-pill portal-status-pill--danger';
                return 'portal-status-pill portal-status-pill--muted';
            }

            async function refreshOnu() {
                try {
                    const res = await fetch(onuLiveUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) return;

                    const o = await res.json();
                    if (!o.linked) return;

                    document.getElementById('onu-rx').textContent = (o.rx_dbm ?? '-') + ' dBm';
                    document.getElementById('onu-tx').textContent = (o.tx_dbm ?? '-') + ' dBm';
                    document.getElementById('onu-rx-label').textContent = o.rx_level_label || 'Unknown';
                    document.getElementById('onu-rx-label').className = signalClass(o.color);
                    document.getElementById('onu-stability').textContent = o.stability_percent || 0;
                    onuStabilityBar.style.width = (o.stability_percent || 0) + '%';
                    document.getElementById('onu-oper-text').textContent = o.oper_status || 'Unknown';
                    document.getElementById('onu-oper-pill').className = operClass(o.oper_status);
                    document.getElementById('onu-oper-pill').textContent = o.rx_level_label || 'Signal pending';
                    document.getElementById('onu-health').textContent = (o.fiber_health_percent || 0) + '%';
                    document.getElementById('onu-polled').textContent = o.last_polled || '-';

                    const hint = document.getElementById('onu-hint');
                    if (hint && o.root_cause) {
                        hint.textContent = 'Hint: ' + String(o.root_cause).replaceAll('_', ' ');
                    }

                    document.getElementById('onu-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
                } catch (e) {
                    document.getElementById('onu-updated').textContent = 'Live sync paused';
                }
            }

            setInterval(refreshOnu, onuPollMs);
        </script>
    @endpush
@endsection
