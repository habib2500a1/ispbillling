@php
    /** @var \App\Models\SupportTicket $record */
    $record = $this->record;
    $workspace = $this->getTicketWorkspaceViewData();
    $c360 = $workspace['c360'];
    $timeline = $workspace['timeline'];
    $hints = $workspace['hints'];
    $gis = $workspace['gis'];
    $network = $workspace['network'];
    $live = $workspace['live'];
    $closeOfflineNotice = $workspace['close_offline_notice'] ?? null;
    $hubUrl = \App\Filament\Pages\SupportHub::getUrl();
    $listUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
@endphp

{!! \App\Support\SupportStyles::html() !!}
{!! \App\Support\SupportStyles::navigatedScript() !!}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>
<script src="{{ asset('js/support-ticket-map.js') }}?v={{ @filemtime(public_path('js/support-ticket-map.js')) ?: 1 }}" defer></script>

<x-filament-panels::page class="isp-support-ticket-edit">
    <div class="sp-pro">
        <header class="sp-ticket-hero">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:0.5rem;align-items:flex-start;">
                <div>
                    <span class="sp-ticket-hero__number">{{ $record->ticket_number }}</span>
                    <h1 class="sp-ticket-hero__subject">{{ $record->subject }}</h1>
                    <p style="margin:0.35rem 0 0;font-size:0.72rem;color:var(--sp-muted);">
                        {{ \App\Models\SupportTicket::DEPARTMENTS[$record->department] ?? $record->department }}
                        · {{ \App\Models\SupportTicket::PRIORITIES[$record->priority] ?? $record->priority }}
                        · {{ \App\Models\SupportTicket::STATUSES[$record->status] ?? $record->status }}
                        · SLA {{ $record->slaRemainingLabel() }}
                    </p>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                    <a href="{{ $listUrl }}" class="sp-360__link">← Queue</a>
                    <a href="{{ $hubUrl }}" class="sp-360__link">Center</a>
                </div>
            </div>
        </header>

        @if (! empty($c360['linked']))
            <section
                wire:key="sp-live-status-{{ $c360['id'] ?? 'none' }}"
                @class([
                    'sp-live-status',
                    'sp-live-status--ok' => $live['ppp_online'] && ($live['onu_online'] !== false),
                    'sp-live-status--warn' => ! $live['ppp_online'] || $live['onu_online'] === false,
                ])
                aria-label="Live subscriber status"
            >
                <div class="sp-live-status__grid">
                    <div class="sp-live-status__item">
                        <span class="sp-live-status__label">PPP / Internet</span>
                        <span class="sp-live-status__badge @if ($live['ppp_online']) sp-live-status__badge--online @else sp-live-status__badge--offline @endif">
                            {{ $live['ppp_online'] ? 'Online' : 'Offline' }}
                        </span>
                        @if (! $live['ppp_online'] && filled($live['ppp_offline_reason']))
                            <span class="sp-live-status__reason">{{ $live['ppp_offline_reason'] }}</span>
                        @endif
                    </div>
                    <div class="sp-live-status__item">
                        <span class="sp-live-status__label">ONU</span>
                        @if ($live['onu_online'] === null)
                            <span class="sp-live-status__badge sp-live-status__badge--muted">Not mapped</span>
                        @else
                            <span class="sp-live-status__badge @if ($live['onu_online']) sp-live-status__badge--online @else sp-live-status__badge--offline @endif">
                                {{ $live['onu_online'] ? 'Online' : 'Offline' }}
                            </span>
                            @if (! $live['onu_online'])
                                <span class="sp-live-status__reason">
                                    {{ $live['onu_offline_reason'] ?? ('Status: '.($live['onu_status'] ?? 'unknown')) }}
                                </span>
                            @endif
                        @endif
                        @if (filled($live['onu_last_polled']))
                            <span class="sp-live-status__meta">Polled {{ $live['onu_last_polled'] }}</span>
                        @endif
                    </div>
                    <div class="sp-live-status__item">
                        <span class="sp-live-status__label">Last logout / seen</span>
                        <span class="sp-live-status__time">{{ $live['last_logout_at'] ?? '—' }}</span>
                        <span class="sp-live-status__meta">{{ $live['last_logout_ago'] ?? '' }}</span>
                    </div>
                </div>
                @if (filled($closeOfflineNotice))
                    <p class="sp-live-status__block sp-live-status__block--info">{{ $closeOfflineNotice }}</p>
                @endif
            </section>
        @endif

        <div class="sp-workspace">
            <aside class="sp-workspace__rail sp-workspace__rail--left">
                <section class="sp-panel" aria-label="Customer 360">
                    <h2 class="sp-panel__title">Customer 360</h2>
                    @if (empty($c360['linked']))
                        <p style="font-size:0.75rem;color:var(--sp-muted);margin:0;">No subscriber linked.</p>
                    @else
                        <p class="sp-360__name">{{ $c360['name'] }}</p>
                        <p class="sp-360__code">#{{ $c360['code'] }}</p>
                        <div class="sp-360__row">
                            <span class="sp-360__label">Status</span>
                            <span class="sp-360__value">{{ $c360['status'] }}</span>
                        </div>
                        <div class="sp-360__row">
                            <span class="sp-360__label">Package</span>
                            <span class="sp-360__value">{{ $c360['package'] }}</span>
                        </div>
                        <div class="sp-360__row">
                            <span class="sp-360__label">Billing due</span>
                            <span class="sp-360__value">{{ $c360['billing_due_fmt'] }}</span>
                        </div>
                        <div class="sp-360__row">
                            <span class="sp-360__label">PPPoE</span>
                            <span class="sp-360__value">
                                <span @class(['sp-status-dot', $c360['ppp_online'] ? 'sp-status-dot--online' : 'sp-status-dot--offline'])></span>
                                {{ $c360['ppp_online'] ? 'Online' : 'Offline' }}
                            </span>
                        </div>
                        @if (! $c360['ppp_online'] && filled($c360['ppp_offline_reason'] ?? null))
                            <div class="sp-360__row">
                                <span class="sp-360__label">PPP reason</span>
                                <span class="sp-360__value" style="font-size:0.62rem;">{{ $c360['ppp_offline_reason'] }}</span>
                            </div>
                            <div class="sp-360__row">
                                <span class="sp-360__label">Last logout</span>
                                <span class="sp-360__value" style="font-size:0.62rem;">{{ $c360['last_logout_at'] ?? '—' }}</span>
                            </div>
                        @endif
                        <div class="sp-360__row">
                            <span class="sp-360__label">Tickets</span>
                            <span class="sp-360__value">{{ $c360['ticket_count'] }} total</span>
                        </div>
                        <div class="sp-360__row">
                            <span class="sp-360__label">Last payment</span>
                            <span class="sp-360__value" style="font-size:0.65rem;">{{ $c360['last_payment'] }}</span>
                        </div>
                        <div class="sp-360__links">
                            <a href="{{ $c360['urls']['profile'] }}" class="sp-360__link">Profile</a>
                            <a href="{{ $c360['urls']['collect'] }}" class="sp-360__link sp-360__link--primary">Collect</a>
                            <a href="{{ $c360['urls']['invoices'] }}" class="sp-360__link">Invoices</a>
                        </div>
                    @endif
                </section>

                @if (! empty($c360['onu']))
                    <section class="sp-panel">
                        <h2 class="sp-panel__title">ONU / OLT</h2>
                        <div class="sp-360__row">
                            <span class="sp-360__label">ONU</span>
                            <span class="sp-360__value">
                                <span @class(['sp-status-dot', ($c360['onu']['online'] ?? false) ? 'sp-status-dot--online' : 'sp-status-dot--offline'])></span>
                                {{ ($c360['onu']['online'] ?? false) ? 'Online' : 'Offline' }}
                            </span>
                        </div>
                        <div class="sp-360__row"><span class="sp-360__label">Serial</span><span class="sp-360__value">{{ $c360['onu']['serial'] }}</span></div>
                        @if (! ($c360['onu']['online'] ?? false) && filled($c360['onu']['offline_reason'] ?? null))
                            <div class="sp-360__row"><span class="sp-360__label">ONU reason</span><span class="sp-360__value" style="font-size:0.62rem;">{{ $c360['onu']['offline_reason'] }}</span></div>
                        @endif
                        <div class="sp-360__row"><span class="sp-360__label">Signal</span><span class="sp-360__value">{{ $c360['onu']['rx_dbm'] !== null ? $c360['onu']['rx_dbm'].' dBm' : '—' }}</span></div>
                        <div class="sp-360__row"><span class="sp-360__label">OLT</span><span class="sp-360__value">{{ $c360['onu']['olt'] }}</span></div>
                        <div class="sp-360__row"><span class="sp-360__label">PON</span><span class="sp-360__value">{{ $c360['onu']['pon'] }}</span></div>
                    </section>
                @endif
            </aside>

            <div class="sp-workspace__main">
                <x-filament-panels::form wire:submit="save">
                    {{ $this->form }}
                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </x-filament-panels::form>

                @php $relationManagers = $this->getRelationManagers(); @endphp
                @if (count($relationManagers))
                    <div style="margin-top:1rem;">
                        <x-filament-panels::resources.relation-managers
                            :active-manager="$this->activeRelationManager ?? array_key_first($relationManagers)"
                            :managers="$relationManagers"
                            :owner-record="$record"
                            :page-class="static::class"
                        />
                    </div>
                @endif

                <section class="sp-timeline" aria-label="Ticket timeline">
                    <h2 class="sp-panel__title">Timeline</h2>
                    <ol class="sp-timeline__list">
                        @foreach ($timeline as $event)
                            <li @class(['sp-timeline__item', 'sp-timeline__item--' . ($event['tone'] ?? 'default')])>
                                <span class="sp-timeline__dot" aria-hidden="true"></span>
                                <div class="sp-timeline__label">{{ $event['label'] }}</div>
                                <div class="sp-timeline__time">{{ $event['at'] ? \Illuminate\Support\Carbon::parse($event['at'])->format('M j, H:i') : '' }}</div>
                                <div class="sp-timeline__detail">{{ $event['detail'] }}</div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="sp-workspace__rail sp-workspace__rail--right">
                @if ($hints !== [])
                    <section class="sp-panel" aria-label="Root cause assistant">
                        <h2 class="sp-panel__title">Likely causes</h2>
                        <p style="font-size:0.62rem;color:var(--sp-muted);margin:0 0 0.45rem;">Informational only — verify in field.</p>
                        @foreach ($hints as $hint)
                            <div class="sp-rca">
                                <div class="sp-rca__title">{{ $hint['title'] }}</div>
                                <div class="sp-rca__cause">{{ $hint['cause'] }}</div>
                                <div class="sp-rca__confidence">{{ $hint['confidence'] }} confidence</div>
                            </div>
                        @endforeach
                    </section>
                @endif

                <section class="sp-panel" aria-label="Network status">
                    <h2 class="sp-panel__title">Network rail</h2>
                    @if ($network === [])
                        <p style="font-size:0.72rem;color:var(--sp-muted);margin:0;">Link a subscriber for live context.</p>
                    @else
                        <div class="sp-360__row">
                            <span class="sp-360__label">PPP</span>
                            <span class="sp-360__value">
                                <span @class(['sp-status-dot', $network['ppp_online'] ? 'sp-status-dot--online' : 'sp-status-dot--offline'])></span>
                                {{ $network['ppp_online'] ? 'Online' : 'Offline' }}
                            </span>
                        </div>
                        @if (! $network['ppp_online'] && filled($network['ppp_offline_reason'] ?? null))
                            <div class="sp-360__row"><span class="sp-360__label">Reason</span><span class="sp-360__value" style="font-size:0.62rem;">{{ $network['ppp_offline_reason'] }}</span></div>
                            <div class="sp-360__row"><span class="sp-360__label">Last logout</span><span class="sp-360__value" style="font-size:0.62rem;">{{ $network['last_logout_at'] ?? '—' }}</span></div>
                        @endif
                        @if ($network['onu_online'] !== null)
                            <div class="sp-360__row">
                                <span class="sp-360__label">ONU</span>
                                <span class="sp-360__value">
                                    <span @class(['sp-status-dot', $network['onu_online'] ? 'sp-status-dot--online' : 'sp-status-dot--offline'])></span>
                                    {{ $network['onu_online'] ? 'Online' : 'Offline' }}
                                </span>
                            </div>
                        @endif
                        <div class="sp-360__row"><span class="sp-360__label">Router/NAS</span><span class="sp-360__value">{{ $network['router'] }}</span></div>
                        <div class="sp-360__row"><span class="sp-360__label">PPP user</span><span class="sp-360__value" style="font-size:0.65rem;">{{ $network['ppp_login'] }}</span></div>
                        <div class="sp-360__row"><span class="sp-360__label">Access</span><span class="sp-360__value">{{ $network['network_access'] }}</span></div>
                    @endif
                </section>

                <section class="sp-panel" aria-label="GIS preview">
                    <h2 class="sp-panel__title">Location &amp; GIS</h2>
                    @if (! empty($gis['available']))
                        <div
                            id="sp-ticket-mini-map"
                            class="sp-map-preview"
                            data-sp-mini-map
                            data-lat="{{ $gis['lat'] }}"
                            data-lng="{{ $gis['lng'] }}"
                        ></div>
                        @if ($gis['trace_found'])
                            <p class="sp-map-trace">Fiber trace ~{{ number_format($gis['trace_length_m']) }} m</p>
                        @endif
                        <div class="sp-map-actions">
                            @if ($gis['navigate_url'])
                                <a href="{{ $gis['navigate_url'] }}" target="_blank" rel="noopener">Navigate</a>
                            @endif
                            <a href="{{ $gis['map_url'] }}" target="_blank" rel="noopener">Full GIS map</a>
                        </div>
                    @else
                        <div class="sp-map-preview sp-map-preview--empty">
                            No GPS on file.<br>
                            <a href="{{ $gis['map_url'] ?? \App\Filament\Pages\FiberPlantMap::getUrl() }}" style="color:var(--sp-amber);font-weight:700;">Open network map</a>
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
