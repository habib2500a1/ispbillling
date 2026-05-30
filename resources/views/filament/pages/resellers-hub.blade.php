@php
    use App\Filament\Resources\ResellerResource;
    use App\Filament\Pages\ResellerPackagePricesPage;
    use App\Filament\Pages\ResellerReportPage;
    use App\Filament\Pages\ResellerWalletHubPage;

    $stats = $this->getStats();
    $partners = $this->getTopPartners();
    $mix = $this->getPartnerMix();
    $settlement = $this->getSettlement();

    $createUrl = ResellerResource::getUrl('create');
    $reportUrl = ResellerReportPage::getUrl();

    $statCards = [
        ['label' => 'Partners', 'value' => (string) $stats['total'], 'hint' => $stats['active'].' active · '.$stats['inactive'].' idle', 'class' => 'isp-hub-stat--violet'],
        ['label' => 'Subscribers', 'value' => number_format($stats['customers_total']), 'hint' => 'Linked to partners', 'class' => 'isp-hub-stat--indigo'],
        ['label' => 'Franchises', 'value' => (string) $stats['franchises'], 'hint' => $stats['sub_resellers'].' sub-resellers', 'class' => 'isp-hub-stat--sky'],
        ['label' => 'White-label', 'value' => (string) $stats['white_label'], 'hint' => 'Custom branded portals', 'class' => 'isp-hub-stat--teal'],
        ['label' => 'Wallet total', 'value' => number_format($stats['wallet_total'], 2).' ৳', 'hint' => 'Combined reseller wallets', 'class' => 'isp-hub-stat--amber'],
        ['label' => 'Pending commission', 'value' => number_format($stats['pending_commission'], 2).' ৳', 'hint' => 'Awaiting settlement', 'class' => 'isp-hub-stat--danger', 'valueClass' => 'isp-hub-stat-value--danger'],
    ];

    $links = [
        ['eyebrow' => 'Directory', 'label' => 'All resellers & franchises', 'hint' => 'Create, edit, hierarchy, territories', 'url' => ResellerResource::getUrl('index'), 'icon' => 'heroicon-o-users', 'accent' => 'text-violet-600'],
        ['eyebrow' => 'Onboarding', 'label' => 'Add reseller', 'hint' => 'Commission, wallet & portal login', 'url' => $createUrl, 'icon' => 'heroicon-o-user-plus', 'accent' => 'text-indigo-600'],
        ['eyebrow' => 'Pricing', 'label' => 'Package prices', 'hint' => 'Area & zone pricing overrides', 'url' => ResellerPackagePricesPage::getUrl(), 'icon' => 'heroicon-o-tag', 'accent' => 'text-amber-600'],
        ['eyebrow' => 'Reports', 'label' => 'Commission report', 'hint' => 'Earnings by partner & period', 'url' => $reportUrl, 'icon' => 'heroicon-o-chart-pie', 'accent' => 'text-cyan-600'],
        ['eyebrow' => 'Wallet', 'label' => 'Wallet hub', 'hint' => 'Top-up and balances', 'url' => ResellerWalletHubPage::getUrl(), 'icon' => 'heroicon-o-wallet', 'accent' => 'text-emerald-600'],
        ['eyebrow' => 'Portal', 'label' => 'Partner portal', 'hint' => '/reseller/login access for subscribers, wallet & commissions', 'url' => url('/reseller/login'), 'icon' => 'heroicon-o-arrow-top-right-on-square', 'accent' => 'text-slate-600', 'external' => true],
    ];

    $mixPalette = ['isp-mix-bar--violet', 'isp-mix-bar--sky', 'isp-mix-bar--teal', 'isp-mix-bar--amber', 'isp-mix-bar--indigo', 'isp-mix-bar--rose'];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Partner operations"
            title="Reseller & franchise management"
            description="Reseller dashboard, commission on payments, territory control, wallet transfers, and white-label branding in one workspace."
            class="isp-hub-hero--violet"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ $stats['total'] }} partners indexed</span>
                    <span class="isp-hub-section__meta">{{ $stats['active'] }} active</span>
                    <span class="isp-hub-section__meta">{{ $stats['white_label'] }} white-label</span>
                    <span class="isp-hub-section__meta">{{ number_format($stats['customers_total']) }} subscribers</span>
                </div>
                <a href="{{ $createUrl }}" class="isp-hub-cta">
                    <x-filament::icon icon="heroicon-o-user-plus" class="h-4 w-4" />
                    Add reseller
                </a>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <div class="isp-hub-grid--split">
            {{-- Top partners leaderboard --}}
            <section class="isp-hub-section">
                <div class="isp-hub-section__head">
                    <div>
                        <h2 class="isp-hub-section__title">Top partners</h2>
                        <p class="isp-hub-section__desc">Ranked by active subscriber base across the partner network.</p>
                    </div>
                    <a href="{{ ResellerResource::getUrl('index') }}" class="isp-hub-section__meta">View all →</a>
                </div>

                @if (count($partners) > 0)
                    <ol class="isp-rank-list">
                        @foreach ($partners as $i => $partner)
                            <li>
                                <a href="{{ $partner['url'] }}" class="isp-rank-row">
                                    <span class="isp-rank-badge {{ ['isp-rank-badge--gold', 'isp-rank-badge--silver', 'isp-rank-badge--bronze'][$i] ?? '' }}">{{ $i + 1 }}</span>
                                    <div class="isp-rank-main">
                                        <div class="isp-rank-head">
                                            <span class="isp-rank-name">
                                                <span class="isp-stat-dot {{ $partner['active'] ? 'isp-stat-dot--on' : 'isp-stat-dot--off' }}" aria-hidden="true"></span>
                                                {{ $partner['name'] }}
                                            </span>
                                            <span class="isp-rank-chip">{{ $partner['type'] }}</span>
                                        </div>
                                        <div class="isp-meter" role="presentation">
                                            <span class="isp-meter__fill" style="width: {{ max(4, $partner['width']) }}%"></span>
                                        </div>
                                    </div>
                                    <div class="isp-rank-meta">
                                        <strong>{{ number_format($partner['customers']) }}</strong>
                                        <span>subs · {{ number_format($partner['wallet'], 0) }} ৳</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="isp-hub-empty">No resellers yet. <a href="{{ $createUrl }}" class="font-semibold underline">Onboard your first partner</a> to start tracking the leaderboard.</p>
                @endif
            </section>

            {{-- Settlement panel --}}
            <section class="isp-hub-section">
                <div class="isp-hub-section__head">
                    <div>
                        <h2 class="isp-hub-section__title">Commission settlement</h2>
                        <p class="isp-hub-section__desc">Pending vs settled earnings.</p>
                    </div>
                </div>

                <div class="isp-settle">
                    <div class="isp-settle-headline">
                        <span class="isp-settle-amount">{{ number_format($settlement['pending_amount'], 2) }} ৳</span>
                        <span class="isp-settle-cap">pending across {{ $settlement['pending_count'] }} entries</span>
                    </div>

                    <div class="isp-meter isp-meter--seg" role="presentation" aria-label="{{ $settlement['pending_share'] }}% pending">
                        <span class="isp-meter__fill isp-meter__fill--pending" style="width: {{ $settlement['pending_share'] }}%"></span>
                    </div>
                    <p class="isp-settle-split">{{ $settlement['pending_share'] }}% pending · {{ 100 - $settlement['pending_share'] }}% paid</p>

                    <dl class="isp-settle-rows">
                        <div class="isp-settle-row">
                            <dt><span class="isp-settle-key isp-settle-key--pending"></span>Pending</dt>
                            <dd>{{ number_format($settlement['pending_amount'], 2) }} ৳ <span>· {{ $settlement['pending_count'] }}</span></dd>
                        </div>
                        <div class="isp-settle-row">
                            <dt><span class="isp-settle-key isp-settle-key--paid"></span>Paid</dt>
                            <dd>{{ number_format($settlement['paid_amount'], 2) }} ৳ <span>· {{ $settlement['paid_count'] }}</span></dd>
                        </div>
                        <div class="isp-settle-row">
                            <dt><span class="isp-settle-key isp-settle-key--cancelled"></span>Cancelled</dt>
                            <dd>{{ number_format($settlement['cancelled_amount'], 2) }} ৳ <span>· {{ $settlement['cancelled_count'] }}</span></dd>
                        </div>
                    </dl>

                    <a href="{{ $reportUrl }}" class="isp-hub-cta isp-hub-cta--block">
                        <x-filament::icon icon="heroicon-o-chart-pie" class="h-4 w-4" />
                        Open commission report
                    </a>
                </div>
            </section>
        </div>

        {{-- Partner mix --}}
        @if (count($mix) > 0)
            <section class="isp-hub-section">
                <div class="isp-hub-section__head">
                    <div>
                        <h2 class="isp-hub-section__title">Partner mix</h2>
                        <p class="isp-hub-section__desc">Distribution of partners by tier across the network.</p>
                    </div>
                    <span class="isp-hub-section__meta">{{ $stats['total'] }} total</span>
                </div>
                <div class="isp-mix-row">
                    @foreach ($mix as $i => $segment)
                        <div class="isp-mix-item">
                            <div class="isp-mix-label">
                                <span>{{ $segment['label'] }}</span>
                                <strong>{{ $segment['count'] }} <span>· {{ $segment['share'] }}%</span></strong>
                            </div>
                            <div class="isp-meter" role="presentation">
                                <span class="isp-meter__fill {{ $mixPalette[$i % count($mixPalette)] }}" style="width: {{ max(4, $segment['share']) }}%"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Partner shortcuts --}}
        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Partner shortcuts</h2>
                    <p class="isp-hub-section__desc">Open onboarding, pricing, commission reporting, wallet control, and portal access from one place.</p>
                </div>
                <span class="isp-hub-section__meta">{{ count($links) }} shortcuts</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--3">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="isp-module-card group"
                        @if (! empty($link['external']))
                            target="_blank"
                            rel="noopener"
                        @endif
                    >
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon {{ $link['accent'] }}">
                                <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="isp-module-card__eyebrow">{{ $link['eyebrow'] }}</p>
                                <p class="isp-module-card__title">{{ $link['label'] }}</p>
                                <p class="isp-module-card__desc">{{ $link['hint'] }}</p>
                            </div>
                            <span class="isp-module-card__arrow" aria-hidden="true">{{ ! empty($link['external']) ? '↗' : '→' }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
