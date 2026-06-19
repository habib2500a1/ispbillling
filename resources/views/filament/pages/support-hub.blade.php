@php
    $stats = $this->getStats();
    $slaRows = $this->getSlaByDepartment();
    $inbox = $this->getActionInbox();
    $ticketsUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
@endphp

{!! \App\Support\SupportStyles::html() !!}
{!! \App\Support\SupportStyles::navigatedScript() !!}
<script src="{{ asset('js/support-hub-v3.js') }}?v={{ @filemtime(public_path('js/support-hub-v3.js')) ?: 1 }}" defer></script>

<x-filament-panels::page class="isp-support-hub-page">
    <div class="sh-pro" data-sh-hub>
        <header class="sh-hero sp-grad--hero">
            <div>
                <span class="sh-hero__badge">Service operations</span>
                <h1 class="sh-hero__title">Support Operations Center</h1>
                <p class="sh-hero__sub">
                    Portal tickets · call center · SLA tracking · technician assignment · live chat — enterprise ISP service desk.
                </p>
                <div class="sh-hero__actions">
                    <a href="{{ \App\Filament\Resources\SupportTicketResource::getUrl('create') }}" class="sh-btn sh-btn--white" data-navigate="false">
                        <x-filament::icon icon="heroicon-m-plus-circle" class="h-4 w-4" />
                        New ticket
                    </a>
                    <a href="{{ $ticketsUrl }}" class="sh-btn sh-btn--glass">
                        <x-filament::icon icon="heroicon-m-ticket" class="h-4 w-4" />
                        All tickets
                    </a>
                    <a href="{{ \App\Filament\Pages\CallCenterHub::getUrl() }}" class="sh-btn sh-btn--glass">
                        <x-filament::icon icon="heroicon-m-phone" class="h-4 w-4" />
                        Call center
                    </a>
                </div>
            </div>
            <div class="sh-hero__kpi">
                <span class="sh-hero__kpi-label">Open queue</span>
                <strong class="sh-hero__kpi-value">{{ number_format($stats['open']) }}</strong>
                <span class="sh-hero__kpi-label" style="margin-top:0.35rem;display:block;">
                    {{ $stats['breached'] }} SLA breach · {{ $stats['unassigned'] }} unassigned
                </span>
            </div>
        </header>

        <nav class="sh-tabs" aria-label="Support sections">
            <button type="button" class="sh-tabs__btn sh-tabs__btn--active" data-sh-tab="overview">Overview</button>
            <button type="button" class="sh-tabs__btn" data-sh-tab="queue">Queue</button>
            <button type="button" class="sh-tabs__btn" data-sh-tab="analytics">Analytics</button>
            <button type="button" class="sh-tabs__btn" data-sh-tab="tools">Tools</button>
        </nav>

        <div class="sh-tab-panel" data-sh-panel="overview">
            <section class="enoc-dashboard" aria-label="NOC ticket dashboard">
                <div class="enoc-kpi-row">
                    @foreach ($this->getNocDashboardKpis() as $kpi)
                        <a href="{{ $kpi['url'] }}" @class(['enoc-kpi', 'enoc-kpi--'.$kpi['tone']])>
                            <span class="enoc-kpi__label">{{ $kpi['label'] }}</span>
                            <strong class="enoc-kpi__value">{{ $kpi['value'] }}</strong>
                        </a>
                    @endforeach
                </div>

                <div class="enoc-grid-2">
                    <div class="enoc-panel">
                        <h3 class="enoc-panel__title">Open tickets by category</h3>
                        @php $catRows = $this->getCategoryBreakdown(); @endphp
                        @if ($catRows === [])
                            <p style="margin:0;font-size:0.72rem;color:var(--sp-muted);">No open tickets in queue.</p>
                        @else
                            <div class="enoc-cat-grid">
                                @foreach ($catRows as $row)
                                    <div class="enoc-cat">
                                        <span class="enoc-cat__count">{{ number_format($row['count']) }}</span>
                                        <span class="enoc-cat__group">{{ $row['group'] }}</span>
                                        <span class="enoc-cat__label">{{ $row['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="enoc-panel">
                        <h3 class="enoc-panel__title">Active mass incidents</h3>
                        @php $incidents = $this->getActiveIncidents(); @endphp
                        @if ($incidents === [])
                            <p style="margin:0;font-size:0.72rem;color:var(--sp-muted);">No active root incidents — mass outage engine idle.</p>
                        @else
                            @foreach ($incidents as $inc)
                                <div class="enoc-incident">
                                    <div>
                                        <span class="enoc-incident__badge">{{ $inc['number'] }}</span>
                                        <strong style="margin-left:0.35rem;">{{ $inc['title'] }}</strong><br>
                                        <span style="color:var(--sp-muted);font-size:0.65rem;">Affected: {{ number_format($inc['count']) }} · {{ $inc['detected'] }}</span>
                                    </div>
                                    <a href="{{ $inc['url'] }}" class="sp-360__link">NOC wall →</a>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="enoc-grid-2">
                    <div class="enoc-panel">
                        <h3 class="enoc-panel__title">SLA targets (standard profile)</h3>
                        <table class="enoc-sla-table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Response</th>
                                    <th>Resolution</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->getSlaMatrix() as $row)
                                    <tr>
                                        <td><span class="enoc-priority-pill enoc-priority-pill--{{ strtolower($row['priority']) }}">{{ $row['code'] }} {{ $row['priority'] }}</span></td>
                                        <td>{{ $row['response'] }}</td>
                                        <td>{{ $row['resolution'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="enoc-panel">
                        <h3 class="enoc-panel__title">Escalation flow (SLA breach)</h3>
                        <div class="enoc-escalation">
                            @foreach ($this->getEscalationLadder() as $i => $step)
                                @if ($i > 0)
                                    <span class="enoc-escalation__arrow">↓</span>
                                @endif
                                <span class="enoc-escalation__step">{{ $step['label'] }}</span>
                            @endforeach
                        </div>
                        <p style="margin:0.65rem 0 0;font-size:0.65rem;color:var(--sp-muted);">
                            Auto-tickets: ONU offline &gt;24h · OLT PON down · RX &lt; -30 dBm · mass outage merges into one incident.
                        </p>
                    </div>
                </div>
            </section>

            @if ($inbox !== [])
                <section class="sh-inbox" aria-label="Action inbox">
                    <h2 class="text-sm font-extrabold text-gray-900 dark:text-white" style="margin:0 0 0.5rem;">Action inbox</h2>
                    <ul class="sh-inbox__list">
                        @foreach ($inbox as $item)
                            <li class="sh-inbox__item">
                                <a href="{{ $item['url'] }}">
                                    <strong>{{ $item['title'] }}</strong><br>
                                    <span style="font-size:0.68rem;color:var(--sp-muted);">{{ $item['message'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="sh-stats">
                @foreach ($this->getKpiCards() as $kpi)
                    <a href="{{ $kpi['url'] }}" class="sh-stat sh-stat--{{ $kpi['tone'] }}">
                        <span class="sh-stat__label">{{ $kpi['label'] }}</span>
                        <strong class="sh-stat__value">{{ $kpi['value'] }}</strong>
                        <span class="sh-stat__hint">{{ $kpi['hint'] }}</span>
                    </a>
                @endforeach
            </div>

            @if (count($slaRows) > 0)
                <section style="margin-top:1rem;padding:0.85rem;border-radius:var(--sp-radius-sm);background:var(--sp-card);border:1px solid var(--sp-border);">
                    <h3 style="margin:0 0 0.65rem;font-size:0.85rem;font-weight:800;color:var(--sp-text);">SLA by department</h3>
                    <div style="overflow-x:auto;">
                        <table class="sh-sla-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th style="text-align:right;">Open</th>
                                    <th style="text-align:right;">Overdue</th>
                                    <th style="text-align:right;">Unassigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($slaRows as $row)
                                    <tr>
                                        <td style="font-weight:600;color:var(--sp-text);">{{ $row['label'] }}</td>
                                        <td style="text-align:right;">{{ $row['open'] }}</td>
                                        <td style="text-align:right;" @class(['sh-sla-breached' => $row['breached'] > 0])>{{ $row['breached'] }}</td>
                                        <td style="text-align:right;">{{ $row['unassigned'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <div class="sh-tab-panel" data-sh-panel="queue" hidden>
            <p style="font-size:0.8rem;color:var(--sp-muted);margin:0 0 0.75rem;">Jump to filtered ticket queues — same logic as the ticket list tabs.</p>
            <div class="sp-queue-chips">
                @foreach ([
                    ['key' => 'open', 'label' => 'Open', 'count' => $stats['open']],
                    ['key' => 'sla', 'label' => 'SLA breach', 'count' => $stats['breached']],
                    ['key' => 'unassigned', 'label' => 'Unassigned', 'count' => $stats['unassigned']],
                    ['key' => 'live_chat', 'label' => 'Live chat', 'count' => $stats['live_chat']],
                ] as $chip)
                    <a href="{{ $ticketsUrl }}?{{ http_build_query(['activeTab' => $chip['key']]) }}" class="sp-queue-chip">
                        {{ $chip['label'] }}
                        <span class="sp-queue-chip__count">{{ $chip['count'] }}</span>
                    </a>
                @endforeach
                <a href="{{ $ticketsUrl }}" class="sp-queue-chip sp-queue-chip--active">All tickets →</a>
            </div>
        </div>

        <div class="sh-tab-panel" data-sh-panel="analytics" hidden>
            <div class="sh-stats">
                @foreach ($this->getAnalyticsStats() as $stat)
                    <div class="sh-stat" style="cursor:default;">
                        <span class="sh-stat__label">{{ $stat['label'] }}</span>
                        <strong class="sh-stat__value">{{ $stat['value'] }}</strong>
                        <span class="sh-stat__hint">{{ $stat['hint'] }}</span>
                    </div>
                @endforeach
            </div>

            <section style="margin-top:1rem;display:grid;gap:1rem;">
                @if (count($categories = $this->getCategoryTrends()) > 0)
                    <div style="padding:0.85rem;border-radius:var(--sp-radius-sm);background:var(--sp-card);border:1px solid var(--sp-border);">
                        <h3 style="margin:0 0 0.65rem;font-size:0.85rem;font-weight:800;">Complaint categories (30d)</h3>
                        @foreach ($categories as $cat)
                            <div style="margin-bottom:0.45rem;">
                                <div style="display:flex;justify-content:space-between;font-size:0.72rem;font-weight:700;">
                                    <span>{{ $cat['label'] }}</span>
                                    <span>{{ $cat['count'] }} ({{ $cat['percent'] }}%)</span>
                                </div>
                                <div style="height:6px;border-radius:999px;background:var(--sp-border);overflow:hidden;">
                                    <div style="height:100%;width:{{ min(100, $cat['percent']) }}%;background:var(--sp-amber);"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (count($techs = $this->getTechnicianPerformance()) > 0)
                    <div style="padding:0.85rem;border-radius:var(--sp-radius-sm);background:var(--sp-card);border:1px solid var(--sp-border);">
                        <h3 style="margin:0 0 0.65rem;font-size:0.85rem;font-weight:800;">Technician ranking (30d)</h3>
                        <table class="sh-sla-table">
                            <thead>
                                <tr>
                                    <th>Technician</th>
                                    <th style="text-align:right;">Resolved</th>
                                    <th style="text-align:right;">Open</th>
                                    <th style="text-align:right;">Avg h</th>
                                    <th style="text-align:right;">SLA %</th>
                                    <th style="text-align:right;">CSAT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($techs as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td style="text-align:right;">{{ $row['resolved'] }}</td>
                                        <td style="text-align:right;">{{ $row['open'] }}</td>
                                        <td style="text-align:right;">{{ $row['avg_hours'] }}</td>
                                        <td style="text-align:right;">{{ $row['sla_pct'] }}%</td>
                                        <td style="text-align:right;">{{ $row['csat'] > 0 ? $row['csat'].'/5' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (count($areas = $this->getAreaComplaints()) > 0)
                    <div style="padding:0.85rem;border-radius:var(--sp-radius-sm);background:var(--sp-card);border:1px solid var(--sp-border);">
                        <h3 style="margin:0 0 0.65rem;font-size:0.85rem;font-weight:800;">Area-wise complaints</h3>
                        <table class="sh-sla-table">
                            <thead>
                                <tr>
                                    <th>Area</th>
                                    <th style="text-align:right;">Open</th>
                                    <th style="text-align:right;">30d total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areas as $row)
                                    <tr>
                                        <td>{{ $row['area'] }}</td>
                                        <td style="text-align:right;">{{ $row['open'] }}</td>
                                        <td style="text-align:right;">{{ $row['total_30d'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (count($olts = $this->getOltComplaints()) > 0)
                    <div style="padding:0.85rem;border-radius:var(--sp-radius-sm);background:var(--sp-card);border:1px solid var(--sp-border);">
                        <h3 style="margin:0 0 0.65rem;font-size:0.85rem;font-weight:800;">OLT-wise complaints (open)</h3>
                        <table class="sh-sla-table">
                            <thead>
                                <tr>
                                    <th>OLT</th>
                                    <th style="text-align:right;">Open</th>
                                    <th style="text-align:right;">Critical</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($olts as $row)
                                    <tr>
                                        <td>{{ $row['olt'] }}</td>
                                        <td style="text-align:right;">{{ $row['open'] }}</td>
                                        <td style="text-align:right;">{{ $row['critical'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="sh-tab-panel" data-sh-panel="tools" hidden>
            <div class="sh-bento">
                @foreach ($this->getToolCards() as $card)
                    <a
                        href="{{ $card['url'] }}"
                        @class(['sh-tile', 'sh-tile--featured' => ! empty($card['featured'])])
                        @if (str_contains($card['url'], '/create')) data-navigate="false" @endif
                    >
                        <x-filament::icon :icon="$card['icon']" class="h-6 w-6" style="color:var(--sp-amber);" />
                        <span class="sh-tile__title">{{ $card['title'] }}</span>
                        <span class="sh-tile__desc">{{ $card['desc'] }}</span>
                    </a>
                @endforeach
            </div>

            <details style="margin-top:1rem;font-size:0.78rem;color:var(--sp-muted);">
                <summary style="cursor:pointer;font-weight:700;color:var(--sp-text);">Webhook &amp; scheduler</summary>
                <div style="margin-top:0.5rem;padding:0.75rem;border-radius:10px;border:1px solid var(--sp-border);background:var(--sp-card);">
                    <code style="font-size:0.68rem;">POST {{ url('/api/webhooks/support-ticket-ingest') }}</code><br>
                    <span style="font-size:0.68rem;">Header: X-ISP-Webhook-Secret · SLA: <code>isp:support-check-sla</code> every 30 min</span>
                </div>
            </details>
        </div>

        <nav class="sh-dock" aria-label="Quick navigation">
            <div class="sh-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
                    ['url' => $ticketsUrl, 'label' => 'Tickets', 'icon' => 'heroicon-o-ticket'],
                    ['url' => \App\Filament\Pages\CallCenterHub::getUrl(), 'label' => 'Calls', 'icon' => 'heroicon-o-phone'],
                    ['url' => \App\Filament\Pages\SupportHub::getUrl(['tab' => 'tools']), 'label' => 'Tools', 'icon' => 'heroicon-o-wrench-screwdriver', 'active' => true],
                ] as $link)
                    @if (filled($link['url'] ?? null))
                        <a href="{{ $link['url'] }}" @class(['sh-dock__link', 'sh-dock__link--active' => ! empty($link['active'])])>
                            <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
