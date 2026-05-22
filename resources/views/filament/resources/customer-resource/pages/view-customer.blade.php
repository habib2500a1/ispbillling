<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-subscribers',
        'fi-resource-record-' . $record->getKey(),
        'isp-client-details-page',
        'isp-cv-page',
    ])
>
    @php
        $details = $this->getClientDetails();
        $h = $details['header'];
        $optical = $details['optical'];
        $sections = $details['sections'];
        $overview = $details['sections_overview'];
        $moreKeys = ['fees', 'installation', 'staff', 'onu_billing', 'notifications', 'automation', 'tags', 'kyc', 'system'];
        if (($sections['legacy_meta'] ?? []) !== []) {
            $moreKeys[] = 'legacy_meta';
        }
    @endphp

    <style>
        [x-cloak] { display: none !important; }
        .isp-cv-page .fi-header-heading,
        .isp-cv-page .fi-header-subheading { display: none !important; }
        .isp-cv-page .fi-page-header-actions {
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-bottom: 0.5rem;
        }
        @media (max-width: 1023px) {
            .isp-cv-hero { flex-direction: column; }
            .isp-cv-hero__actions { width: 100%; }
            .isp-cv-hero__actions .isp-cv-btn { flex: 1; justify-content: center; text-align: center; }
            .isp-cv-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .isp-cv-overview { grid-template-columns: 1fr; }
            .isp-cv-split { grid-template-columns: 1fr; }
            .isp-cv-recent { grid-template-columns: 1fr; }
            .isp-cv-tabs { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; }
            .isp-cv-tabs__btn { flex-shrink: 0; white-space: nowrap; }
            .isp-cv-mobile-bar {
                display: flex;
                position: sticky;
                bottom: 0;
                z-index: 20;
                gap: 0.35rem;
                padding: 0.5rem;
                margin: 0 -0.5rem;
                background: var(--isp-card-bg, #fff);
                border-top: 1px solid var(--isp-card-border, #e5e7eb);
                box-shadow: 0 -4px 12px rgba(15, 23, 42, 0.08);
            }
            .dark .isp-cv-mobile-bar {
                background: rgb(17 24 39);
                border-color: rgb(55 65 81);
            }
            .isp-cv-mobile-bar__btn {
                flex: 1;
                padding: 0.55rem 0.4rem;
                border-radius: 0.5rem;
                font-size: 0.72rem;
                font-weight: 600;
                text-align: center;
                border: 1px solid #e2e8f0;
                background: #f8fafc;
                color: #0f172a;
            }
            .isp-cv-mobile-bar__btn--primary { background: #0d9488; color: #fff; border-color: #0d9488; }
        }
        @media (min-width: 1024px) {
            .isp-cv-mobile-bar { display: none !important; }
        }
    </style>

    <div class="isp-cv" wire:key="client-view-{{ $record->getKey() }}" x-data="{ tab: 'overview' }">
        <header class="isp-cv-hero">
            <div class="isp-cv-hero__main">
                <span class="isp-cv-avatar" aria-hidden="true">{{ $h['initial'] }}</span>
                <div class="isp-cv-hero__text">
                    <h1 class="isp-cv-hero__name">{{ $h['client_name'] }}</h1>
                    <p class="isp-cv-hero__meta">
                        <span class="font-mono">{{ $h['client_code'] }}</span>
                        @if ($h['phone'] !== '—')
                            · <a href="tel:{{ preg_replace('/\D+/', '', $h['phone']) }}" class="isp-cv-hero__phone">{{ $h['phone'] }}</a>
                        @endif
                        · <span class="font-mono text-xs opacity-80">{{ $h['username'] }}</span>
                    </p>
                    <div class="isp-cv-hero__badges">
                        <span class="isp-cv-pill isp-cv-pill--{{ $h['status_color'] }}">{{ $h['status'] }}</span>
                        <span class="isp-cv-pill isp-cv-pill--{{ $h['subscriber_type_color'] }}">{{ $h['subscriber_type'] }}</span>
                        <span class="isp-cv-pill {{ $h['online'] ? 'isp-cv-pill--online' : 'isp-cv-pill--offline' }}">
                            {{ $h['online'] ? 'Online' : 'Offline' }}
                        </span>
                        <span class="isp-cv-pill {{ $h['network'] === 'suspended' ? 'isp-cv-pill--danger' : 'isp-cv-pill--muted' }}">
                            Net {{ $h['network'] }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="isp-cv-hero__actions no-print">
                <a href="{{ $details['urls']['collect'] }}" class="isp-cv-btn isp-cv-btn--primary">Collect payment</a>
                <a href="{{ $details['urls']['edit'] }}" class="isp-cv-btn isp-cv-btn--soft">Edit</a>
                <a href="{{ $details['urls']['invoices'] }}" class="isp-cv-btn isp-cv-btn--ghost">Invoices</a>
            </div>
        </header>

        <div class="isp-cv-kpis">
            <div class="isp-cv-kpi {{ $h['open_balance'] > 0 ? 'isp-cv-kpi--warn' : '' }}">
                <span class="isp-cv-kpi__label">Open due</span>
                <strong class="isp-cv-kpi__value">{{ number_format($h['open_balance'], 2) }}</strong>
                <span class="isp-cv-kpi__unit">BDT</span>
            </div>
            <div class="isp-cv-kpi {{ $h['expired'] ? 'isp-cv-kpi--danger' : '' }}">
                <span class="isp-cv-kpi__label">Expires</span>
                <strong class="isp-cv-kpi__value">{{ $h['valid_until'] }}</strong>
            </div>
            <div class="isp-cv-kpi">
                <span class="isp-cv-kpi__label">Package</span>
                <strong class="isp-cv-kpi__value isp-cv-kpi__value--sm">{{ $h['package'] }}</strong>
                <span class="isp-cv-kpi__sub">{{ $h['speed'] }}</span>
            </div>
            <div class="isp-cv-kpi">
                <span class="isp-cv-kpi__label">Monthly</span>
                <strong class="isp-cv-kpi__value isp-cv-kpi__value--sm">{{ $h['monthly_bill'] }}</strong>
            </div>
            <div class="isp-cv-kpi">
                <span class="isp-cv-kpi__label">Wallet</span>
                <strong class="isp-cv-kpi__value">{{ number_format($h['balance'], 2) }}</strong>
                <span class="isp-cv-kpi__unit">BDT</span>
            </div>
            @if ($h['online'] && ! empty($h['connection_duration']))
            <div class="isp-cv-kpi isp-cv-kpi--ok">
                <span class="isp-cv-kpi__label">Uptime</span>
                <strong class="isp-cv-kpi__value isp-cv-kpi__value--sm">{{ $h['connection_duration'] }}</strong>
            </div>
            @elseif (! empty($h['last_disconnect']) && $h['last_disconnect'] !== '—')
            <div class="isp-cv-kpi">
                <span class="isp-cv-kpi__label">Last off</span>
                <strong class="isp-cv-kpi__value isp-cv-kpi__value--sm">{{ $h['last_disconnect'] }}</strong>
            </div>
            @endif
        </div>

        <nav class="isp-cv-tabs no-print" role="tablist">
            <button type="button" @click="tab = 'overview'" :class="tab === 'overview' && 'isp-cv-tabs__btn--active'" class="isp-cv-tabs__btn">Overview</button>
            <button type="button" @click="tab = 'billing'" :class="tab === 'billing' && 'isp-cv-tabs__btn--active'" class="isp-cv-tabs__btn">Billing</button>
            <button type="button" @click="tab = 'network'" :class="tab === 'network' && 'isp-cv-tabs__btn--active'" class="isp-cv-tabs__btn">Network</button>
            <button type="button" @click="tab = 'more'" :class="tab === 'more' && 'isp-cv-tabs__btn--active'" class="isp-cv-tabs__btn">More</button>
        </nav>

        <div x-show="tab === 'overview'" x-cloak class="isp-cv-pane">
            @include('filament.resources.customer-resource.partials.client-details-overview', [
                'sections' => $overview,
                'optical' => $optical,
                'urls' => $details['urls'],
            ])
            @if ($details['recent_payments']->isNotEmpty() || $details['recent_invoices']->isNotEmpty())
                <div class="isp-cv-recent">
                    @if ($details['recent_payments']->isNotEmpty())
                        <section class="isp-cv-card">
                            <div class="isp-cv-card__head">
                                <h3 class="isp-cv-card__title">Recent payments</h3>
                                <button type="button" class="isp-cv-link" @click="tab = 'billing'">All →</button>
                            </div>
                            <table class="isp-cv-table">
                                <tbody>
                                    @foreach ($details['recent_payments']->take(3) as $pay)
                                        <tr>
                                            <td>{{ $pay->paid_at?->format('d M Y') }}</td>
                                            <td>{{ ucfirst((string) $pay->method) }}</td>
                                            <td class="text-right font-mono font-semibold">{{ number_format((float) $pay->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @endif
                    @if ($details['recent_invoices']->isNotEmpty())
                        <section class="isp-cv-card">
                            <div class="isp-cv-card__head">
                                <h3 class="isp-cv-card__title">Recent invoices</h3>
                                <button type="button" class="isp-cv-link" @click="tab = 'billing'">All →</button>
                            </div>
                            <table class="isp-cv-table">
                                <tbody>
                                    @foreach ($details['recent_invoices']->take(3) as $inv)
                                        <tr>
                                            <td class="font-mono text-xs">#{{ $inv->id }}</td>
                                            <td>{{ $inv->issue_date?->format('d M Y') }}</td>
                                            <td class="text-right font-mono">{{ number_format((float) $inv->total, 2) }}</td>
                                            <td><span class="isp-cv-pill isp-cv-pill--muted text-xs">{{ ucfirst((string) $inv->status) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @endif
                </div>
            @endif
        </div>

        <div x-show="tab === 'billing'" x-cloak class="isp-cv-pane">
            <div class="isp-cv-split">
                <section class="isp-cv-card isp-cv-card--full">
                    <h3 class="isp-cv-card__title">Payments</h3>
                    @if ($details['recent_payments']->isEmpty())
                        <p class="isp-cv-muted text-sm">No payments yet.</p>
                    @else
                        <div class="isp-cv-table-wrap">
                            <table class="isp-cv-table isp-cv-table--head">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>By</th>
                                        <th>Method</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($details['recent_payments'] as $pay)
                                        <tr>
                                            <td>{{ $pay->paid_at?->format('d M Y H:i') }}</td>
                                            <td>{{ $pay->recorder?->name ?? 'Online' }}</td>
                                            <td>{{ ucfirst((string) $pay->method) }}</td>
                                            <td class="text-right font-mono font-semibold">{{ number_format((float) $pay->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
                <section class="isp-cv-card isp-cv-card--full">
                    <h3 class="isp-cv-card__title">Invoices</h3>
                    @if ($details['recent_invoices']->isEmpty())
                        <p class="isp-cv-muted text-sm">No invoices.</p>
                    @else
                        <div class="isp-cv-table-wrap">
                            <table class="isp-cv-table isp-cv-table--head">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Issue</th>
                                        <th>Due</th>
                                        <th class="text-right">Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($details['recent_invoices'] as $inv)
                                        <tr>
                                            <td class="font-mono text-xs">{{ $inv->id }}</td>
                                            <td>{{ $inv->issue_date?->format('d M Y') }}</td>
                                            <td>{{ $inv->due_date?->format('d M Y') }}</td>
                                            <td class="text-right font-mono">{{ number_format((float) $inv->total, 2) }}</td>
                                            <td>{{ ucfirst((string) $inv->status) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        </div>

        <div x-show="tab === 'network'" x-cloak class="isp-cv-pane">
            <section class="isp-cv-card isp-cv-card--full" wire:poll.60s>
                <h3 class="isp-cv-card__title">ONU / Optical</h3>
                @include('filament.resources.customer-resource.partials.client-details-onu-table', [
                    'snapshot' => $optical,
                    'optical_noc_url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(),
                    'laser_settings_url' => \App\Filament\Pages\ManageOpticalLaserSettings::canAccess()
                        ? \App\Filament\Pages\ManageOpticalLaserSettings::getUrl()
                        : null,
                ])
            </section>
            <section class="isp-cv-card isp-cv-card--full">
                <h3 class="isp-cv-card__title">Live traffic</h3>
                @livewire(\App\Filament\Resources\CustomerResource\Widgets\SubscriberLiveTrafficWidget::class, ['record' => $record], key('traffic-'.$record->getKey()))
            </section>
        </div>

        <div x-show="tab === 'more'" x-cloak class="isp-cv-pane">
            <div class="isp-cv-more-grid">
                <section class="isp-cv-card">
                    <h3 class="isp-cv-card__title">Contacts</h3>
                    <p class="text-sm mb-2">Primary: <strong class="font-mono">{{ $details['customer']->phone }}</strong></p>
                    @forelse ($details['contacts'] as $c)
                        <p class="text-sm"><span class="isp-cv-muted">{{ $c['label'] }}:</span> <span class="font-mono">{{ $c['phone'] }}</span></p>
                    @empty
                        <p class="isp-cv-muted text-sm">No extra contacts.</p>
                    @endforelse
                </section>
                <section class="isp-cv-card isp-cv-card--full">
                    <h3 class="isp-cv-card__title">SMS log</h3>
                    @include('filament.resources.customer-resource.partials.client-details-sms-log', [
                        'logs' => $details['recent_sms_logs']->take(20),
                        'stats' => $details['sms_stats'],
                        'eventLabels' => $details['sms_event_labels'],
                        'fullLogUrl' => $details['urls']['sms_log'] ?? null,
                    ])
                </section>
            </div>
            <p class="isp-cv-muted text-sm mb-2">Extra billing, install, automation &amp; system fields (Edit client for changes).</p>
            @include('filament.resources.customer-resource.partials.client-details-sections', [
                'sections' => $sections,
                'keys' => $moreKeys,
                'compact' => true,
            ])
        </div>

        <div class="isp-cv-mobile-bar no-print lg:hidden">
            <a href="{{ $details['urls']['collect'] }}" class="isp-cv-mobile-bar__btn isp-cv-mobile-bar__btn--primary">Collect</a>
            <button type="button" class="isp-cv-mobile-bar__btn" wire:click="extendThirtyDays" wire:loading.attr="disabled">Extend 30d</button>
            <button type="button" class="isp-cv-mobile-bar__btn" wire:click="toggleNetworkAccess" wire:loading.attr="disabled">
                {{ ($h['network'] ?? 'active') === 'suspended' ? 'Net ON' : 'Net OFF' }}
            </button>
            <a href="{{ $details['urls']['edit'] }}" class="isp-cv-mobile-bar__btn">Edit</a>
        </div>
    </div>

    @php $relationManagers = $this->getRelationManagers(); @endphp
    @if (count($relationManagers))
        <div class="isp-cv-records no-print" x-data="{ open: false }">
            <button type="button" class="isp-cv-records__toggle" @click="open = !open">
                <span x-text="open ? 'Hide related records' : 'Show related records (contacts, devices, tickets…)'"></span>
                <svg class="h-4 w-4 transition" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div x-show="open" x-collapse class="isp-cv-records__body">
                <x-filament-panels::resources.relation-managers
                    :active-locale="isset($activeLocale) ? $activeLocale : null"
                    :active-manager="$this->activeRelationManager ?? array_key_first($relationManagers)"
                    :content-tab-label="$this->getContentTabLabel()"
                    :content-tab-icon="$this->getContentTabIcon()"
                    :content-tab-position="$this->getContentTabPosition()"
                    :managers="$relationManagers"
                    :owner-record="$record"
                    :page-class="static::class"
                />
            </div>
        </div>
    @endif
</x-filament-panels::page>
