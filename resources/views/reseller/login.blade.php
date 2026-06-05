<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Reseller login — {{ config('app.name') }}</title>
    @include('partials.site-favicon')
    @include('partials.reseller-theme-head')
    @php
        $rslPortalBuild = '2026.06.04-pro-qa5';
        $loginCssVer = (@filemtime(public_path('css/reseller-portal-pro.css')) ?: time()).'-'.$rslPortalBuild;
        $loginCompatVer = (@filemtime(public_path('css/reseller-portal-compat.css')) ?: time()).'-'.$rslPortalBuild;
        $loginCssHref = '/css/reseller-portal-pro.css?v='.$loginCssVer;
        $loginCompatHref = '/css/reseller-portal-compat.css?v='.$loginCompatVer;
        $wl = app()->bound('reseller.white_label') ? app('reseller.white_label') : null;
        $companyName = $wl?->brand_name ?: config('app.name');
        $logoUrl = $wl?->logoUrl() ?: \App\Support\CompanyBranding::logoUrl();
        $initial = $wl?->brandInitial() ?: \App\Support\CompanyBranding::brandInitial();
    @endphp
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <link rel="preload" href="{{ $loginCssHref }}" as="style">
    <link rel="stylesheet" href="{{ $loginCssHref }}" data-rsl-build="{{ $rslPortalBuild }}">
    <link rel="stylesheet" href="{{ $loginCompatHref }}" data-rsl-build="{{ $rslPortalBuild }}">
    @include('reseller.partials.critical-css')
    <script src="{{ asset('js/portal-theme.js') }}?v={{ $loginCssVer }}"></script>
</head>
<body class="rsl-page rsl-login-page">
    <div class="rsl-login-shell">
        <aside class="rsl-login-brand-panel" aria-hidden="true">
            <div class="rsl-login-brand-inner">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="rsl-login-brand-logo">
                @else
                    <span class="rsl-login-brand-mark">{{ $initial }}</span>
                @endif
                <h2 class="rsl-login-brand-title">{{ $companyName }}</h2>
                <p class="rsl-login-brand-tagline">Enterprise partner portal</p>
                <ul class="rsl-login-features">
                    <li><span class="rsl-login-feature-dot"></span> Due collection & live reports</li>
                    <li><span class="rsl-login-feature-dot"></span> Mobile-friendly dashboard</li>
                    <li><span class="rsl-login-feature-dot"></span> Secure partner login</li>
                </ul>
            </div>
        </aside>

        <main class="rsl-login-main">
            <div class="rsl-login-hero-mobile">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="rsl-login-mobile-logo">
                @else
                    <span class="rsl-login-brand-mark">{{ $initial }}</span>
                @endif
                <h2 class="rsl-login-hero-title">{{ $companyName }}</h2>
                <p>Enterprise partner portal</p>
            </div>

            <div class="rsl-login-glass">
                <div class="rsl-login-card-head">
                    <button type="button" class="rsl-login-theme" onclick="portalCycleTheme()" id="rsl-login-theme" aria-label="Theme">◐</button>
                </div>
                <div class="rsl-login-card-body">
                    <h1 class="rsl-login-title">Partner login</h1>
                    <p class="rsl-login-sub">Sign in with partner ID, email, or phone</p>

                    @if ($wl && filled($wl->portal_login_message))
                        <p class="rsl-login-wl-msg">{{ $wl->portal_login_message }}</p>
                    @endif
                    @if ($errors->any())
                        <div class="rsl-login-error" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="post" action="{{ route('reseller.login.store') }}" class="rsl-login-form">
                        @csrf
                        <label class="rsl-login-label" for="login">Partner ID</label>
                        <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus class="rsl-input rsl-login-input" placeholder="RSL-0001 or email" autocomplete="username">

                        <label class="rsl-login-label" for="password">Password</label>
                        <input id="password" name="password" type="password" required class="rsl-input rsl-login-input" autocomplete="current-password">

                        <label class="rsl-login-remember">
                            <input type="checkbox" name="remember" value="1">
                            <span>Remember me</span>
                        </label>

                        <button type="submit" class="rsl-btn rsl-login-submit">Sign in</button>
                    </form>
                </div>
            </div>

            <p class="rsl-login-hub-link"><a href="{{ route('login.hub') }}">← All sign-in options</a></p>
            <p class="rsl-login-foot">&copy; {{ date('Y') }} {{ $companyName }} · <span class="rsl-login-build">{{ $rslPortalBuild }}</span></p>
        </main>
    </div>
    <script>
        function portalApplyTheme(theme) {
            const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('rsl-dark', dark);
            const btn = document.getElementById('rsl-login-theme');
            if (btn) btn.textContent = { light: '☀️', dark: '🌙', system: '◐' }[theme] || '◐';
        }
        function portalCycleTheme() {
            const order = ['light', 'dark', 'system'];
            const cur = window.portalGetTheme?.() || 'system';
            const next = order[(order.indexOf(cur) + 1) % order.length];
            window.portalSetTheme?.(next);
            portalApplyTheme(next);
        }
        portalApplyTheme(window.portalGetTheme?.() || 'system');
    </script>
</body>
</html>
