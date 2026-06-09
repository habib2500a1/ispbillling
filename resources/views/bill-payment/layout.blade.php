<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script data-cfasync="false">
        (function () {
            var stored = localStorage.getItem('isp-portal-theme');
            var mode = stored === 'dark' ? 'dark' : 'light';
            var html = document.documentElement;
            html.classList.toggle('portal-dark', mode === 'dark');
            html.setAttribute('data-portal-theme', mode);
        })();
    </script>
    <title>@yield('title', 'Bill payment') — {{ $companyName ?? config('isp.company_name') }}</title>
    @if (! empty($companyLogo))
        <link rel="icon" href="{{ $companyLogo }}" />
        <link rel="apple-touch-icon" href="{{ $companyLogo }}" />
    @else
        @include('partials.site-favicon')
    @endif
    @php
        $bpCssV = @filemtime(public_path('css/bill-payment.css')) ?: time();
    @endphp
    @include('partials.isp-premium-theme', ['tailwind' => false])
    <link rel="stylesheet" href="{{ asset('css/bill-payment.css') }}?v={{ $bpCssV }}">
    @if (! empty($whiteLabelPrimaryColor))
        <style>:root { --bp-teal: {{ $whiteLabelPrimaryColor }}; --bp-teal-dark: {{ $whiteLabelPrimaryColor }}; }</style>
    @endif
</head>
<body class="bp-bg antialiased">
    @include('partials.demo-banner')
    <header class="bp-topbar">
        <div class="bp-topbar-inner">
            <div>
                <p class="bp-topbar-kicker">Online payment</p>
                <p class="bp-topbar-title">{{ $companyName ?? config('isp.company_name') }}</p>
            </div>
            <button
                type="button"
                class="bp-theme-pill"
                onclick="portalCycleTheme()"
                id="bp-theme-btn"
                title="Light mode — tap to switch"
                aria-label="Toggle light and dark mode"
                aria-pressed="false"
            >
                <span class="bp-theme-pill__icon" id="bp-theme-icon" aria-hidden="true">☀️</span>
                <span class="bp-theme-pill__text" id="bp-theme-label">Light mode</span>
            </button>
        </div>
    </header>
    <div class="bp-split">
        <aside class="bp-brand">
            <div class="bp-brand__inner">
                @php
                    $bpLogo = $companyLogo ?? \App\Support\CompanyBranding::logoUrl();
                    $bpCustomer = \App\Support\ResellerBranding::customerFromContext();
                    $bpMethods = \App\Support\PortalPaymentGateways::methodsForPublicBillPay($bpCustomer);
                @endphp
                @if ($bpLogo)
                    <img src="{{ $bpLogo }}" alt="{{ $companyName ?? config('isp.company_name') }}" class="bp-brand-logo" />
                @endif
                <p class="bp-brand-kicker">Online payment</p>
                <h1 class="bp-brand-title">{{ $companyName ?? config('isp.company_name') }}</h1>
                <p class="bp-brand-tagline">{{ $companyTagline ?? config('isp.company_tagline') }}</p>
                <ol class="bp-brand-steps">
                    <li>Enter your client code</li>
                    @if ($otpEnabled ?? false)
                        <li>Verify mobile OTP</li>
                    @endif
                    <li>Review invoice &amp; due amount</li>
                    @php
                        $bpLabels = array_map(
                            fn (array $m): string => $m['label'].' ('.$m['badge'].')',
                            $bpMethods,
                        );
                    @endphp
                    <li>
                        @if ($bpLabels !== [])
                            Pay via {{ implode(' · ', $bpLabels) }}
                        @else
                            Pay online when enabled by your ISP
                        @endif
                    </li>
                </ol>
            </div>
            <p class="bp-brand-foot">Secure bill payment · No login required</p>
        </aside>
        <main class="bp-main">
            @php
                $bpLogoMobile = $companyLogo ?? \App\Support\CompanyBranding::logoUrl();
            @endphp
            @if ($bpLogoMobile)
                <div class="bp-mobile-brand">
                    <img src="{{ $bpLogoMobile }}" alt="{{ $companyName ?? config('isp.company_name') }}" class="bp-mobile-brand__logo" />
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    @php
        $bpJsV = @filemtime(public_path('js/bill-pay-theme.js')) ?: time();
    @endphp
    <script src="{{ asset('js/bill-pay-theme.js') }}?v={{ $bpJsV }}" defer data-cfasync="false"></script>
    @stack('scripts')
</body>
</html>
