@php
    $stats = $this->getStats();
    $revenue = $this->getRevenueAnalytics();
    $growth = $revenue['growth'] ?? [];
    $growthLabels = $growth['labels'] ?? [];
    $growthValues = $growth['values'] ?? [];
    $maxGrowth = max(1, (float) ($growth['max'] ?? 1));
@endphp

{!! \App\Support\BillingStyles::navigatedScript() !!}
<link rel="stylesheet" href="{{ asset('css/billing-hub-pro.css') }}?v={{ @filemtime(public_path('css/billing-hub-pro.css')) ?: 1 }}">
<script src="{{ asset('js/billing-invoices-v2.js') }}?v={{ @filemtime(public_path('js/billing-invoices-v2.js')) ?: 1 }}" defer></script>

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

        <div class="bh-quick-actions">
            <a href="{{ \App\Filament\Resources\InvoiceResource::getUrl('create') }}" class="bh-quick-actions__btn bh-quick-actions__btn--primary">
                <x-filament::icon icon="heroicon-m-document-plus" class="h-4 w-4" />
                New invoice
            </a>
            <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="bh-quick-actions__btn">
                <x-filament::icon icon="heroicon-m-currency-bangladeshi" class="h-4 w-4" />
                Collect payment
            </a>
            <a href="{{ \App\Filament\Resources\InvoiceResource::getUrl('due') }}" class="bh-quick-actions__btn">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                Due bills
            </a>
            <a href="{{ \App\Filament\Pages\BillingNoticesPage::getUrl() }}" class="bh-quick-actions__btn">
                <x-filament::icon icon="heroicon-m-bell-alert" class="h-4 w-4" />
                Notices
            </a>
        </div>

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

        <section class="bh-revenue">
            <div class="bh-revenue__chart">
                <div class="bh-revenue__chart-head">
                    <div>
                        <h2 class="bh-revenue__chart-title">Revenue growth</h2>
                        <p class="bh-revenue__chart-sub">6-month collection trend</p>
                    </div>
                    <div class="bh-collection-rate">
                        <div class="bh-collection-rate__track">
                            <div class="bh-collection-rate__fill" style="width: {{ $revenue['collection_rate'] ?? 0 }}%"></div>
                        </div>
                        <span class="bh-collection-rate__label">{{ $revenue['collection_rate'] ?? 0 }}% collected</span>
                    </div>
                </div>
                <div class="bh-revenue__bars">
                    @foreach ($growthLabels as $idx => $label)
                        @php
                            $amount = (float) ($growthValues[$idx] ?? 0);
                            $height = max(4, round(($amount / $maxGrowth) * 100));
                        @endphp
                        <div class="bh-revenue__bar-col">
                            <span class="bh-revenue__bar-val">{{ $amount >= 1000 ? number_format($amount / 1000, 0).'k' : number_format($amount, 0) }}</span>
                            <div class="bh-revenue__bar-wrap">
                                <div class="bh-revenue__bar" style="height: {{ $height }}%"></div>
                            </div>
                            <span class="bh-revenue__bar-label">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="bh-revenue__metrics">
                <div class="bh-revenue__metric bl-grad--revenue">
                    <span class="bh-revenue__metric-label">Total revenue (MTD)</span>
                    <span class="bh-revenue__metric-value">{{ number_format($revenue['monthly_bill'] ?? 0, 0) }} BDT</span>
                    <span class="bh-revenue__metric-hint">Monthly bill issued</span>
                </div>
                <div class="bh-revenue__metric bl-grad--collection">
                    <span class="bh-revenue__metric-label">Today's collection</span>
                    <span class="bh-revenue__metric-value">{{ number_format($revenue['collected_today'] ?? 0, 0) }} BDT</span>
                    <span class="bh-revenue__metric-hint">Cash received today</span>
                </div>
                <div class="bh-revenue__metric bl-grad--paid">
                    <span class="bh-revenue__metric-label">Monthly collection</span>
                    <span class="bh-revenue__metric-value">{{ number_format($revenue['collected_month'] ?? 0, 0) }} BDT</span>
                    <span class="bh-revenue__metric-hint">This month received</span>
                </div>
                <div class="bh-revenue__metric bl-grad--due">
                    <span class="bh-revenue__metric-label">Due amount</span>
                    <span class="bh-revenue__metric-value">{{ number_format($revenue['total_due'] ?? 0, 0) }} BDT</span>
                    <span class="bh-revenue__metric-hint">Outstanding AR</span>
                </div>
            </div>
        </section>

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
