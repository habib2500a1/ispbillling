<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi min-h-screen">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <title>Login - {{ $companyName }}</title>

    <style>
        [x-cloak=''],
        [x-cloak='x-cloak'],
        [x-cloak='1'] {
            display: none !important;
        }
        @media (max-width: 1023px) {
            [x-cloak='-lg'] { display: none !important; }
        }
        @media (min-width: 1024px) {
            [x-cloak='lg'] { display: none !important; }
        }
    </style>

    {!! \Filament\Support\Facades\FilamentAsset::renderStyles() !!}
    {!! $filamentThemeHtml !!}
    {!! $filamentFontHtml !!}

    <style>
        :root {
            --font-family: '{!! $fontFamily !!}';
            --sidebar-width: 20rem;
            --collapsed-sidebar-width: 4.5rem;
            --default-theme-mode: system;
        }
    </style>

    @include('filament.hooks.auth-page-assets')
</head>
<body class="fi-body fi-panel-admin min-h-screen bg-gray-50 font-normal text-gray-950 antialiased dark:bg-gray-950 dark:text-white">
    <div class="isp-premium-orbs" aria-hidden="true">
        <span></span><span></span><span></span>
    </div>
    <div class="isp-auth-split min-h-screen">
        <aside class="isp-auth-split__brand">
            <div class="isp-auth-split__brand-inner">
                @if ($companyLogo)
                    <img src="{{ $companyLogo }}" alt="" class="isp-auth-split__logo mb-6 max-h-16 w-auto">
                @else
                    <div class="isp-auth-split__logo-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2" opacity="0.35"/>
                            <path d="M14 28 L24 14 L34 28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M18 32 H30" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                @endif
                <h1 class="isp-auth-split__company">{{ $companyName }}</h1>
                <p class="isp-auth-split__tagline">{{ $companyTagline }}</p>
                <ul class="isp-auth-split__features mt-10 space-y-3 text-sm text-white/75">
                    <li>Billing · payments · auto disconnect</li>
                    <li>MikroTik · GPON · live monitoring</li>
                    <li>Support tickets · SMS · customer portal</li>
                    <li>Accounting · HR · resellers · BTRC</li>
                </ul>
            </div>
            <p class="isp-auth-split__footer text-xs text-white/40">Secure admin access</p>
        </aside>

        <main class="isp-auth-split__form">
            <div class="w-full max-w-md">
                @include('filament.pages.auth.admin-login')
            </div>
        </main>
    </div>
</body>
</html>
