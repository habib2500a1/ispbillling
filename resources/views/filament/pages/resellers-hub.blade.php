@php
    $stats = $this->getStats();
    $statCards = [
        ['label' => 'Partners', 'value' => (string) $stats['total'], 'hint' => $stats['active'].' active', 'class' => 'isp-hub-stat--violet'],
        ['label' => 'Franchises', 'value' => (string) $stats['franchises'], 'hint' => 'Multi-branch partners', 'class' => 'isp-hub-stat--sky'],
        ['label' => 'White-label', 'value' => (string) $stats['white_label'], 'hint' => 'Custom branded portals', 'class' => 'isp-hub-stat--teal'],
        ['label' => 'Wallet total', 'value' => number_format($stats['wallet_total'], 2).' BDT', 'hint' => 'Combined reseller wallets', 'class' => 'isp-hub-stat--amber'],
        ['label' => 'Pending commission', 'value' => number_format($stats['pending_commission'], 2).' BDT', 'hint' => 'Awaiting settlement', 'class' => 'isp-hub-stat--danger', 'valueClass' => 'isp-hub-stat-value--danger'],
    ];
    $links = [
        ['eyebrow' => 'Directory', 'label' => 'All resellers & franchises', 'hint' => 'Create, edit, hierarchy, territories', 'url' => \App\Filament\Resources\ResellerResource::getUrl('index'), 'icon' => 'heroicon-o-users', 'accent' => 'text-violet-600'],
        ['eyebrow' => 'Onboarding', 'label' => 'Add reseller', 'hint' => 'Commission, wallet & portal login', 'url' => \App\Filament\Resources\ResellerResource::getUrl('create'), 'icon' => 'heroicon-o-user-plus', 'accent' => 'text-indigo-600'],
        ['eyebrow' => 'Pricing', 'label' => 'Package prices', 'hint' => 'Area & zone pricing overrides', 'url' => \App\Filament\Pages\ResellerPackagePricesPage::getUrl(), 'icon' => 'heroicon-o-tag', 'accent' => 'text-amber-600'],
        ['eyebrow' => 'Reports', 'label' => 'Commission report', 'hint' => 'Earnings by partner & period', 'url' => \App\Filament\Pages\ResellerReportPage::getUrl(), 'icon' => 'heroicon-o-chart-pie', 'accent' => 'text-cyan-600'],
        ['eyebrow' => 'Wallet', 'label' => 'Wallet hub', 'hint' => 'Top-up and balances', 'url' => \App\Filament\Pages\ResellerWalletHubPage::getUrl(), 'icon' => 'heroicon-o-wallet', 'accent' => 'text-emerald-600'],
        ['eyebrow' => 'Portal', 'label' => 'Partner portal', 'hint' => '/reseller/login access for subscribers, wallet & commissions', 'url' => url('/reseller/login'), 'icon' => 'heroicon-o-arrow-top-right-on-square', 'accent' => 'text-slate-600', 'external' => true],
    ];
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
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

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
