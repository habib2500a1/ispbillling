<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>Sign in — {{ $companyName }}</title>
    @if (! empty($logo))
        <link rel="icon" href="{{ $logo }}" />
    @else
        @include('partials.site-favicon')
    @endif
    <script data-cfasync="false">
        (function () {
            var key = 'isp-portal-theme';
            var stored = localStorage.getItem(key);
            var theme = stored === 'dark' ? 'dark' : 'light';
            document.documentElement.classList.toggle('portal-dark', theme === 'dark');
        })();
    </script>
    <style>
        html, body.isp-login-hub-page { margin: 0; min-height: 100vh; background: #f4f4f5; color: #09090b; font-family: Outfit, system-ui, sans-serif; }
        html.portal-dark, html.portal-dark body.isp-login-hub-page { background: #09090b; color: #fafafa; }
        .lh-shell { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; box-sizing: border-box; }
        .lh-card { width: 100%; max-width: 26rem; background: #fff; border: 1px solid #e4e4e7; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 8px 30px rgba(0,0,0,.08); box-sizing: border-box; }
        html.portal-dark .lh-card { background: #18181b; border-color: #3f3f46; }
        .lh-head { text-align: center; margin-bottom: 1rem; }
        .lh-head__title { margin: 0; font-size: 1.35rem; font-weight: 800; }
        .lh-head__lead { margin: .5rem 0 0; font-size: .875rem; color: #71717a; }
        .lh-field { margin-bottom: .875rem; }
        .lh-field label { display: block; margin-bottom: .35rem; font-size: .8125rem; font-weight: 600; }
        .lh-field input { width: 100%; padding: .75rem; border: 1px solid #d4d4d8; border-radius: .5rem; font: inherit; box-sizing: border-box; background: #fafafa; color: inherit; }
        html.portal-dark .lh-field input { background: #27272a; border-color: #52525b; }
        .lh-submit { width: 100%; padding: .75rem; border: 0; border-radius: .5rem; background: #4f46e5; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .lh-orbs { display: none !important; }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @include('partials.isp-premium-theme', ['tailwind' => false, 'glass' => false, 'motion' => false])
    <link rel="stylesheet" href="{{ asset('css/login-hub.css') }}?v={{ @filemtime(public_path('css/login-hub.css')) ?: time() }}">
</head>
<body class="isp-login-hub-page">
    @include('partials.demo-banner')

    <main class="lh-shell">
        <div class="lh-card">
            <div class="lh-card__top">
                <button
                    type="button"
                    class="lh-theme-btn"
                    id="lh-theme-btn"
                    aria-label="Toggle light or dark mode"
                    title="Toggle theme"
                >
                    <svg class="lh-theme-btn__sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                    <svg class="lh-theme-btn__moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </div>

            <header class="lh-head">
                <div class="lh-brand">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="lh-brand__logo" width="56" height="56">
                    @else
                        <span class="lh-brand__mark" aria-hidden="true">{{ mb_substr($companyName, 0, 1) }}</span>
                    @endif
                </div>
                <p class="lh-head__eyebrow">Secure access</p>
                <h1 class="lh-head__title">{{ $companyName }}</h1>
                <p class="lh-head__lead">Sign in with your email, phone, or account ID</p>
            </header>

            @include('partials.demo-credentials-hint')

            @if (session('session_expired'))
                <div class="lh-alert lh-alert--error" role="alert">
                    Your session expired. Please sign in again.
                </div>
            @endif

            @if ($errors->any())
                <div class="lh-alert lh-alert--error" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="lh-form" id="lh-form" method="POST" action="{{ route('login.hub.store') }}" novalidate>
                @csrf

                <div class="lh-field">
                    <label for="lh-login">Email, phone, or account ID</label>
                    <input
                        id="lh-login"
                        name="login"
                        type="text"
                        required
                        autofocus
                        autocomplete="username"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        value="{{ old('login') }}"
                        placeholder="habib@radiantbd.com, 01XXXXXXXXX, CUST-001"
                    >
                </div>

                <div class="lh-field">
                    <label for="lh-password">Password</label>
                    <div class="lh-password-wrap">
                        <input
                            id="lh-password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        >
                        <button type="button" class="lh-password-toggle" id="lh-password-toggle" aria-label="Show password" tabindex="-1">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="lh-remember">
                    <input type="checkbox" name="remember" value="1" id="lh-remember" @checked(old('remember', '1') !== '0')>
                    <span>Remember this device</span>
                </label>

                <button type="submit" class="lh-submit" id="lh-submit">
                    <span class="lh-submit__label">Sign in</span>
                    <span class="lh-submit__spinner" aria-hidden="true"></span>
                </button>
            </form>

            <footer class="lh-foot">
                <a href="{{ $payUrl }}" class="lh-chip">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    Pay bill without login
                </a>
                @if (config('portal.signup.enabled', true) && $portalEnabled)
                    <a href="{{ route('portal.signup') }}" class="lh-chip lh-chip--muted">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        New connection
                    </a>
                @endif
            </footer>
        </div>
    </main>

    <script data-cfasync="false">
        (function () {
            var passwordInput = document.getElementById('lh-password');
            var pwToggle = document.getElementById('lh-password-toggle');
            var themeBtn = document.getElementById('lh-theme-btn');
            var submitBtn = document.getElementById('lh-submit');
            var form = document.getElementById('lh-form');

            pwToggle.addEventListener('click', function () {
                var show = passwordInput.type === 'password';
                passwordInput.type = show ? 'text' : 'password';
                pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });

            if (themeBtn) {
                themeBtn.addEventListener('click', function () {
                    var root = document.documentElement;
                    var next = root.classList.contains('portal-dark') ? 'light' : 'dark';
                    root.classList.toggle('portal-dark', next === 'dark');
                    localStorage.setItem('isp-portal-theme', next);
                });
            }

            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
            });
        })();
    </script>
</body>
</html>
