@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page class="isp-billing-hub-page">
    <div class="bh-pro">
        <header class="bh-hero">
            <div class="bh-hero__grid">
                <span class="bh-hero__badge">Revenue &amp; collections</span>
                <h1 class="bh-hero__title">Billing Center</h1>
                <p class="bh-hero__sub">
                    Auto invoices · pro-rata · VAT · coupons · grace &amp; late fees · dunning reminders — collect faster with desk &amp; field tools.
                </p>
                <div class="bh-hero__actions">
                    <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="bh-btn bh-btn--white">
                        <x-filament::icon icon="heroicon-m-currency-bangladeshi" class="h-4 w-4" />
                        Collection desk
                    </a>
                    <a href="{{ \App\Filament\Resources\InvoiceResource::getUrl('index') }}" class="bh-btn bh-btn--glass">
                        <x-filament::icon icon="heroicon-m-queue-list" class="h-4 w-4" />
                        All bills
                    </a>
                    <a href="{{ \App\Filament\Pages\DunningReport::getUrl() }}" class="bh-btn bh-btn--glass">
                        <x-filament::icon icon="heroicon-m-bell-alert" class="h-4 w-4" />
                        Dunning
                    </a>
                </div>
            </div>
            <div class="bh-hero__money">
                <div class="bh-hero__money-card">
                    <span class="bh-hero__money-label">Outstanding</span>
                    <strong class="bh-hero__money-value">{{ number_format((float) $stats['outstanding'], 0) }} BDT</strong>
                    <span class="bh-hero__money-hint">{{ number_format($stats['open']) }} open · {{ $stats['overdue'] }} overdue</span>
                </div>
            </div>
        </header>

        <div class="bh-stats">
            @foreach ($this->getKpiCards() as $kpi)
                <a href="{{ $kpi['url'] }}" class="bh-stat bh-stat--{{ $kpi['tone'] }}">
                    <div class="bh-stat__row">
                        <span class="bh-stat__icon">
                            <x-filament::icon :icon="$kpi['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                    <span class="bh-stat__label">{{ $kpi['label'] }}</span>
                    <strong class="bh-stat__value">{{ $kpi['value'] }}</strong>
                    <span class="bh-stat__hint">{{ $kpi['hint'] }}</span>
                </a>
            @endforeach
        </div>

        <section>
            <div class="bh-section__head">
                <div>
                    <h2 class="bh-section__title">Smart billing ops</h2>
                    <p class="bh-section__sub">Dunning · credit limit · prepaid wallet · aging</p>
                </div>
                <a href="{{ \App\Filament\Pages\DunningReport::getUrl() }}" class="bh-section__link">Full dunning report →</a>
            </div>
            <div class="bh-ops">
                @foreach ($this->getOpsCards() as $op)
                    <div @class([
                        'bh-ops__card bh-ops__card--' . $op['tone'],
                        'bh-ops__card--alert' => ! empty($op['alert']),
                    ])>
                        <span class="bh-ops__label">{{ $op['label'] }}</span>
                        <p @class([
                            'bh-ops__value',
                            'bh-ops__value--rose' => $op['tone'] === 'rose' && ! empty($op['alert']),
                            'bh-ops__value--amber' => $op['tone'] === 'amber',
                        ])>{{ $op['value'] }}</p>
                        <span class="bh-ops__meta">{{ $op['meta'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section>
            <div class="bh-section__head">
                <div>
                    <h2 class="bh-section__title">Tools &amp; workflows</h2>
                    <p class="bh-section__sub">Cashier · invoices · field collection · reports</p>
                </div>
            </div>
            <div class="bh-bento">
                @foreach ($this->getActionCards() as $card)
                    <a
                        href="{{ $card['url'] }}"
                        @class([
                            'bh-tile bh-tile--' . $card['tone'],
                            'bh-tile--featured' => ! empty($card['featured']),
                        ])
                        @if (! empty($card['external'])) target="_blank" rel="noopener" @endif
                    >
                        <div class="bh-tile__head">
                            <span class="bh-tile__icon">
                                <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                            </span>
                            <x-filament::icon icon="heroicon-m-arrow-up-right" class="bh-tile__go" />
                        </div>
                        <div>
                            <h3 class="bh-tile__title">{{ $card['title'] }}</h3>
                            <p class="bh-tile__desc">{{ $card['desc'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <details class="bh-cli">
            <summary>Scheduler &amp; CLI commands</summary>
            <div class="bh-cli__body">
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li><code>php artisan isp:generate-bills [--force] [--dry-run]</code></li>
                    <li><code>php artisan isp:apply-late-fees [--dry-run]</code></li>
                    <li><code>php artisan isp:send-invoice-due-reminders</code></li>
                    <li><code>php artisan isp:prepaid-wallet-settle</code></li>
                </ul>
                <p class="mt-3">Packages set billing cycle; subscribers set billing mode, grace days, and billing day.</p>
            </div>
        </details>

        <nav class="bh-dock" aria-label="Quick navigation">
            <div class="bh-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
                    ['url' => \App\Filament\Resources\InvoiceResource::getUrl('index'), 'label' => 'Bills', 'icon' => 'heroicon-o-queue-list'],
                    ['url' => \App\Filament\Pages\BillCollectionDesk::getUrl(), 'label' => 'Collect', 'icon' => 'heroicon-o-currency-bangladeshi', 'active' => true],
                    ['url' => \App\Filament\Pages\ManagePaymentSettings::getUrl(), 'label' => 'Pay', 'icon' => 'heroicon-o-credit-card'],
                    ['url' => \App\Filament\Pages\ReportsHub::getUrl(), 'label' => 'Reports', 'icon' => 'heroicon-o-chart-pie'],
                ] as $link)
                    @php
                        $href = $link['url'];
                    @endphp
                    @if ($href)
                        <a
                            href="{{ $href }}"
                            @class([
                                'bh-dock__link',
                                'bh-dock__link--active' => ! empty($link['active']),
                            ])
                        >
                            <x-filament::icon :icon="$link['icon']" />
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
