<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#312e81">
    <title>Sign in — {{ $companyName }}</title>
    @if (! empty($logo))
        <link rel="icon" href="{{ $logo }}" />
    @else
        @include('partials.site-favicon')
    @endif
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ @filemtime(public_path('css/portal.css')) ?: 1 }}">
    @include('partials.isp-premium-theme', ['tailwind' => false])
    <link rel="stylesheet" href="{{ asset('css/login-hub.css') }}?v={{ @filemtime(public_path('css/login-hub.css')) ?: 1 }}">
    <script src="{{ asset('js/portal-theme.js') }}?v=1"></script>
</head>
<body class="portal-body antialiased isp-login-hub-page isp-premium-theme">
    @include('partials.demo-banner')
    <div class="portal-premium-orbs isp-premium-orbs" aria-hidden="true">
        <span></span><span></span><span></span>
    </div>

    <main class="isp-login-hub">
        <div class="isp-login-hub__panel isp-gradient-border">
            <div class="isp-gradient-border__inner isp-login-hub__panel-inner">
                @include('partials.demo-credentials-hint')
                <header class="isp-login-hub__head">
                    <div class="isp-login-hub__brand">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="" class="isp-login-hub__logo" width="64" height="64">
                        @else
                            <span class="isp-login-hub__mark" aria-hidden="true">{{ mb_substr($companyName, 0, 1) }}</span>
                        @endif
                    </div>
                    <p class="isp-login-hub__eyebrow">Secure access</p>
                    <h1 class="isp-login-hub__title">{{ $companyName }}</h1>
                    <p class="isp-login-hub__lead">Choose your portal — customer, staff, or partner</p>
                </header>

                <div class="isp-login-hub__grid" role="list">
                    @if ($portalEnabled)
                        <a href="{{ $customerLoginUrl }}" class="isp-login-hub__card isp-login-hub__card--customer" role="listitem">
                            <span class="isp-login-hub__card-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <span class="isp-login-hub__card-body">
                                <span class="isp-login-hub__card-badge">Customer</span>
                                <span class="isp-login-hub__card-title">Customer portal</span>
                                <span class="isp-login-hub__card-desc">Bills, usage, speed test, tickets &amp; connection</span>
                            </span>
                            <span class="isp-login-hub__card-arrow" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                            </span>
                        </a>
                    @endif

                    <a href="{{ $adminLoginUrl }}" class="isp-login-hub__card isp-login-hub__card--admin" role="listitem">
                        <span class="isp-login-hub__card-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </span>
                        <span class="isp-login-hub__card-body">
                            <span class="isp-login-hub__card-badge">Staff</span>
                            <span class="isp-login-hub__card-title">Admin / operations</span>
                            <span class="isp-login-hub__card-desc">Billing desk, subscribers, network &amp; reports</span>
                        </span>
                        <span class="isp-login-hub__card-arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                        </span>
                    </a>

                    @if ($resellerEnabled)
                        <a href="{{ $resellerLoginUrl }}" class="isp-login-hub__card isp-login-hub__card--reseller" role="listitem">
                            <span class="isp-login-hub__card-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </span>
                            <span class="isp-login-hub__card-body">
                                <span class="isp-login-hub__card-badge">Partner</span>
                                <span class="isp-login-hub__card-title">Reseller portal</span>
                                <span class="isp-login-hub__card-desc">Collections, due reports &amp; partner dashboard</span>
                            </span>
                            <span class="isp-login-hub__card-arrow" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                            </span>
                        </a>
                    @endif
                </div>

                <footer class="isp-login-hub__foot">
                    <a href="{{ $payUrl }}" class="isp-login-hub__chip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                        Pay bill without login
                    </a>
                    @if (config('portal.signup.enabled', true) && $portalEnabled)
                        <a href="{{ route('portal.signup') }}" class="isp-login-hub__chip isp-login-hub__chip--muted">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            New connection
                        </a>
                    @endif
                </footer>
            </div>
        </div>
    </main>
</body>
</html>
