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
    @include('partials.isp-premium-theme', ['tailwind' => true])
    @php
        $rslPortalBuild = '2026.06.04-dash-v2';
        $rslCssFile = 'css/reseller-portal-v2.css';
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

            $navPrimary = array_filter([
                ['reseller.dashboard', 'Home', ['reseller.dashboard']],
                $portal->canPortal($P::CUSTOMER_VIEW)
                    ? ['reseller.customers.index', 'Subs', ['reseller.customers.*']] : null,
                $portal->canPortal($P::WALLET_VIEW)
                    ? ['reseller.wallet.index', 'Wallet', ['reseller.wallet.*']] : null,
                $portal->canPortal($P::COMMISSION_VIEW)
                    ? ['reseller.commissions.index', 'Pay', ['reseller.commissions.*']] : null,
                $portal->canPortal($P::SUB_RESELLER_VIEW)
                    ? ['reseller.sub-resellers.index', 'Partners', ['reseller.sub-resellers.*']] : null,
                ['reseller.hub', 'Hub', ['reseller.hub', 'reseller.wallet.overview', 'reseller.due-account', 'reseller.reports.enterprise', 'reseller.customer-transfers.*', 'reseller.api-keys.*', 'reseller.branding.*', 'reseller.internal-tickets.*', 'reseller.announcements.*', 'reseller.security.*']],
                $portal->canPortal($P::REPORTS_VIEW)
                    ? ['reseller.reports.index', 'Reports', ['reseller.reports.index', 'reseller.activity.*']] : null,
            ]);

            $navMore = array_filter([
                $portal->canPortal($P::SETTLEMENT_MANAGE)
                    ? ['reseller.settlements.index', 'Settle', ['reseller.settlements.*']] : null,
                $portal->canPortal($P::BILLING_VIEW)
                    ? ['reseller.invoices.index', 'Bills', ['reseller.invoices.*']] : null,
                $portal->canPortal($P::ONU_VIEW)
                    ? ['reseller.onu.index', 'ONU', ['reseller.onu.*']] : null,
                $portal->canPortal($P::NETWORK_VIEW)
                    ? ['reseller.network.index', 'Net', ['reseller.network.*']] : null,
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

            $navPrimary = array_values(array_filter(
                $navPrimary,
                static fn (array $item): bool => $navRouteExists($item[0]),
            ));

            $navMore = array_values(array_filter(
                $navMore,
                static fn (array $item): bool => $navRouteExists($item[0]),
            ));
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
                            <p class="rsl-brand-sub">
                                <span class="rsl-tier-badge">Enterprise</span>
                                {{ $reseller->code }} · {{ $reseller->franchiseTypeLabel() }}
                                @if ($portal->staff())
                                    · {{ $portal->actorName() }}
                                @endif
                            </p>
                        </div>
                    </a>
                    <div class="rsl-header-actions">
                        <button type="button" class="rsl-theme-btn" onclick="portalCycleTheme()" id="rsl-theme-btn" aria-label="Toggle theme">◐</button>
                        @php $unreadNotes = app(\App\Services\Resellers\ResellerPortalNotifier::class)->unreadCount($reseller); @endphp
                        <a href="{{ route('reseller.notifications.index') }}" class="rsl-theme-btn relative no-underline" aria-label="Notifications" title="Notifications">
                            🔔
                            @if ($unreadNotes > 0)
                                <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $unreadNotes > 9 ? '9+' : $unreadNotes }}</span>
                            @endif
                        </a>
                        @if ($portal->canPortal($P::WALLET_VIEW))
                            @php
                                $walletTitle = 'Wallet — Main '.number_format((float) $reseller->wallet_balance, 0);
                                if ((float) $reseller->bonus_wallet_balance > 0) {
                                    $walletTitle .= ' · Bonus '.number_format((float) $reseller->bonus_wallet_balance, 0);
                                }
                                $walletTitle .= ' BDT';
                                $hqDue = (float) ($reseller->admin_receivable_due ?? 0);
                                $showHqDue = $hqDue > 0.009
                                    && app(\App\Services\Resellers\ResellerDueLedgerService::class)->usesPostpaidDue($reseller);
                            @endphp
                            <a href="{{ \Illuminate\Support\Facades\Route::has('reseller.wallet.overview') ? route('reseller.wallet.overview') : route('reseller.wallet.index') }}" class="rsl-wallet-pill" title="{{ $walletTitle }}">
                                <span class="text-[10px] font-semibold uppercase opacity-80">Wallet</span>
                                {{ number_format($totalWallet, 0) }} BDT
                            </a>
                            @if ($showHqDue && \Illuminate\Support\Facades\Route::has('reseller.due-account'))
                                <a href="{{ route('reseller.due-account') }}" class="rsl-wallet-pill rsl-wallet-pill--due" title="Amount owed to HQ — view paid and due in Due account">
                                    <span class="text-[10px] font-semibold uppercase opacity-90">HQ due</span>
                                    {{ number_format($hqDue, 0) }} BDT
                                </a>
                            @endif
                        @endif
                        <form method="post" action="{{ route('reseller.logout') }}">
                            @csrf
                            <button type="submit" class="rsl-logout-btn">Log out</button>
                        </form>
                    </div>
                </div>
            </header>
            <nav class="rsl-nav-desktop hidden lg:flex" aria-label="Partner navigation">
                @foreach ($navPrimary as [$route, $label, $patterns])
                    <a href="{{ route($route) }}" class="rsl-nav-link {{ $navActive($patterns) ? 'rsl-nav-active' : '' }}">{{ $label }}</a>
                @endforeach
                @if (count($navMore) > 0)
                    <div class="rsl-nav-more">
                        <span class="rsl-nav-more-label">More</span>
                        @foreach ($navMore as [$route, $label, $patterns])
                            <a href="{{ route($route) }}" class="rsl-nav-link rsl-nav-link--sm {{ $navActive($patterns) ? 'rsl-nav-active' : '' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                @endif
            </nav>
        </div>
        <nav class="rsl-dock lg:hidden" aria-label="Partner navigation">
            @foreach ($navPrimary as [$route, $label, $patterns])
                <a href="{{ route($route) }}" class="rsl-dock-link {{ $navActive($patterns) ? 'rsl-dock-link--active' : '' }}">{{ $label }}</a>
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
            @php
                $ledger = app(\App\Services\Resellers\ResellerWalletLedgerService::class);
                $rslUser = auth('reseller')->user();
            @endphp
            @if ($ledger->isLowBalance($rslUser) && ! request()->routeIs('reseller.wallet.*'))
                <div class="rsl-alert mb-4 border border-rose-200 bg-rose-50 text-rose-900">
                    Low wallet balance ({{ number_format((float) $rslUser->wallet_balance, 0) }} BDT).
                    <a href="{{ route('reseller.wallet.index') }}" class="font-semibold underline">Top up now</a>
                </div>
            @endif
        @endauth
        @yield('content')
    </main>
    @auth('reseller')
        <p class="rsl-portal-build" aria-hidden="true">{{ $rslPortalBuild ?? '' }}</p>
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
