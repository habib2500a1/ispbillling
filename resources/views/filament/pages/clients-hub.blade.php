@php
    $indexUrl = \App\Filament\Resources\CustomerResource::getUrl('index');
    $pollSeconds = (int) config('bandwidth.live_page_poll_seconds', 60);
    $stats = $this->getStats();
    $statCards = $this->getStatCards();
    $onlinePct = ($stats['total'] ?? 0) > 0
        ? round(100 * ($stats['online'] ?? 0) / max(1, $stats['total']))
        : 0;
@endphp

<x-filament-panels::page class="isp-clients-hub-page">
    @if ($pollSeconds > 0)
        <div wire:poll.{{ $pollSeconds }}s="refreshLiveData">
    @else
        <div>
    @endif
    <div class="ch-pro">
        {{-- Premium hero --}}
        <header class="ch-hero">
            <div class="ch-hero__grid">
                <span class="ch-hero__badge">
                    <span class="ch-hero__badge-dot" aria-hidden="true"></span>
                    Subscriber operations
                </span>
                <h1 class="ch-hero__title">Clients Center</h1>
                <p class="ch-hero__sub">
                    Manage home &amp; reseller subscribers — PPPoE, packages, renewals, live sessions, imports, and area reports from one command desk.
                </p>
                <div class="ch-hero__actions">
                    <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('create') }}" class="ch-btn ch-btn--white">
                        <x-filament::icon icon="heroicon-m-user-plus" class="h-4 w-4" />
                        Add client
                    </a>
                    <a href="{{ $indexUrl }}" class="ch-btn ch-btn--glass">
                        <x-filament::icon icon="heroicon-m-users" class="h-4 w-4" />
                        Directory
                    </a>
                    <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="ch-btn ch-btn--glass">
                        <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                        Live PPP
                    </a>
                </div>
            </div>
            <div class="ch-hero__live">
                <div class="ch-hero__live-card">
                    <span class="ch-hero__live-label">Online right now</span>
                    <strong class="ch-hero__live-value">{{ number_format($stats['online'] ?? 0) }}</strong>
                    <span class="ch-hero__live-hint">{{ $onlinePct }}% of {{ number_format($stats['total'] ?? 0) }} subscribers</span>
                </div>
            </div>
        </header>

        {{-- KPI strip (clickable) --}}
        <div class="ch-stats">
            @foreach ($statCards as $i => $stat)
                @php
                    $statUrls = [
                        0 => $indexUrl,
                        1 => $indexUrl.'?preset=online',
                        2 => \App\Filament\Resources\CustomerResource::getUrl('active'),
                        3 => \App\Filament\Resources\CustomerResource::getUrl('expired'),
                        4 => \App\Filament\Resources\CustomerResource::getUrl('suspended'),
                    ];
                    $tones = ['sky', 'teal', 'emerald', 'amber', 'rose'];
                    $icons = [
                        'heroicon-o-users',
                        'heroicon-o-signal',
                        'heroicon-o-check-circle',
                        'heroicon-o-exclamation-circle',
                        'heroicon-o-pause-circle',
                    ];
                @endphp
                <a href="{{ $statUrls[$i] ?? $indexUrl }}" class="ch-stat ch-stat--{{ $tones[$i] ?? 'teal' }}">
                    <div class="ch-stat__top">
                        <span class="ch-stat__icon">
                            <x-filament::icon :icon="$icons[$i] ?? 'heroicon-o-users'" class="h-5 w-5" />
                        </span>
                    </div>
                    <span class="ch-stat__label">{{ $stat['label'] }}</span>
                    <strong class="ch-stat__value">{{ $stat['value'] }}</strong>
                    @if (! empty($stat['hint']))
                        <span class="ch-stat__hint">{{ $stat['hint'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Quick filters --}}
        <section class="ch-section">
            <div class="ch-section__head">
                <div>
                    <h2 class="ch-section__title">Quick access</h2>
                    <p class="ch-section__sub">Saved lists with live counts — one tap to open</p>
                </div>
                <span class="ch-section__tag">Updated ~2 min</span>
            </div>
            <div class="ch-quick">
                @foreach ($this->getQuickFilters() as $chip)
                    <a href="{{ $chip['url'] }}" class="ch-quick__item ch-quick__item--{{ $chip['tone'] }}">
                        <span class="ch-quick__icon">
                            <x-filament::icon :icon="$chip['icon']" class="h-5 w-5" />
                        </span>
                        <span class="ch-quick__body">
                            <span class="ch-quick__label">{{ $chip['label'] }}</span>
                            <strong class="ch-quick__count">
                                @if (is_numeric($chip['count']))
                                    {{ number_format((int) $chip['count']) }}
                                @else
                                    {{ $chip['count'] }}
                                @endif
                            </strong>
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="ch-quick__arrow" />
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Tools bento --}}
        <section class="ch-section">
            <div class="ch-section__head">
                <div>
                    <h2 class="ch-section__title">Tools &amp; workflows</h2>
                    <p class="ch-section__sub">Register, monitor, renew, import &amp; export</p>
                </div>
            </div>
            <div class="ch-bento">
                @foreach ($this->getActionCards() as $card)
                    @php
                        $tileTone = match ($card['icon_class'] ?? '') {
                            'text-emerald-600' => 'emerald',
                            'text-cyan-600' => 'cyan',
                            'text-amber-600' => 'amber',
                            'text-violet-600' => 'violet',
                            'text-slate-600' => 'slate',
                            'text-fuchsia-600' => 'fuchsia',
                            'text-sky-600' => 'sky',
                            'text-indigo-600' => 'indigo',
                            default => 'teal',
                        };
                    @endphp
                    <a
                        href="{{ $card['url'] }}"
                        @class([
                            'ch-tile ch-tile--' . $tileTone,
                            'ch-tile--featured' => ! empty($card['featured']),
                        ])
                    >
                        <div class="ch-tile__head">
                            <span class="ch-tile__icon">
                                <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                            </span>
                            <x-filament::icon icon="heroicon-m-arrow-up-right" class="ch-tile__go" />
                        </div>
                        <div class="ch-tile__body">
                            <h3 class="ch-tile__title">{{ $card['title'] }}</h3>
                            <p class="ch-tile__desc">{{ $card['desc'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Sticky dock --}}
        <nav class="ch-dock" aria-label="Quick navigation">
            <div class="ch-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
                    ['url' => $indexUrl, 'label' => 'Directory', 'icon' => 'heroicon-o-users', 'active' => true],
                    ['url' => \App\Filament\Pages\BillingOverview::getUrl(), 'label' => 'Billing', 'icon' => 'heroicon-o-banknotes'],
                    ['url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl(), 'label' => 'Online', 'icon' => 'heroicon-o-signal'],
                    ['url' => \App\Filament\Pages\ReportsHub::getUrl(), 'label' => 'Reports', 'icon' => 'heroicon-o-chart-pie'],
                ] as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @class([
                            'ch-dock__link',
                            'ch-dock__link--active' => ! empty($link['active']) || request()->fullUrlIs($link['url'].'*'),
                        ])
                    >
                        <x-filament::icon :icon="$link['icon']" />
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
    </div>
</x-filament-panels::page>
