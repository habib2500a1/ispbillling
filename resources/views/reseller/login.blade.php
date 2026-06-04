<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Reseller login — {{ config('app.name') }}</title>
    @include('partials.site-favicon')
    @include('partials.isp-premium-theme', ['tailwind' => true])
    @php
        $rslPortalBuild = '2026.06.04-dash-v2';
        $loginCssVer = (@filemtime(public_path('css/reseller-portal-v2.css')) ?: time()).'-'.$rslPortalBuild;
    @endphp
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <link rel="stylesheet" href="{{ asset('css/reseller-portal-v2.css') }}?v={{ $loginCssVer }}">
    <script src="{{ asset('js/portal-theme.js') }}?v={{ $loginCssVer }}"></script>
</head>
@php
    $wl = app()->bound('reseller.white_label') ? app('reseller.white_label') : null;
    $companyName = $wl?->brand_name ?: config('app.name');
    $logoUrl = $wl?->logoUrl() ?: \App\Support\CompanyBranding::logoUrl();
    $initial = $wl?->brandInitial() ?: \App\Support\CompanyBranding::brandInitial();
@endphp
<body class="rsl-page rsl-bg rsl-login-page">
    <div class="rsl-login-shell">
        <aside class="rsl-login-brand" aria-hidden="true">
            <div class="rsl-login-brand-inner">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="rsl-login-brand-logo">
                @else
                    <span class="rsl-login-brand-mark">{{ $initial }}</span>
                @endif
                <h2 class="rsl-login-brand-title">{{ $companyName }}</h2>
                <p class="rsl-login-brand-tagline">Enterprise partner portal — collect dues, manage subscribers, and track wallet in one place.</p>
                <ul class="rsl-login-features">
                    <li><span class="rsl-login-feature-dot"></span> Real-time collection &amp; commission</li>
                    <li><span class="rsl-login-feature-dot"></span> Mobile-first dashboard</li>
                    <li><span class="rsl-login-feature-dot"></span> Secure partner login</li>
                </ul>
            </div>
        </aside>

        <main class="rsl-login-main">
            <div class="rsl-login-card">
                <div class="rsl-login-card-head">
                    <button type="button" class="rsl-login-theme" onclick="portalCycleTheme()" id="rsl-login-theme" aria-label="Toggle theme">◐</button>
                </div>

                <div class="rsl-login-card-body">
                    <div class="rsl-login-mobile-brand">
                        @if ($logoUrl ?? false)
                            <img src="{{ $logoUrl }}" alt="" class="rsl-login-mobile-logo">
                        @else
                            <span class="rsl-brand-mark">{{ $initial ?? 'R' }}</span>
                        @endif
                    </div>
                    <h1 class="rsl-login-title">Partner sign in</h1>
                    <p class="rsl-login-sub">পার্টনার কোড, ইমেইল বা ফোন দিয়ে লগইন করুন</p>

                    @if ($wl && filled($wl->portal_login_message))
                        <p class="rsl-login-wl-msg">{{ $wl->portal_login_message }}</p>
                    @endif

                    @if ($errors->any())
                        <div class="rsl-login-error" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="post" action="{{ route('reseller.login.store') }}" class="rsl-login-form">
                        @csrf
                        <div class="rsl-login-field">
                            <label class="rsl-login-label" for="login">Partner ID</label>
                            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus class="rsl-input rsl-login-input" placeholder="RSL-2605-0001 or email" autocomplete="username">
                        </div>
                        <div class="rsl-login-field">
                            <label class="rsl-login-label" for="password">Password</label>
                            <input id="password" name="password" type="password" required class="rsl-input rsl-login-input" autocomplete="current-password">
                        </div>
                        <label class="rsl-login-remember">
                            <input type="checkbox" name="remember" value="1">
                            <span>Remember me</span>
                        </label>
                        <button type="submit" class="rsl-btn rsl-login-submit">Sign in</button>
                    </form>
                </div>
            </div>
            <p class="rsl-login-foot">&copy; {{ date('Y') }} {{ $companyName ?? config('app.name') }} · <span class="rsl-login-build">{{ $rslPortalBuild }}</span></p>
        </main>
    </div>
    <script>
        function portalApplyThemeButton(theme) {
            const btn = document.getElementById('rsl-login-theme');
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
    </script>
</body>
</html>
