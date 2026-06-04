<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title>@yield('title', 'Reseller portal') — {{ config('app.name') }}</title>
    @include('partials.site-favicon')
    @include('partials.reseller-theme-head')
    @php
        $rslPortalBuild = '2026.06.04-pro-en';
        $rslCssFile = 'css/reseller-portal-pro.css';
        $rslCssVer = (@filemtime(public_path($rslCssFile)) ?: time()).'-'.$rslPortalBuild;
        $rslJsVer = (@filemtime(public_path('js/portal-theme.js')) ?: time()).'-'.$rslPortalBuild;
    @endphp
    <link rel="stylesheet" href="{{ asset($rslCssFile) }}?v={{ $rslCssVer }}">
    @stack('styles')
    <script src="{{ asset('js/portal-theme.js') }}?v={{ $rslJsVer }}"></script>
</head>
<body class="rsl-page rsl-bg antialiased" data-portal-build="{{ $rslPortalBuild }}">
@auth('reseller')
    @php
        $reseller = auth('reseller')->user();
        $portal = $portal ?? app(\App\Support\ResellerPortalSession::class);
        $P = \App\Support\ResellerPortalPermission::class;

        $navIcons = [
            'reseller.dashboard' => 'home',
            'reseller.customers.index' => 'users',
            'reseller.wallet.index' => 'wallet',
            'reseller.commissions.index' => 'pay',
            'reseller.sub-resellers.index' => 'users',
            'reseller.hub' => 'hub',
            'reseller.reports.index' => 'chart',
        ];

        $navPrimary = array_filter([
            ['reseller.dashboard', 'Dashboard', ['reseller.dashboard']],
            $portal->canPortal($P::CUSTOMER_VIEW)
                ? ['reseller.customers.index', 'Subscribers', ['reseller.customers.*']] : null,
            $portal->canPortal($P::WALLET_VIEW)
                ? ['reseller.wallet.index', 'Wallet', ['reseller.wallet.*']] : null,
            $portal->canPortal($P::COMMISSION_VIEW)
                ? ['reseller.commissions.index', 'Commission', ['reseller.commissions.*']] : null,
            $portal->canPortal($P::SUB_RESELLER_VIEW)
                ? ['reseller.sub-resellers.index', 'Partners', ['reseller.sub-resellers.*']] : null,
            ['reseller.hub', 'Hub', ['reseller.hub', 'reseller.wallet.overview', 'reseller.due-account', 'reseller.reports.enterprise', 'reseller.customer-transfers.*', 'reseller.api-keys.*', 'reseller.branding.*', 'reseller.internal-tickets.*', 'reseller.announcements.*', 'reseller.security.*']],
            $portal->canPortal($P::REPORTS_VIEW)
                ? ['reseller.reports.index', 'Reports', ['reseller.reports.index', 'reseller.activity.*']] : null,
        ]);

        $navMore = array_filter([
            $portal->canPortal($P::SETTLEMENT_MANAGE)
                ? ['reseller.settlements.index', 'Settlements', ['reseller.settlements.*']] : null,
            $portal->canPortal($P::BILLING_VIEW)
                ? ['reseller.invoices.index', 'Bills', ['reseller.invoices.*']] : null,
            $portal->canPortal($P::ONU_VIEW)
                ? ['reseller.onu.index', 'ONU', ['reseller.onu.*']] : null,
            $portal->canPortal($P::NETWORK_VIEW)
                ? ['reseller.network.index', 'Network', ['reseller.network.*']] : null,
            $portal->canPortal($P::TICKET_CREATE)
                ? ['reseller.tickets.index', 'Tickets', ['reseller.tickets.*']] : null,
            $portal->canPortal($P::STAFF_MANAGE)
                ? ['reseller.staff.index', 'Staff', ['reseller.staff.*']] : null,
            (($reseller->own_integrations_enabled && $portal->canPortal($P::INTEGRATIONS_MANAGE)) || $reseller->white_label_enabled)
                ? ['reseller.settings.index', 'Settings', ['reseller.settings.*']] : null,
        ]);

        $navActive = function (array $patterns): bool {
            foreach ($patterns as $pattern) {
                if (request()->routeIs($pattern)) {
                    return true;
                }
            }
            return false;
        };

        $totalWallet = (float) $reseller->wallet_balance + (float) $reseller->bonus_wallet_balance;
        $navRouteExists = static fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
        $navPrimary = array_values(array_filter($navPrimary, static fn (array $item): bool => $navRouteExists($item[0])));
        $navMore = array_values(array_filter($navMore, static fn (array $item): bool => $navRouteExists($item[0])));

        $rslLogo = ($reseller->white_label_enabled && $reseller->logoUrl())
            ? $reseller->logoUrl()
            : \App\Support\CompanyBranding::logoUrl();
        $rslInitial = $reseller->white_label_enabled
            ? $reseller->brandInitial()
            : \App\Support\CompanyBranding::brandInitial();
        $walletUrl = \Illuminate\Support\Facades\Route::has('reseller.wallet.overview')
            ? route('reseller.wallet.overview')
            : route('reseller.wallet.index');
        $hqDue = (float) ($reseller->admin_receivable_due ?? 0);
        $showHqDue = $hqDue > 0.009
            && app(\App\Services\Resellers\ResellerDueLedgerService::class)->usesPostpaidDue($reseller);
        $unreadNotes = app(\App\Services\Resellers\ResellerPortalNotifier::class)->unreadCount($reseller);
    @endphp

    <div class="rsl-app">
        <aside class="rsl-sidebar rsl-only-desktop" aria-label="Sidebar">
            <div class="rsl-sidebar-header">
                <a href="{{ route('reseller.dashboard') }}" class="rsl-sidebar-brand">
                    @if ($rslLogo)
                        <img src="{{ $rslLogo }}" alt="" class="rsl-sidebar-brand-logo">
                    @else
                        <span class="rsl-sidebar-brand-mark">{{ $rslInitial }}</span>
                    @endif
                    <div class="rsl-sidebar-brand-text">
                        <p class="rsl-sidebar-brand-title">{{ $reseller->brand_name ?: $reseller->name }}</p>
                        <p class="rsl-sidebar-brand-sub">{{ $reseller->code }}</p>
                    </div>
                </a>
            </div>
            <nav class="rsl-sidebar-nav">
                <p class="rsl-sidebar-section-label">Menu</p>
                @foreach ($navPrimary as [$route, $label, $patterns])
                    <a href="{{ route($route) }}" class="rsl-sidebar-link {{ $navActive($patterns) ? 'rsl-sidebar-link--active' : '' }}">
                        @include('reseller.partials.nav-icons', ['name' => $navIcons[$route] ?? 'home'])
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
                @if (count($navMore) > 0)
                    <p class="rsl-sidebar-section-label">More</p>
                    @foreach ($navMore as [$route, $label, $patterns])
                        <a href="{{ route($route) }}" class="rsl-sidebar-link {{ $navActive($patterns) ? 'rsl-sidebar-link--active' : '' }}">
                            @include('reseller.partials.nav-icons', ['name' => $navIcons[$route] ?? 'hub'])
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                @endif
            </nav>
            <div class="rsl-sidebar-footer">
                <form method="post" action="{{ route('reseller.logout') }}">
                    @csrf
                    <button type="submit" class="rsl-sidebar-logout">Log out</button>
                </form>
            </div>
        </aside>

        <div class="rsl-app-content">
            <header class="rsl-appbar">
                <div class="rsl-appbar-inner">
                <a href="{{ route('reseller.dashboard') }}" class="rsl-brand-link rsl-only-mobile">
                    @if ($rslLogo)
                        <img src="{{ $rslLogo }}" alt="" class="rsl-brand-logo">
                    @else
                        <span class="rsl-brand-mark">{{ $rslInitial }}</span>
                    @endif
                    <div class="rsl-brand-text">
                        <p class="rsl-brand-title">{{ $reseller->brand_name ?: $reseller->name }}</p>
                    </div>
                </a>
                <p class="rsl-appbar-title rsl-only-desktop">@yield('title', 'Dashboard')</p>
                <div class="rsl-appbar-actions">
                    <button type="button" class="rsl-theme-btn" onclick="portalCycleTheme()" id="rsl-theme-btn" aria-label="Theme">◐</button>
                    <a href="{{ route('reseller.notifications.index') }}" class="rsl-theme-btn relative" aria-label="Notifications" style="text-decoration:none">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                        @if ($unreadNotes > 0)
                            <span style="position:absolute;right:-2px;top:-2px;min-width:1rem;height:1rem;padding:0 4px;border-radius:9999px;background:#dc2626;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center">{{ $unreadNotes > 9 ? '9+' : $unreadNotes }}</span>
                        @endif
                    </a>
                    @if ($portal->canPortal($P::WALLET_VIEW))
                        <a href="{{ $walletUrl }}" class="rsl-wallet-pill" title="Wallet">
                            <span style="display:block;font-size:10px;font-weight:600;opacity:.85">Wallet</span>
                            {{ number_format($totalWallet, 0) }} BDT
                        </a>
                        @if ($showHqDue && \Illuminate\Support\Facades\Route::has('reseller.due-account'))
                            <a href="{{ route('reseller.due-account') }}" class="rsl-wallet-pill rsl-wallet-pill--due" title="HQ due">
                                <span style="display:block;font-size:10px;font-weight:600;opacity:.9">HQ</span>
                                {{ number_format($hqDue, 0) }} BDT
                            </a>
                        @endif
                    @endif
                    <form method="post" action="{{ route('reseller.logout') }}" class="rsl-only-desktop">
                        @csrf
                        <button type="submit" class="rsl-btn rsl-btn--ghost rsl-btn-sm">Logout</button>
                    </form>
                </div>
                </div>
            </header>

            <main class="rsl-main">
                @if (session('status'))
                    <div class="rsl-alert rsl-alert-ok">{{ session('status') }}</div>
                @endif
                @if ($reseller->wallet_frozen)
                    <div class="rsl-alert rsl-alert-warn">Wallet frozen — settlements disabled.</div>
                @endif
                @php $ledger = app(\App\Services\Resellers\ResellerWalletLedgerService::class); @endphp
                @if ($ledger->isLowBalance($reseller) && ! request()->routeIs('reseller.wallet.*'))
                    <div class="rsl-alert rsl-alert-danger">
                        Low balance ({{ number_format((float) $reseller->wallet_balance, 0) }} BDT).
                        <a href="{{ route('reseller.wallet.index') }}" class="rsl-link">Top up</a>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>

        <nav class="rsl-dock rsl-only-mobile" aria-label="Mobile navigation">
            @foreach (array_slice($navPrimary, 0, 5) as [$route, $label, $patterns])
                <a href="{{ route($route) }}" class="rsl-dock-link {{ $navActive($patterns) ? 'rsl-dock-link--active' : '' }}">
                    @include('reseller.partials.nav-icons', ['name' => $navIcons[$route] ?? 'home'])
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <p class="rsl-portal-build" aria-hidden="true">{{ $rslPortalBuild }}</p>
    <script>
        (function () {
            const pollUrl = "{{ route('reseller.realtime.poll') }}";
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
@else
    <main class="rsl-main">@yield('content')</main>
@endauth

<script>
    function portalApplyThemeButton(theme) {
        const btn = document.getElementById('rsl-theme-btn');
        if (btn) btn.textContent = { light: '☀️', dark: '🌙', system: '◐' }[theme] || '◐';
    }
    function portalApplyTheme(theme) {
        const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('rsl-dark', dark);
        portalApplyThemeButton(theme);
    }
    function portalCycleTheme() {
        const order = ['light', 'dark', 'system'];
        const cur = window.portalGetTheme?.() || 'system';
        const next = order[(order.indexOf(cur) + 1) % order.length];
        window.portalSetTheme?.(next);
        portalApplyTheme(next);
    }
    portalApplyTheme(window.portalGetTheme?.() || 'system');
    window.addEventListener('portal-theme-changed', (e) => portalApplyTheme(e.detail.mode));
</script>
@stack('scripts')
</body>
</html>
