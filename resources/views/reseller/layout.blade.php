<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Reseller portal') — {{ config('app.name') }}</title>
    @include('partials.site-favicon')
    @include('partials.isp-premium-theme', ['tailwind' => true])
    <link rel="stylesheet" href="{{ asset('css/reseller-portal.css') }}?v=4">
    <script src="{{ asset('js/portal-theme.js') }}?v=1"></script>
</head>
<body class="rsl-page rsl-bg antialiased">
    @auth('reseller')
        @php
            $reseller = auth('reseller')->user();
            $portal = $portal ?? app(\App\Support\ResellerPortalSession::class);
            $nav = array_filter([
                ['reseller.dashboard', 'Home', null],
                $portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_VIEW)
                    ? ['reseller.customers.index', 'Subs', \App\Support\ResellerPortalPermission::CUSTOMER_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::COMMISSION_VIEW)
                    ? ['reseller.commissions.index', 'Pay', \App\Support\ResellerPortalPermission::COMMISSION_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW)
                    ? ['reseller.wallet.index', 'Wallet', \App\Support\ResellerPortalPermission::WALLET_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::SETTLEMENT_MANAGE)
                    ? ['reseller.settlements.index', 'Settle', \App\Support\ResellerPortalPermission::SETTLEMENT_MANAGE] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::ONU_VIEW)
                    ? ['reseller.onu.index', 'ONU', \App\Support\ResellerPortalPermission::ONU_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::NETWORK_VIEW)
                    ? ['reseller.network.index', 'Net', \App\Support\ResellerPortalPermission::NETWORK_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::SUB_RESELLER_VIEW)
                    ? ['reseller.sub-resellers.index', 'Partners', \App\Support\ResellerPortalPermission::SUB_RESELLER_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::BILLING_VIEW)
                    ? ['reseller.invoices.index', 'Bills', \App\Support\ResellerPortalPermission::BILLING_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::REPORTS_VIEW)
                    ? ['reseller.reports.index', 'Reports', \App\Support\ResellerPortalPermission::REPORTS_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::REPORTS_VIEW)
                    ? ['reseller.activity.index', 'Activity', \App\Support\ResellerPortalPermission::REPORTS_VIEW] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::TICKET_CREATE)
                    ? ['reseller.tickets.index', 'Tickets', \App\Support\ResellerPortalPermission::TICKET_CREATE] : null,
                $portal->canPortal(\App\Support\ResellerPortalPermission::STAFF_MANAGE)
                    ? ['reseller.staff.index', 'Staff', \App\Support\ResellerPortalPermission::STAFF_MANAGE] : null,
                (($reseller->own_integrations_enabled && $portal->canPortal(\App\Support\ResellerPortalPermission::INTEGRATIONS_MANAGE)) || $reseller->white_label_enabled)
                    ? ['reseller.settings.index', 'Settings', null] : null,
            ]);
        @endphp
        <div class="rsl-topbar">
            <header class="rsl-header">
                <div class="rsl-header-inner">
                    <a href="{{ route('reseller.dashboard') }}" class="rsl-brand-link">
                        @php
                            $rslLogo = ($reseller->white_label_enabled && $reseller->logoUrl())
                                ? $reseller->logoUrl()
                                : \App\Support\CompanyBranding::logoUrl();
                            $rslInitial = $reseller->white_label_enabled
                                ? $reseller->brandInitial()
                                : \App\Support\CompanyBranding::brandInitial();
                        @endphp
                        @if ($rslLogo)
                            <img src="{{ $rslLogo }}" alt="" class="rsl-brand-logo" />
                        @else
                            <span class="rsl-brand-mark">{{ $rslInitial }}</span>
                        @endif
                        <div class="rsl-brand-text">
                            <p class="rsl-brand-title">{{ $reseller->brand_name ?: $reseller->name }}</p>
                            <p class="rsl-brand-sub">{{ $reseller->code }} · {{ $reseller->franchiseTypeLabel() }}@if ($portal->staff()) · {{ $portal->actorName() }}@endif</p>
                        </div>
                    </a>
                    <div class="rsl-header-actions">
                        <button type="button" class="rsl-theme-btn" onclick="portalCycleTheme()" id="rsl-theme-btn" aria-label="Toggle theme">◐</button>
                        @php $unreadNotes = app(\App\Services\Resellers\ResellerPortalNotifier::class)->unreadCount($reseller); @endphp
                        <a href="{{ route('reseller.notifications.index') }}" class="rsl-theme-btn relative no-underline" aria-label="Notifications" title="Notifications">
                            🔔@if ($unreadNotes > 0)<span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $unreadNotes > 9 ? '9+' : $unreadNotes }}</span>@endif
                        </a>
                        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::WALLET_VIEW))
                            <a href="{{ route('reseller.wallet.index') }}" class="rsl-wallet-pill">{{ number_format((float) $reseller->wallet_balance, 0) }} BDT</a>
                        @endif
                        <form method="post" action="{{ route('reseller.logout') }}">
                            @csrf
                            <button type="submit" class="rsl-logout-btn">Log out</button>
                        </form>
                    </div>
                </div>
            </header>
            <nav class="rsl-nav-desktop hidden lg:flex" aria-label="Partner navigation">
                @foreach ($nav as [$route, $label])
                    <a href="{{ route($route) }}" class="rsl-nav-link {{ request()->routeIs($route) ? 'rsl-nav-active' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <nav class="rsl-dock lg:hidden" aria-label="Partner navigation">
            @foreach ($nav as [$route, $label])
                <a href="{{ route($route) }}" class="rsl-dock-link {{ request()->routeIs($route) ? 'rsl-dock-link--active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
    @endauth

    <main class="rsl-main">
        @if (session('status'))
            <div class="rsl-alert rsl-alert-ok">{{ session('status') }}</div>
        @endif
        @auth('reseller')
            @if (auth('reseller')->user()->wallet_frozen)
                <div class="rsl-alert mb-4 border border-amber-300 bg-amber-50 text-amber-900">Your wallet is frozen. Settlement requests are blocked until admin unfreezes it.</div>
            @endif
        @endauth
        @yield('content')
    </main>
    @auth('reseller')
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
    @endauth
    <script>
        function portalApplyThemeButton(theme) {
            const btn = document.getElementById('rsl-theme-btn');
            if (btn) btn.textContent = { light: '☀️', dark: '🌙', system: '◐' }[theme] || '◐';
        }

        function portalApplyTheme(theme) {
            const effectiveDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('rsl-dark', effectiveDark);
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
</body>
</html>
