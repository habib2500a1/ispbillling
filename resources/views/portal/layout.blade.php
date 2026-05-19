<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#312e81">
    <title>@yield('title', __('portal.customer_portal')) — {{ $companyName ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/css/portal.css', 'resources/js/app.js'])
    @endif
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { corePlugins: { preflight: false } };
    </script>
    <script src="{{ asset('js/portal-theme.js') }}?v=1"></script>
    @stack('head')
</head>
<body class="portal-body antialiased">
    @php
        $localeLabels = config('locales.labels', []);
    @endphp
    <div class="portal-locale-bar">
        <span class="portal-locale-label">{{ __('portal.language') }}:</span>
        @foreach (config('locales.supported', ['en']) as $code)
            <a href="{{ route('locale.switch', $code) }}" class="portal-locale-link {{ app()->getLocale() === $code ? 'portal-locale-link--active' : '' }}">
                {{ $localeLabels[$code] ?? strtoupper($code) }}
            </a>
        @endforeach
    </div>
    @auth('customer')
        @php
            $customer = auth('customer')->user();
            $navMain = [
                ['portal.dashboard', 'Home', 'heroicon-o-home'],
                ['portal.bills.index', 'Bills', 'heroicon-o-document-text'],
                ['portal.usage.index', 'Usage', 'heroicon-o-chart-bar'],
                ['portal.tickets.index', 'Support', 'heroicon-o-lifebuoy'],
                ['portal.profile.index', 'Profile', 'heroicon-o-user-circle'],
            ];
            $navSections = [
                'Overview' => [
                    ['portal.dashboard', 'Dashboard'],
                    ['portal.usage.index', 'Usage'],
                    ['portal.onu.index', 'ONU'],
                ],
                'Billing' => [
                    ['portal.bills.index', 'Bills'],
                    ['portal.payments.index', 'Payments'],
                    ['portal.packages.index', 'Package'],
                ],
                'Account' => [
                    ['portal.speed-test.index', 'Speed test'],
                    ['portal.tickets.index', 'Support'],
                    ['portal.profile.index', 'Settings'],
                ],
            ];
        @endphp
        <header class="portal-header">
            <div class="portal-header-inner">
                <a href="{{ route('portal.dashboard') }}" class="portal-brand">
                    @include('portal.partials.brand-mark')
                    <span class="portal-brand-text">{{ $companyName }}</span>
                </a>
                <nav class="portal-nav-desktop portal-nav-sections" aria-label="Main navigation">
                    @foreach ($navSections as $section => $links)
                        <div class="portal-nav-section">
                            <span class="portal-nav-section-label">{{ $section }}</span>
                            @foreach ($links as [$route, $label])
                                <a href="{{ route($route) }}" class="portal-nav-link {{ request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route)) ? 'portal-nav-active' : '' }}">{{ $label }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
                <div class="portal-header-actions">
                    <button type="button" class="portal-theme-btn" title="Theme" onclick="portalCycleTheme()" id="portal-theme-btn">◐</button>
                    <span class="portal-badge">{{ number_format((float) $customer->account_balance, 0) }} BDT</span>
                    <form method="post" action="{{ route('portal.logout') }}">
                        @csrf
                        <button type="submit" class="portal-nav-link text-sm">Log out</button>
                    </form>
                </div>
            </div>
        </header>
        <nav class="portal-nav-mobile" aria-label="Mobile navigation">
            @foreach ($navMain as [$route, $label, $icon])
                @php
                    $dockIcons = [
                        'Home' => '⌂',
                        'Bills' => '৳',
                        'Usage' => '↕',
                        'Support' => '✦',
                        'Profile' => '☺',
                    ];
                @endphp
                <a href="{{ route($route) }}" class="portal-dock-link {{ request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route)) ? 'portal-dock-link--active' : '' }}">
                    <span class="portal-dock-icon" aria-hidden="true">{{ $dockIcons[$label] ?? substr($label, 0, 1) }}</span>
                    <span class="portal-dock-label">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    @endauth

    <main class="portal-main @auth('customer') portal-main--app @else portal-main--guest @endauth">
        @if (session('status'))
            <div class="portal-alert portal-alert-ok">{{ session('status') }}</div>
        @endif
        @if (session('danger'))
            <div class="portal-alert portal-alert-err">{{ session('danger') }}</div>
        @endif
        @if (isset($errors) && $errors->any())
            <div class="portal-alert portal-alert-err">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="portal-card-shell">
            @yield('content')
        </div>
    </main>
    @stack('scripts')
    <script>
        function portalCycleTheme() {
            const order = ['light', 'dark', 'system'];
            const cur = window.portalGetTheme?.() || 'system';
            const next = order[(order.indexOf(cur) + 1) % order.length];
            window.portalSetTheme?.(next);
            portalThemeBtnLabel();
        }
        function portalThemeBtnLabel() {
            const btn = document.getElementById('portal-theme-btn');
            if (!btn) return;
            const m = window.portalGetTheme?.() || 'system';
            btn.textContent = { light: '☀️', dark: '🌙', system: '◐' }[m] || '◐';
        }
        window.addEventListener('portal-theme-changed', portalThemeBtnLabel);
        portalThemeBtnLabel();
    </script>
</body>
</html>
