<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-subscribers',
        'fi-resource-record-' . $record->getKey(),
        'isp-subscriber-record-page',
        'isp-sub-view-page',
    ])
>
    @php
        $details = $this->getClientDetails();
        $h = $details['header'];
        $cc = $details['command_center'] ?? [];
        $strip = $cc['header_strip'] ?? [];
        $optical = $details['optical'];
        $sections = $details['sections'];
        $overview = $details['sections_overview'];
        $moreKeys = ['onu_billing', 'fees', 'installation', 'notifications', 'automation', 'tags', 'kyc', 'system'];
        if (($sections['legacy_meta'] ?? []) !== []) {
            $moreKeys[] = 'legacy_meta';
        }
        $tabItems = [
            ['key' => 'overview', 'label' => 'Overview', 'icon' => 'heroicon-o-squares-2x2'],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => 'heroicon-o-banknotes'],
            ['key' => 'network', 'label' => 'Network', 'icon' => 'heroicon-o-signal'],
            ['key' => 'more', 'label' => 'More', 'icon' => 'heroicon-o-ellipsis-horizontal-circle'],
        ];
        $quickLinks = [
            ['label' => 'Collect payment', 'url' => $details['urls']['collect'], 'icon' => 'heroicon-o-banknotes', 'btn' => 'white'],
            ['label' => 'Edit profile', 'url' => $details['urls']['edit'], 'icon' => 'heroicon-o-pencil-square', 'btn' => 'glass'],
            ['label' => 'Invoices', 'url' => $details['urls']['invoices'], 'icon' => 'heroicon-o-document-text', 'btn' => 'glass'],
            ['label' => 'New ticket', 'url' => $cc['tickets']['create_url'] ?? '#', 'icon' => 'heroicon-o-lifebuoy', 'btn' => 'glass'],
        ];
        $networkPath = $cc['network_path'] ?? [];
        $officeAccess = $networkPath['office_access'] ?? [];
        if (! empty($officeAccess['online']) && ! empty($officeAccess['wan_admin_url'])) {
            $quickLinks[] = [
                'label' => 'WAN router',
                'url' => $officeAccess['wan_admin_url'],
                'icon' => 'heroicon-o-globe-alt',
                'btn' => 'glass',
                'external' => true,
                'class' => 'sub-hero-wan-link',
            ];
        }
        $onuOwnership = $details['connection_link']['onu_ownership'] ?? 'company';
        $onuOwnershipLabel = $details['connection_link']['onu_ownership_label'] ?? 'Company ONU';
    @endphp

    {!! \App\Support\SubscriberViewStyles::navigatedScriptWithOlt() !!}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" data-cfasync="false"></script>
    <script src="{{ asset('js/subscriber-view-tabs.js') }}?v={{ @filemtime(public_path('js/subscriber-view-tabs.js')) ?: 1 }}" data-cfasync="false" defer></script>
    <script src="{{ asset('js/subscriber-view-location-map.js') }}?v={{ @filemtime(public_path('js/subscriber-view-location-map.js')) ?: 1 }}" data-cfasync="false" defer></script>
    <script src="{{ asset('js/subscriber-view-charts.js') }}?v={{ @filemtime(public_path('js/subscriber-view-charts.js')) ?: 1 }}" data-cfasync="false" defer></script>

    <div class="sub-pro olt-pro" wire:key="client-view-{{ $record->getKey() }}-{{ \App\Support\SubscriberViewStyles::version() }}" x-data="{ tab: 'overview' }" data-sub-tabs-root data-initial-tab="overview">
        <div class="sub-hero-wrap">
        <header class="olt-hero sub-hero">
            <div class="olt-hero__grid">
                <span class="olt-hero__badge">
                    <span @class([
                        'olt-hero__badge-dot',
                        'olt-hero__badge-dot--offline' => ! $h['online'],
                    ]) aria-hidden="true"></span>
                    Client command · {{ $h['online'] ? 'PPPoE online' : 'PPPoE offline' }}
                </span>
                <h1 class="olt-hero__title">{{ $h['client_name'] }}</h1>
                <p class="olt-hero__sub">
                    <span class="font-mono font-semibold">{{ $h['client_code'] }}</span>
                    @if ($h['phone'] !== '—')
                        · <a href="tel:{{ preg_replace('/\D+/', '', $h['phone']) }}" class="underline decoration-white/30 hover:decoration-white">{{ $h['phone'] }}</a>
                    @endif
                    · PPPoE <span class="font-mono">{{ $h['username'] }}</span>
                    · Joined <span class="font-semibold">{{ $h['activation_date'] }}</span>
                </p>
                <div class="sub-hero__pills">
                    @if (! empty($strip['is_vip']))
                        <span class="sub-pill sub-pill--warning">VIP</span>
                    @endif
                    @if (! empty($strip['is_corporate']))
                        <span class="sub-pill sub-pill--info">Corporate</span>
                    @endif
                    <span class="sub-pill sub-pill--{{ $h['status_color'] }}">{{ $h['status'] }}</span>
                    <span class="sub-pill sub-pill--{{ $h['subscriber_type_color'] }}">{{ $h['subscriber_type'] }}</span>
                    <span @class(['sub-pill', $h['online'] ? 'sub-pill--online' : 'sub-pill--offline'])>{{ $h['online'] ? 'Online' : 'Offline' }}</span>
                    <span @class(['sub-pill', $h['network'] === 'suspended' ? 'sub-pill--danger' : 'sub-pill--gray'])>Net {{ $h['network'] }}</span>
                    <span @class(['sub-pill', $onuOwnership === 'customer' ? 'sub-pill--info' : 'sub-pill--success'])>{{ $onuOwnershipLabel }}</span>
                    @if (($strip['open_tickets'] ?? 0) > 0)
                        <span class="sub-pill sub-pill--warning">{{ $strip['open_tickets'] }} ticket(s)</span>
                    @endif
                </div>
                <div class="olt-hero__actions no-print">
                    @foreach ($quickLinks as $link)
                        <a
                            href="{{ $link['url'] }}"
                            @class(array_filter(['olt-btn', 'olt-btn--' . $link['btn'], $link['class'] ?? null]))
                            @if (! empty($link['external'])) target="_blank" rel="noopener" @endif
                        >
                            <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="olt-hero__live">
                <div class="olt-hero__live-card">
                    @if ($h['online'])
                        <span class="olt-hero__live-label">Live session</span>
                        <strong class="olt-hero__live-value">{{ $h['connection_duration'] !== '—' ? $h['connection_duration'] : 'Online' }}</strong>
                        <span class="olt-hero__live-hint">{{ $h['package'] }} · {{ $h['speed'] }}</span>
                    @else
                        <span class="olt-hero__live-label">Open balance</span>
                        <strong class="olt-hero__live-value">{{ number_format($h['open_balance'], 0) }} BDT</strong>
                        <span class="olt-hero__live-hint">
                            @if ($h['expired'])
                                Expired · valid until {{ $h['valid_until'] }}
                            @else
                                Valid until {{ $h['valid_until'] }}
                            @endif
                        </span>
                    @endif
                </div>
            </div>
        </header>
        </div>

        @include('filament.resources.customer-resource.partials.subscriber-command-center-head', ['commandCenter' => $cc])

        @php $onuWarning = ($details['onu_ops']['warning'] ?? ['active' => false]); @endphp
        @if (! empty($onuWarning['active']))
            <div @class([
                'sub-onu-profile-alert no-print',
                'sub-onu-profile-alert--' . ($onuWarning['level'] ?? 'warning'),
            ]) role="alert">
                <strong>ONU warning</strong>
                <span>{{ $onuWarning['message'] ?? 'ONU requires attention.' }}</span>
                @if (! empty($onuWarning['suggest_ticket']) && ! empty($details['onu_ops']['ticket_suggest_url']))
                    <a href="{{ $details['onu_ops']['ticket_suggest_url'] }}" class="sub-onu-profile-alert__link">Open ticket →</a>
                @endif
            </div>
        @endif

        <div class="sub-rail sub-rail--dates">
            <div class="sub-rail__item">
                <span class="sub-rail__label">Joining date</span>
                <strong class="sub-rail__value">{{ $h['activation_date'] }}</strong>
            </div>
            <div class="sub-rail__item">
                <span class="sub-rail__label">Valid until</span>
                <strong class="sub-rail__value">{{ $h['valid_until'] }}</strong>
            </div>
            <div class="sub-rail__item">
                <span class="sub-rail__label">Line off date</span>
                <strong class="sub-rail__value">{{ $h['off_date'] }}</strong>
            </div>
            <div class="sub-rail__item">
                <span class="sub-rail__label">Portal logout</span>
                <strong class="sub-rail__value">{{ $h['portal_last_logout'] }}</strong>
            </div>
        </div>

        <nav class="sub-quickbar no-print" aria-label="Quick actions">
            <a href="{{ $details['urls']['collect'] }}" class="sub-quickbar__btn sub-quickbar__btn--primary">
                <x-filament::icon icon="heroicon-o-banknotes" class="h-4 w-4" />
                Collect
            </a>
            <a href="{{ $details['urls']['portal_login'] }}" class="sub-quickbar__btn" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-arrow-right-on-rectangle" class="h-4 w-4" />
                Portal
            </a>
            @if (! empty($officeAccess['online']) && ! empty($officeAccess['wan_admin_url']))
                <a href="{{ $officeAccess['wan_admin_url'] }}" class="sub-quickbar__btn sub-quickbar__btn--wan" target="_blank" rel="noopener">
                    <x-filament::icon icon="heroicon-o-globe-alt" class="h-4 w-4" />
                    WAN
                </a>
            @endif
            @if (! empty($networkPath['links']['billing_router_portal']))
                <a href="{{ $networkPath['links']['billing_router_portal'] }}" class="sub-quickbar__btn" target="_blank" rel="noopener">
                    <x-filament::icon icon="heroicon-o-home" class="h-4 w-4" />
                    /router
                </a>
            @endif
            <button type="button" class="sub-quickbar__btn" wire:click="extendDaysLive(5)" wire:loading.attr="disabled">
                <x-filament::icon icon="heroicon-o-calendar" class="h-4 w-4" />
                +5d
            </button>
            <button type="button" class="sub-quickbar__btn" wire:click="extendDaysLive(30)" wire:loading.attr="disabled">
                <x-filament::icon icon="heroicon-o-calendar" class="h-4 w-4" />
                +30d
            </button>
            <button type="button" class="sub-quickbar__btn" wire:click="toggleNetworkAccess" wire:loading.attr="disabled">
                <x-filament::icon icon="heroicon-o-signal-slash" class="h-4 w-4" />
                {{ ($h['network'] ?? 'active') === 'suspended' ? 'Net ON' : 'Net OFF' }}
            </button>
            <a href="{{ $details['urls']['edit'] }}" class="sub-quickbar__btn">
                <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                Edit
            </a>
        </nav>

        @include('filament.resources.customer-resource.partials.subscriber-router-access-strip', [
            'networkPath' => $cc['network_path'] ?? [],
            'portalLoginUrl' => $details['urls']['portal_login'] ?? '#',
        ])

        <nav class="sub-tabs no-print" role="tablist" aria-label="Client sections">
            @foreach ($tabItems as $item)
                <button
                    type="button"
                    @click="tab = '{{ $item['key'] }}'"
                    data-sub-tab="{{ $item['key'] }}"
                    aria-selected="{{ $item['key'] === 'overview' ? 'true' : 'false' }}"
                    :class="tab === '{{ $item['key'] }}' && 'sub-tabs__btn--active'"
                    @class(['sub-tabs__btn', 'sub-tabs__btn--active' => $item['key'] === 'overview'])
                >
                    <x-filament::icon :icon="$item['icon']" class="h-4 w-4" />
                    <span>{{ $item['label'] }}</span>
                </button>
            @endforeach
        </nav>

        <div x-show="tab === 'overview'" x-cloak class="sub-pane" data-sub-pane="overview">
            @include('filament.resources.customer-resource.partials.subscriber-command-center-panels', ['commandCenter' => $cc])
            @include('filament.resources.customer-resource.partials.client-details-overview', [
                'sections' => $overview,
                'connectionLink' => $details['connection_link'] ?? [],
                'contact' => $details['contact'] ?? [],
                'address' => $record->address,
                'clientName' => $h['client_name'],
                'editUrl' => $details['urls']['edit'] ?? null,
                'optical' => $optical,
                'urls' => $details['urls'],
                'notes' => filled($record->notes) ? $record->notes : null,
            ])
            @if ($details['recent_payments']->isNotEmpty() || $details['recent_invoices']->isNotEmpty())
                <div class="isp-cv-recent">
                    @if ($details['recent_payments']->isNotEmpty())
                        <section class="isp-cv-card">
                            <div class="isp-cv-card__head">
                                <h3 class="isp-cv-card__title">Recent payments</h3>
                                <button type="button" class="isp-cv-link" @click="tab = 'billing'">All →</button>
                            </div>
                            <div class="isp-cv-table-wrap">
                            <table class="isp-cv-table">
                                <tbody>
                                    @foreach ($details['recent_payments']->take(3) as $pay)
                                        <tr>
                                            <td data-label="Date">{{ $pay->paid_at?->format('d M Y') }}</td>
                                            <td data-label="Method">{{ ucfirst((string) $pay->method) }}</td>
                                            <td class="text-right font-mono font-semibold" data-label="Amount">{{ number_format((float) $pay->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </section>
                    @endif
                    @if ($details['recent_invoices']->isNotEmpty())
                        <section class="isp-cv-card">
                            <div class="isp-cv-card__head">
                                <h3 class="isp-cv-card__title">Recent invoices</h3>
                                <button type="button" class="isp-cv-link" @click="tab = 'billing'">All →</button>
                            </div>
                            <div class="isp-cv-table-wrap">
                            <table class="isp-cv-table">
                                <tbody>
                                    @foreach ($details['recent_invoices']->take(3) as $inv)
                                        <tr>
                                            <td class="font-mono text-xs" data-label="Invoice">#{{ $inv->id }}</td>
                                            <td data-label="Issue">{{ $inv->issue_date?->format('d M Y') }}</td>
                                            <td class="text-right font-mono" data-label="Total">{{ number_format((float) $inv->total, 2) }}</td>
                                            <td data-label="Status"><span class="isp-cv-pill isp-cv-pill--muted text-xs">{{ ucfirst((string) $inv->status) }}</span></td>
                                            <td class="text-right" data-label="Actions">
                                                @include('filament.resources.customer-resource.partials.client-invoice-actions', ['invoice' => $inv])
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </section>
                    @endif
                </div>
            @endif
        </div>

        <div x-show="tab === 'billing'" x-cloak class="sub-pane" data-sub-pane="billing" hidden>
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
                                            <td data-label="Date">{{ $pay->paid_at?->format('d M Y H:i') }}</td>
                                            <td data-label="By">{{ $pay->recorder?->name ?? 'Online' }}</td>
                                            <td data-label="Method">{{ ucfirst((string) $pay->method) }}</td>
                                            <td class="text-right font-mono font-semibold" data-label="Amount">{{ number_format((float) $pay->amount, 2) }}</td>
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
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($details['recent_invoices'] as $inv)
                                        <tr>
                                            <td class="font-mono text-xs" data-label="Invoice">{{ $inv->id }}</td>
                                            <td data-label="Issue">{{ $inv->issue_date?->format('d M Y') }}</td>
                                            <td data-label="Due">{{ $inv->due_date?->format('d M Y') }}</td>
                                            <td class="text-right font-mono" data-label="Total">{{ number_format((float) $inv->total, 2) }}</td>
                                            <td data-label="Status">{{ ucfirst((string) $inv->status) }}</td>
                                            <td class="text-right" data-label="Actions">
                                                @include('filament.resources.customer-resource.partials.client-invoice-actions', ['invoice' => $inv])
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        </div>

        <div x-show="tab === 'network'" x-cloak class="sub-pane" data-sub-pane="network" hidden>
            @include('filament.resources.customer-resource.partials.subscriber-network-path-panel', [
                'networkPath' => $cc['network_path'] ?? [],
            ])
            @include('filament.resources.customer-resource.partials.subscriber-onu-ops-panel', [
                'onuOps' => $details['onu_ops'] ?? [],
            ])
            @include('filament.resources.customer-resource.partials.onu-network-diagnostics-cards', [
                'diagnostics' => $details['network_diagnostics'] ?? [],
            ])
            @include('filament.resources.customer-resource.partials.client-fiber-path', ['customer' => $record])
            <section class="isp-cv-card isp-cv-card--full" wire:poll.60s wire:key="onu-optical-{{ $record->getKey() }}-v2">
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
                @livewire(\App\Filament\Resources\CustomerResource\Widgets\SubscriberLiveTrafficWidget::class, ['record' => $record, 'embedded' => true], key('traffic-'.$record->getKey()))
            </section>
        </div>

        <div x-show="tab === 'more'" x-cloak class="sub-pane" data-sub-pane="more" hidden>
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
            <p class="isp-cv-muted text-sm mb-2">Billing, install, automation &amp; system fields (Edit client to change).</p>
            @include('filament.resources.customer-resource.partials.client-details-sections', [
                'sections' => $sections,
                'keys' => $moreKeys,
                'compact' => true,
            ])
        </div>

        <nav class="olt-dock sub-dock-mobile no-print" aria-label="Quick actions">
            <div class="olt-dock__inner">
                <a href="{{ $details['urls']['collect'] }}" class="olt-dock__link olt-dock__link--active">
                    <x-filament::icon icon="heroicon-o-banknotes" />
                    <span>Collect</span>
                </a>
                <a href="{{ $details['urls']['portal_login'] }}" class="olt-dock__link" target="_blank" rel="noopener">
                    <x-filament::icon icon="heroicon-o-arrow-right-on-rectangle" />
                    <span>Portal</span>
                </a>
                <button type="button" class="olt-dock__link border-0 bg-transparent" wire:click="extendDaysLive(5)" wire:loading.attr="disabled">
                    <x-filament::icon icon="heroicon-o-calendar" />
                    <span>+5d</span>
                </button>
                <button type="button" class="olt-dock__link border-0 bg-transparent" wire:click="extendDaysLive(30)" wire:loading.attr="disabled">
                    <x-filament::icon icon="heroicon-o-calendar" />
                    <span>+30d</span>
                </button>
                <button type="button" class="olt-dock__link border-0 bg-transparent" wire:click="toggleNetworkAccess" wire:loading.attr="disabled">
                    <x-filament::icon icon="heroicon-o-signal-slash" />
                    <span>{{ ($h['network'] ?? 'active') === 'suspended' ? 'Net ON' : 'Net OFF' }}</span>
                </button>
                <a href="{{ $details['urls']['edit'] }}" class="olt-dock__link">
                    <x-filament::icon icon="heroicon-o-pencil-square" />
                    <span>Edit</span>
                </a>
            </div>
        </nav>
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
