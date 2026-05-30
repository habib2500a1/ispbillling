@php
    use App\Filament\Resources\ResellerResource;
    use App\Filament\Pages\ResellerPackagePricesPage;
    use App\Filament\Pages\ResellerReportPage;
    use App\Filament\Pages\ResellerWalletHubPage;

    $stats       = $this->getStats();
    $partners    = $this->getTopPartners();
    $mix         = $this->getPartnerMix();
    $settlement  = $this->getSettlement();
    $commissions = $this->getRecentCommissions();

    $createUrl = ResellerResource::getUrl('create');
    $reportUrl = ResellerReportPage::getUrl();

    $mixPalette = ['violet', 'sky', 'teal', 'amber', 'indigo', 'rose'];
@endphp

<x-filament-panels::page class="isp-rsh-page">

<div class="rsh-pro">

    {{-- ── Hero ── --}}
    <div class="rsh-hero">
        <div class="rsh-hero__grid">
            <span class="rsh-hero__badge">
                <span class="rsh-hero__badge-dot" aria-hidden="true"></span>
                Partner operations
            </span>
            <h1 class="rsh-hero__title">Reseller &amp; Franchise</h1>
            <p class="rsh-hero__sub">Commission on payments, territory control, wallet transfers, and white-label branding in one workspace.</p>
            <div class="rsh-hero__actions">
                <a href="{{ ResellerResource::getUrl('index') }}" class="rsh-btn--white">
                    <x-filament::icon icon="heroicon-o-users" class="h-4 w-4" />
                    All resellers
                </a>
                <a href="{{ $createUrl }}" class="rsh-btn--glass">
                    <x-filament::icon icon="heroicon-o-user-plus" class="h-4 w-4" />
                    Add reseller
                </a>
                <a href="{{ $reportUrl }}" class="rsh-btn--glass">
                    <x-filament::icon icon="heroicon-o-chart-pie" class="h-4 w-4" />
                    Commission report
                </a>
            </div>
        </div>
        <div class="rsh-hero__meta">
            <div class="rsh-hero__meta-card">
                <span class="rsh-hero__meta-label">Total partners</span>
                <span class="rsh-hero__meta-value">{{ $stats['total'] }}</span>
                <span class="rsh-hero__meta-hint">{{ $stats['active'] }} active</span>
            </div>
        </div>
    </div>

    {{-- ── KPI strip ── --}}
    <div class="rsh-stats">
        <div class="rsh-stat rsh-stat--violet">
            <div class="rsh-stat__icon">
                <x-filament::icon icon="heroicon-o-users" class="h-4 w-4" />
            </div>
            <span class="rsh-stat__label">Partners</span>
            <span class="rsh-stat__value">{{ $stats['total'] }}</span>
            <span class="rsh-stat__hint">{{ $stats['active'] }} active · {{ $stats['inactive'] }} idle</span>
        </div>
        <div class="rsh-stat rsh-stat--indigo">
            <div class="rsh-stat__icon">
                <x-filament::icon icon="heroicon-o-user-group" class="h-4 w-4" />
            </div>
            <span class="rsh-stat__label">Subscribers</span>
            <span class="rsh-stat__value">{{ number_format($stats['customers_total']) }}</span>
            <span class="rsh-stat__hint">Linked to partners</span>
        </div>
        <div class="rsh-stat rsh-stat--sky">
            <div class="rsh-stat__icon">
                <x-filament::icon icon="heroicon-o-building-office" class="h-4 w-4" />
            </div>
            <span class="rsh-stat__label">Franchises</span>
            <span class="rsh-stat__value">{{ $stats['franchises'] }}</span>
            <span class="rsh-stat__hint">{{ $stats['sub_resellers'] }} sub-resellers</span>
        </div>
        <div class="rsh-stat rsh-stat--teal">
            <div class="rsh-stat__icon">
                <x-filament::icon icon="heroicon-o-paint-brush" class="h-4 w-4" />
            </div>
            <span class="rsh-stat__label">White-label</span>
            <span class="rsh-stat__value">{{ $stats['white_label'] }}</span>
            <span class="rsh-stat__hint">Custom branded portals</span>
        </div>
        <div class="rsh-stat rsh-stat--amber">
            <div class="rsh-stat__icon">
                <x-filament::icon icon="heroicon-o-wallet" class="h-4 w-4" />
            </div>
            <span class="rsh-stat__label">Wallet total</span>
            <span class="rsh-stat__value">{{ number_format($stats['wallet_total'], 0) }} ৳</span>
            <span class="rsh-stat__hint">Combined reseller wallets</span>
        </div>
        <div class="rsh-stat rsh-stat--rose">
            <div class="rsh-stat__icon">
                <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
            </div>
            <span class="rsh-stat__label">Pending commission</span>
            <span class="rsh-stat__value">{{ number_format($stats['pending_commission'], 0) }} ৳</span>
            <span class="rsh-stat__hint">Awaiting settlement</span>
        </div>
    </div>

    {{-- ── Split grid: leaderboard + settlement ── --}}
    <div class="rsh-split">

        {{-- Top partners leaderboard --}}
        <section class="rsh-section">
            <div class="rsh-section__head">
                <div>
                    <h2 class="rsh-section__title">Top partners</h2>
                    <p class="rsh-section__sub">Ranked by active subscriber base across the partner network.</p>
                </div>
                <a href="{{ ResellerResource::getUrl('index') }}" class="rsh-section__tag">View all →</a>
            </div>

            @if (count($partners) > 0)
                <ol class="rsh-rank-list">
                    @foreach ($partners as $i => $partner)
                        <li class="rsh-rank-item">
                            <a href="{{ $partner['url'] }}" class="rsh-rank-row">
                                <span class="rsh-rank-badge {{ ['rsh-rank-badge--gold', 'rsh-rank-badge--silver', 'rsh-rank-badge--bronze'][$i] ?? '' }}">{{ $i + 1 }}</span>
                                <div class="rsh-rank-main">
                                    <div class="rsh-rank-head">
                                        <span class="rsh-rank-name">
                                            <span class="rsh-dot {{ $partner['active'] ? 'rsh-dot--on' : 'rsh-dot--off' }}" aria-hidden="true"></span>
                                            {{ $partner['name'] }}
                                        </span>
                                        <span class="rsh-rank-chip">{{ $partner['type'] }}</span>
                                    </div>
                                    <div class="rsh-meter" role="presentation">
                                        <span class="rsh-meter__fill" style="width: {{ max(4, $partner['width']) }}%"></span>
                                    </div>
                                </div>
                                <div class="rsh-rank-meta">
                                    <strong>{{ number_format($partner['customers']) }}</strong>
                                    <span>subs · {{ number_format($partner['wallet'], 0) }} ৳</span>
                                </div>
                            </a>
                            <a href="{{ $partner['portal_login_url'] }}" target="_blank" rel="noopener" class="rsh-portal-login-btn" title="Log in as this partner">
                                Portal login
                            </a>
                        </li>
                    @endforeach
                </ol>
            @else
                <p style="font-size:0.875rem;color:var(--rsh-muted);">
                    No resellers yet.
                    <a href="{{ $createUrl }}" style="font-weight:600;text-decoration:underline;color:var(--rsh-violet);">Onboard your first partner</a>
                    to start tracking the leaderboard.
                </p>
            @endif
        </section>

        {{-- Commission settlement --}}
        <section class="rsh-section">
            <div class="rsh-section__head">
                <div>
                    <h2 class="rsh-section__title">Commission settlement</h2>
                    <p class="rsh-section__sub">Pending vs settled earnings.</p>
                </div>
            </div>

            <div class="rsh-settle">
                <div class="rsh-settle-headline">
                    <span class="rsh-settle-amount">{{ number_format($settlement['pending_amount'], 2) }} ৳</span>
                    <span class="rsh-settle-cap">pending across {{ $settlement['pending_count'] }} entries</span>
                </div>

                <div class="rsh-meter" role="presentation" aria-label="{{ $settlement['pending_share'] }}% pending">
                    <span class="rsh-meter__fill rsh-meter__fill--pending" style="width: {{ $settlement['pending_share'] }}%"></span>
                </div>
                <p class="rsh-settle-split">{{ $settlement['pending_share'] }}% pending · {{ 100 - $settlement['pending_share'] }}% paid</p>

                <dl class="rsh-settle-rows">
                    <div class="rsh-settle-row">
                        <dt><span class="rsh-settle-key rsh-settle-key--pending"></span>Pending</dt>
                        <dd>{{ number_format($settlement['pending_amount'], 2) }} ৳ <span>· {{ $settlement['pending_count'] }}</span></dd>
                    </div>
                    <div class="rsh-settle-row">
                        <dt><span class="rsh-settle-key rsh-settle-key--paid"></span>Paid</dt>
                        <dd>{{ number_format($settlement['paid_amount'], 2) }} ৳ <span>· {{ $settlement['paid_count'] }}</span></dd>
                    </div>
                    <div class="rsh-settle-row">
                        <dt><span class="rsh-settle-key rsh-settle-key--cancelled"></span>Cancelled</dt>
                        <dd>{{ number_format($settlement['cancelled_amount'], 2) }} ৳ <span>· {{ $settlement['cancelled_count'] }}</span></dd>
                    </div>
                </dl>

                <a href="{{ $reportUrl }}" class="rsh-settle-cta">
                    <x-filament::icon icon="heroicon-o-chart-pie" class="h-4 w-4" />
                    Open commission report
                </a>
            </div>
        </section>
    </div>

    {{-- ── Partner mix ── --}}
    @if (count($mix) > 0)
        <section class="rsh-section">
            <div class="rsh-section__head">
                <div>
                    <h2 class="rsh-section__title">Partner mix</h2>
                    <p class="rsh-section__sub">Distribution of partners by tier across the network.</p>
                </div>
                <span class="rsh-section__tag">{{ $stats['total'] }} total</span>
            </div>
            <div class="rsh-mix-row">
                @foreach ($mix as $i => $segment)
                    <div class="rsh-mix-item">
                        <div class="rsh-mix-label">
                            <span>{{ $segment['label'] }}</span>
                            <strong>{{ $segment['count'] }} <span>· {{ $segment['share'] }}%</span></strong>
                        </div>
                        <div class="rsh-meter" role="presentation">
                            <span class="rsh-meter__fill rsh-meter__fill--{{ $mixPalette[$i % count($mixPalette)] }}" style="width: {{ max(4, $segment['share']) }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Recent commissions activity feed ── --}}
    @if (count($commissions) > 0)
        <section class="rsh-section">
            <div class="rsh-section__head">
                <div>
                    <h2 class="rsh-section__title">Recent commissions</h2>
                    <p class="rsh-section__sub">Latest commission entries across all partners.</p>
                </div>
                <a href="{{ $reportUrl }}" class="rsh-section__tag">Full report →</a>
            </div>
            <div class="rsh-feed">
                @foreach ($commissions as $commission)
                    <a href="{{ $commission['url'] }}" class="rsh-feed-row" style="text-decoration:none;">
                        <span class="rsh-feed-badge rsh-feed-badge--{{ $commission['status'] }}">{{ $commission['status'] }}</span>
                        <span class="rsh-feed-name">{{ $commission['reseller'] }}</span>
                        <span class="rsh-feed-amount">{{ number_format($commission['amount'], 2) }} ৳</span>
                        <span class="rsh-feed-date">{{ $commission['date'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Partner shortcuts ── --}}
    <section class="rsh-section">
        <div class="rsh-section__head">
            <div>
                <h2 class="rsh-section__title">Partner shortcuts</h2>
                <p class="rsh-section__sub">Open onboarding, pricing, commission reporting, wallet control, and portal access from one place.</p>
            </div>
            <span class="rsh-section__tag">6 shortcuts</span>
        </div>
        <div class="rsh-grid">
            <a href="{{ ResellerResource::getUrl('index') }}" class="rsh-card rsh-card--violet">
                <div class="rsh-card__icon">
                    <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                </div>
                <div class="rsh-card__body">
                    <p class="rsh-card__eyebrow">Directory</p>
                    <p class="rsh-card__title">All resellers</p>
                    <p class="rsh-card__desc">Create, edit, hierarchy, territories</p>
                </div>
                <span class="rsh-card__arrow" aria-hidden="true">→</span>
            </a>

            <a href="{{ $createUrl }}" class="rsh-card rsh-card--indigo">
                <div class="rsh-card__icon">
                    <x-filament::icon icon="heroicon-o-user-plus" class="h-5 w-5" />
                </div>
                <div class="rsh-card__body">
                    <p class="rsh-card__eyebrow">Onboarding</p>
                    <p class="rsh-card__title">Add reseller</p>
                    <p class="rsh-card__desc">Commission, wallet &amp; portal login</p>
                </div>
                <span class="rsh-card__arrow" aria-hidden="true">→</span>
            </a>

            <a href="{{ ResellerPackagePricesPage::getUrl() }}" class="rsh-card rsh-card--amber">
                <div class="rsh-card__icon">
                    <x-filament::icon icon="heroicon-o-tag" class="h-5 w-5" />
                </div>
                <div class="rsh-card__body">
                    <p class="rsh-card__eyebrow">Pricing</p>
                    <p class="rsh-card__title">Package prices</p>
                    <p class="rsh-card__desc">Area &amp; zone pricing overrides</p>
                </div>
                <span class="rsh-card__arrow" aria-hidden="true">→</span>
            </a>

            <a href="{{ $reportUrl }}" class="rsh-card rsh-card--sky">
                <div class="rsh-card__icon">
                    <x-filament::icon icon="heroicon-o-chart-pie" class="h-5 w-5" />
                </div>
                <div class="rsh-card__body">
                    <p class="rsh-card__eyebrow">Reports</p>
                    <p class="rsh-card__title">Commission report</p>
                    <p class="rsh-card__desc">Earnings by partner &amp; period</p>
                </div>
                <span class="rsh-card__arrow" aria-hidden="true">→</span>
            </a>

            <a href="{{ ResellerWalletHubPage::getUrl() }}" class="rsh-card rsh-card--emerald">
                <div class="rsh-card__icon">
                    <x-filament::icon icon="heroicon-o-wallet" class="h-5 w-5" />
                </div>
                <div class="rsh-card__body">
                    <p class="rsh-card__eyebrow">Wallet</p>
                    <p class="rsh-card__title">Wallet hub</p>
                    <p class="rsh-card__desc">Top-up and balances</p>
                </div>
                <span class="rsh-card__arrow" aria-hidden="true">→</span>
            </a>

            <a href="{{ url('/reseller/login') }}" class="rsh-card rsh-card--slate" target="_blank" rel="noopener">
                <div class="rsh-card__icon">
                    <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-5 w-5" />
                </div>
                <div class="rsh-card__body">
                    <p class="rsh-card__eyebrow">Portal</p>
                    <p class="rsh-card__title">Partner portal</p>
                    <p class="rsh-card__desc">/reseller/login for subscribers &amp; wallet</p>
                </div>
                <span class="rsh-card__arrow" aria-hidden="true">↗</span>
            </a>
        </div>
    </section>

    {{-- ── Bottom dock ── --}}
    <nav class="rsh-dock" aria-label="Hub navigation">
        <div class="rsh-dock__inner">
            <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="rsh-dock__link">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                Home
            </a>
            <a href="{{ \App\Filament\Pages\ClientsHub::getUrl() }}" class="rsh-dock__link">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                Clients
            </a>
            <a href="{{ \App\Filament\Pages\ResellersHub::getUrl() }}" class="rsh-dock__link rsh-dock__link--active" aria-current="page">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                Resellers
            </a>
            <a href="{{ \App\Filament\Pages\BillingOverview::getUrl() }}" class="rsh-dock__link">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                Billing
            </a>
            <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="rsh-dock__link">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                Reports
            </a>
        </div>
    </nav>

</div>
</x-filament-panels::page>
