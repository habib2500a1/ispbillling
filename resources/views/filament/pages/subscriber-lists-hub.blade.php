@php
    $lists = $this->getLists();

    $statCards = [
        [
            'tone'  => 'indigo',
            'icon'  => 'heroicon-o-queue-list',
            'label' => 'Saved views',
            'value' => (string) count($lists),
            'hint'  => 'Subscriber segments',
        ],
        [
            'tone'  => 'rose',
            'icon'  => 'heroicon-o-exclamation-circle',
            'label' => 'Attention',
            'value' => '3',
            'hint'  => 'Expired · suspended · left',
        ],
        [
            'tone'  => 'amber',
            'icon'  => 'heroicon-o-star',
            'label' => 'Growth',
            'value' => '2',
            'hint'  => 'Active & VIP monitoring',
        ],
        [
            'tone'  => 'sky',
            'icon'  => 'heroicon-o-bolt',
            'label' => 'Ops access',
            'value' => 'Instant',
            'hint'  => 'One-click filtered lists',
        ],
    ];
@endphp

<x-filament-panels::page class="isp-slh-page">
    <div class="slh-pro">

        {{-- ── Hero ── --}}
        <header class="slh-hero">
            <div class="slh-hero__grid">
                <span class="slh-hero__badge">
                    <span class="slh-hero__badge-dot" aria-hidden="true"></span>
                    Subscriber operations
                </span>
                <h1 class="slh-hero__title">Subscriber Lists</h1>
                <p class="slh-hero__sub">
                    Quick access to filtered subscriber views — active, free, VIP, expired, suspended, and left clients. One tap to open any segment.
                </p>
                <div class="slh-hero__actions">
                    <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('index') }}" class="slh-btn slh-btn--white">
                        <x-filament::icon icon="heroicon-m-users" class="h-4 w-4" />
                        All subscribers
                    </a>
                    <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('create') }}" class="slh-btn slh-btn--glass">
                        <x-filament::icon icon="heroicon-m-user-plus" class="h-4 w-4" />
                        Add client
                    </a>
                    <a href="{{ \App\Filament\Pages\ClientsHub::getUrl() }}" class="slh-btn slh-btn--glass">
                        <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                        Clients center
                    </a>
                </div>
            </div>
            <div class="slh-hero__meta">
                <div class="slh-hero__meta-card">
                    <span class="slh-hero__meta-label">Segment views</span>
                    <strong class="slh-hero__meta-value">{{ count($lists) }}</strong>
                    <span class="slh-hero__meta-hint">Ops-ready filters</span>
                </div>
            </div>
        </header>

        {{-- ── KPI strip ── --}}
        <div class="slh-stats">
            @foreach ($statCards as $stat)
                <div class="slh-stat slh-stat--{{ $stat['tone'] }}">
                    <span class="slh-stat__icon">
                        <x-filament::icon :icon="$stat['icon']" class="h-5 w-5" />
                    </span>
                    <span class="slh-stat__label">{{ $stat['label'] }}</span>
                    <strong class="slh-stat__value">{{ $stat['value'] }}</strong>
                    <span class="slh-stat__hint">{{ $stat['hint'] }}</span>
                </div>
            @endforeach
        </div>

        {{-- ── Segment shortcuts ── --}}
        <section class="slh-section">
            <div class="slh-section__head">
                <div>
                    <h2 class="slh-section__title">Segment shortcuts</h2>
                    <p class="slh-section__sub">Open the most-used subscriber filters for billing review, retention work, and support follow-up.</p>
                </div>
                <span class="slh-section__tag">{{ count($lists) }} lists</span>
            </div>

            <div class="slh-grid">
                @foreach ($lists as $list)
                    <a href="{{ $list['url'] }}" class="slh-card slh-card--{{ $list['color'] }}">
                        <span class="slh-card__icon">
                            <x-filament::icon :icon="$list['icon']" class="h-6 w-6" />
                        </span>
                        <div class="slh-card__body">
                            <p class="slh-card__eyebrow">Filtered subscribers</p>
                            <p class="slh-card__title">{{ $list['label'] }}</p>
                            <p class="slh-card__desc">{{ $list['description'] }}</p>
                        </div>
                        <span class="slh-card__arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- ── Bottom dock ── --}}
        <nav class="slh-dock" aria-label="Quick navigation">
            <div class="slh-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(),                              'label' => 'Home',      'icon' => 'heroicon-o-home'],
                    ['url' => \App\Filament\Pages\ClientsHub::getUrl(),                             'label' => 'Clients',   'icon' => 'heroicon-o-users'],
                    ['url' => \App\Filament\Pages\SubscriberListsHub::getUrl(),                     'label' => 'Lists',     'icon' => 'heroicon-o-queue-list', 'active' => true],
                    ['url' => \App\Filament\Pages\BillingOverview::getUrl(),                        'label' => 'Billing',   'icon' => 'heroicon-o-banknotes'],
                    ['url' => \App\Filament\Pages\ReportsHub::getUrl(),                             'label' => 'Reports',   'icon' => 'heroicon-o-chart-pie'],
                ] as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @class([
                            'slh-dock__link',
                            'slh-dock__link--active' => ! empty($link['active']),
                        ])
                    >
                        <x-filament::icon :icon="$link['icon']" />
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

    </div>
</x-filament-panels::page>
