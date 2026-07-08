@php
    $cc = $commandCenter ?? [];
    $tickets = $cc['tickets'] ?? ['open' => [], 'open_count' => 0];
    $live = $cc['live_session'] ?? [];
    $health = $cc['health'] ?? ['score' => 0, 'label' => '—'];
    $intel = $cc['intelligence'] ?? [];
@endphp

<div class="sub-cc-grid">
    <section class="isp-cv-card sub-cc-panel sub-cc-panel--live">
        <div class="isp-cv-card__head">
            <h3 class="isp-cv-card__title">Live PPP session</h3>
            <span @class(['sub-cc-live-pill', ($live['online'] ?? false) ? 'is-online' : 'is-offline'])>{{ ($live['online'] ?? false) ? 'Connected' : 'Disconnected' }}</span>
        </div>
        <dl class="sub-cc-kv">
            <div><dt>Duration</dt><dd>{{ $live['duration'] ?? '—' }}</dd></div>
            <div><dt>Client IP</dt><dd class="font-mono">{{ $live['framed_ip'] ?? '—' }}</dd></div>
            <div><dt>Session MAC</dt><dd class="font-mono text-xs">{{ $live['caller_id'] ?? '—' }}</dd></div>
            <div><dt>Started</dt><dd>{{ $live['started_at'] ?? '—' }}</dd></div>
            <div><dt>Download</dt><dd>{{ $live['bytes_in'] ?? '—' }}</dd></div>
            <div><dt>Upload</dt><dd>{{ $live['bytes_out'] ?? '—' }}</dd></div>
            <div><dt>Router</dt><dd>{{ $live['router'] ?? '—' }}</dd></div>
            <div><dt>Last disconnect</dt><dd>{{ $live['last_disconnect'] ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="isp-cv-card sub-cc-panel sub-cc-panel--intel">
        <div class="isp-cv-card__head">
            <h3 class="isp-cv-card__title">Enterprise intelligence</h3>
            <div class="sub-cc-health-ring" data-score="{{ $health['score'] ?? 0 }}">
                <strong>{{ $health['score'] ?? 0 }}</strong><span>{{ $health['label'] ?? '—' }}</span>
            </div>
        </div>
        <p class="sub-cc-ai-summary">{{ $intel['ai_summary'] ?? '—' }}</p>
        <div class="sub-cc-intel-grid">
            <div>
                <span class="sub-cc-intel-label">Churn risk</span>
                <strong>{{ $intel['churn']['label'] ?? '—' }}</strong>
                <span class="text-xs text-slate-500">{{ $intel['churn']['hint'] ?? '' }}</span>
            </div>
            <div>
                <span class="sub-cc-intel-label">Lifetime revenue</span>
                <strong>{{ number_format((float) ($intel['revenue']['lifetime_bdt'] ?? 0), 0) }} BDT</strong>
                <span class="text-xs text-slate-500">ARPU {{ number_format((float) ($intel['revenue']['arpu_bdt'] ?? 0), 0) }} BDT/mo</span>
            </div>
        </div>
    </section>

    <section class="isp-cv-card sub-cc-panel sub-cc-panel--tickets">
        <div class="isp-cv-card__head">
            <h3 class="isp-cv-card__title">Support tickets</h3>
            <div class="flex gap-2">
                <a href="{{ $tickets['create_url'] ?? '#' }}" class="isp-cv-link">+ New</a>
                <a href="{{ $tickets['index_url'] ?? '#' }}" class="isp-cv-link">All →</a>
            </div>
        </div>
        @if (($tickets['open'] ?? []) === [])
            <p class="isp-cv-muted text-sm">No open tickets.</p>
        @else
            <div class="sub-cc-ticket-list">
                @foreach ($tickets['open'] as $ticket)
                    <a href="{{ $ticket['url'] }}" class="sub-cc-ticket-row">
                        <div>
                            <strong class="font-mono text-xs">{{ $ticket['number'] }}</strong>
                            <span class="sub-cc-ticket-subject">{{ $ticket['subject'] }}</span>
                        </div>
                        <div class="sub-cc-ticket-meta">
                            <span class="isp-cv-pill isp-cv-pill--muted">{{ $ticket['status'] }}</span>
                            <span class="text-xs">{{ $ticket['assignee'] ?? 'Unassigned' }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
