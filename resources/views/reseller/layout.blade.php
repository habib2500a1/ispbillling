<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Reseller portal') — {{ config('app.name') }}</title>
    @include('partials.site-favicon')
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/reseller-portal.css') }}?v=3">
    <script src="{{ asset('js/portal-theme.js') }}?v=1"></script>
</head>
<body class="rsl-bg antialiased text-slate-900">
    @auth('reseller')
        @php
            $reseller = auth('reseller')->user();
            $nav = array_filter([
                ['reseller.dashboard', 'Home', null],
                $reseller->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_VIEW)
                    ? ['reseller.customers.index', 'Subs', \App\Support\ResellerPortalPermission::CUSTOMER_VIEW] : null,
                $reseller->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW)
                    ? ['reseller.commissions.index', 'Pay', \App\Support\ResellerPortalPermission::COMMISSION_VIEW] : null,
                $reseller->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW)
                    ? ['reseller.wallet.index', 'Wallet', \App\Support\ResellerPortalPermission::WALLET_VIEW] : null,
                $reseller->canPortal(\App\Support\ResellerPortalPermission::SETTLEMENT_MANAGE)
                    ? ['reseller.settlements.index', 'Settle', \App\Support\ResellerPortalPermission::SETTLEMENT_MANAGE] : null,
                $reseller->canPortal(\App\Support\ResellerPortalPermission::ONU_VIEW)
                    ? ['reseller.onu.index', 'ONU', \App\Support\ResellerPortalPermission::ONU_VIEW] : null,
            ]);
        @endphp
        <header class="rsl-header">
            <div class="rsl-header-inner">
                <a href="{{ route('reseller.dashboard') }}" class="flex items-center gap-3">
                    @php $rslLogo = \App\Support\CompanyBranding::logoUrl(); @endphp
                    @if ($rslLogo)
                        <img src="{{ $rslLogo }}" alt="" class="rsl-brand-logo" />
                    @else
                        <span class="rsl-brand-mark">{{ \App\Support\CompanyBranding::brandInitial() }}</span>
                    @endif
                    <div>
                        <p class="rsl-brand-title">{{ $reseller->brand_name ?: $reseller->name }}</p>
                        <p class="rsl-brand-sub">{{ $reseller->code }} · {{ $reseller->franchiseTypeLabel() }}</p>
                    </div>
                </a>
                <div class="flex items-center gap-2">
                    <button type="button" class="rsl-theme-btn" onclick="portalCycleTheme()" id="rsl-theme-btn">◐</button>
                    @if ($reseller->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW))
                        <a href="{{ route('reseller.wallet.index') }}" class="rsl-wallet-pill">{{ number_format((float) $reseller->wallet_balance, 0) }} BDT</a>
                    @endif
                    <form method="post" action="{{ route('reseller.logout') }}">
                        @csrf
                        <button type="submit" class="rsl-nav-link text-sm">Log out</button>
                    </form>
                </div>
            </div>
        </header>
        <nav class="rsl-dock lg:hidden">
            @foreach ($nav as [$route, $label])
                <a href="{{ route($route) }}" class="rsl-dock-link {{ request()->routeIs($route) ? 'rsl-dock-link--active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <nav class="rsl-nav-desktop hidden lg:flex">
            @foreach ($nav as [$route, $label])
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
    @auth('reseller')
        <script>
            (function () {
                const pollUrl = @json(route('reseller.realtime.poll'));
                let since = new Date().toISOString();
                setInterval(async () => {
                    try {
                        const r = await fetch(pollUrl + '?since=' + encodeURIComponent(since), { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) return;
                        const data = await r.json();
                        since = data.server_time || since;
                        if ((data.payments || []).length > 0) {
                            document.dispatchEvent(new CustomEvent('reseller:payment', { detail: data }));
                        }
                    } catch (e) {}
                }, 20000);
            })();
        </script>
    @endauth
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
