<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Reseller portal') — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/reseller-portal.css') }}?v=2">
    <script src="{{ asset('js/portal-theme.js') }}?v=1"></script>
</head>
<body class="rsl-bg antialiased text-slate-900">
    @auth('reseller')
        @php $reseller = auth('reseller')->user(); @endphp
        <header class="rsl-header">
            <div class="rsl-header-inner">
                <a href="{{ route('reseller.dashboard') }}" class="flex items-center gap-3">
                    <span class="rsl-brand-mark">R</span>
                    <div>
                        <p class="rsl-brand-title">{{ $reseller->brand_name ?: $reseller->name }}</p>
                        <p class="rsl-brand-sub">{{ $reseller->code }}</p>
                    </div>
                </a>
                <div class="flex items-center gap-2">
                    <button type="button" class="rsl-theme-btn" onclick="portalCycleTheme()" id="rsl-theme-btn">◐</button>
                    <span class="rsl-wallet-pill">{{ number_format((float) $reseller->wallet_balance, 0) }} BDT</span>
                    <form method="post" action="{{ route('reseller.logout') }}">
                        @csrf
                        <button type="submit" class="rsl-nav-link text-sm">Log out</button>
                    </form>
                </div>
            </div>
        </header>
        <nav class="rsl-dock lg:hidden">
            @foreach ([
                ['reseller.dashboard', 'Home'],
                ['reseller.customers.index', 'Subs'],
                ['reseller.commissions.index', 'Pay'],
            ] as [$route, $label])
                <a href="{{ route($route) }}" class="rsl-dock-link {{ request()->routeIs($route) ? 'rsl-dock-link--active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <nav class="rsl-nav-desktop hidden lg:flex">
            @foreach ([
                ['reseller.dashboard', 'Dashboard'],
                ['reseller.customers.index', 'Subscribers'],
                ['reseller.commissions.index', 'Commissions'],
            ] as [$route, $label])
                <a href="{{ route($route) }}" class="rsl-nav-link {{ request()->routeIs($route) ? 'rsl-nav-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
    @endauth

    <main class="rsl-main">
        @if (session('status'))
            <div class="rsl-alert rsl-alert-ok">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
    <script>
        function portalCycleTheme() {
            const order = ['light', 'dark', 'system'];
            const cur = window.portalGetTheme?.() || 'system';
            const next = order[(order.indexOf(cur) + 1) % order.length];
            window.portalSetTheme?.(next);
            document.documentElement.classList.toggle('rsl-dark', next === 'dark' || (next === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches));
            const btn = document.getElementById('rsl-theme-btn');
            if (btn) btn.textContent = { light: '☀️', dark: '🌙', system: '◐' }[next] || '◐';
        }
        portalCycleTheme();
    </script>
</body>
</html>
