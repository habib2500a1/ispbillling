@php
    $cc = $commandCenter ?? [];
    $tickets = $cc['tickets'] ?? ['open' => [], 'open_count' => 0];
    $timeline = $cc['timeline'] ?? [];
    $live = $cc['live_session'] ?? [];
    $usage = $cc['usage'] ?? [];
    $spark = $cc['onu_sparkline'] ?? [];
    $fraud = $cc['fraud_mac'] ?? [];
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
        @if (! empty($intel['pon_impact']['available']))
            <div class="sub-cc-pon-impact">
                <strong>PON impact</strong> · {{ $intel['pon_impact']['olt'] ?? 'OLT' }} · {{ $intel['pon_impact']['pon'] ?? '—' }}
                · {{ $intel['pon_impact']['neighbors_on_pon'] ?? 0 }} neighbors · {{ $intel['pon_impact']['open_tickets_on_olt'] ?? 0 }} OLT tickets
            </div>
        @endif
        @if (! empty($intel['nearby']))
            <div class="sub-cc-nearby">
                <span class="sub-cc-intel-label">Nearby in area</span>
                <div class="sub-cc-nearby-list">
                    @foreach ($intel['nearby'] as $nb)
                        <a href="{{ $nb['url'] }}" class="sub-cc-nearby-chip">{{ $nb['code'] }} · {{ \Illuminate\Support\Str::limit($nb['name'], 18) }}</a>
                    @endforeach
                </div>
            </div>
        @endif
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

    <section class="isp-cv-card sub-cc-panel sub-cc-panel--timeline">
        <div class="isp-cv-card__head sub-cc-panel__head">
            <div class="sub-cc-panel__head-main">
                <h3 class="isp-cv-card__title">Activity timeline</h3>
                <span class="sub-cc-panel__hint">Payments · tickets · SMS · notes</span>
            </div>
        </div>
        @if ($timeline === [])
            <p class="isp-cv-muted text-sm">No activity yet.</p>
        @else
            <div class="sub-cc-timeline-wrap">
                <ol class="sub-cc-timeline">
                @foreach ($timeline as $event)
                    <li class="sub-cc-timeline__item sub-cc-timeline__item--{{ $event['tone'] ?? 'slate' }}">
                        <span class="sub-cc-timeline__dot"></span>
                        <div>
                            <strong>{{ $event['title'] }}</strong>
                            <p>{{ $event['detail'] ?? '' }}</p>
                            <time>{{ $event['ago'] ?? '' }}</time>
                        </div>
                    </li>
                @endforeach
                </ol>
            </div>
        @endif
    </section>

    <section class="isp-cv-card sub-cc-panel sub-cc-panel--usage" data-sub-usage-panel>
        <div class="isp-cv-card__head sub-cc-panel__head">
            <h3 class="isp-cv-card__title">Usage analytics</h3>
            <div class="sub-cc-usage-tabs" role="tablist" aria-label="Usage period">
                <button type="button" class="is-active" data-usage-tab="day">7 days</button>
                <button type="button" data-usage-tab="week">4 weeks</button>
                <button type="button" data-usage-tab="month">90 days</button>
            </div>
        </div>
        <div class="sub-cc-chart-wrap">
            <canvas id="sub-usage-chart" height="120"></canvas>
        </div>
        <script type="application/json" id="sub-usage-data">@json($usage)</script>
    </section>

    <section class="isp-cv-card sub-cc-panel sub-cc-panel--optical">
        <div class="isp-cv-card__head">
            <h3 class="isp-cv-card__title">ONU signal · 24h</h3>
            @if ($spark['available'] ?? false)
                <span class="text-xs font-mono">{{ $spark['current_rx'] ?? '—' }} dBm</span>
            @endif
        </div>
        @if ($spark['available'] ?? false)
            <canvas id="sub-onu-sparkline" height="64"></canvas>
            <script type="application/json" id="sub-onu-spark-data">@json(['labels' => $spark['labels'] ?? [], 'rx' => $spark['rx'] ?? []])</script>
        @else
            <p class="isp-cv-muted text-sm">No ONU signal history.</p>
        @endif
        <div @class(['sub-cc-fraud', 'sub-cc-fraud--alert' => ($fraud['risk'] ?? '') === 'elevated'])>
            <strong>MAC / fraud strip</strong>
            <p class="text-xs">Binding: <span class="font-mono">{{ $fraud['binding_mac'] ?? '—' }}</span> · ONU: <span class="font-mono">{{ $fraud['onu_mac'] ?? '—' }}</span></p>
            @if (! empty($fraud['alerts']))
                <ul class="sub-cc-fraud-alerts">@foreach ($fraud['alerts'] as $alert)<li>{{ $alert }}</li>@endforeach</ul>
            @else
                <p class="text-xs text-emerald-700">No MAC anomalies in recent sessions.</p>
            @endif
        </div>
    </section>
</div>
