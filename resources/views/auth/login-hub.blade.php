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

            <div class="lh-alert lh-alert--error" id="lh-error" role="alert" hidden></div>

            <form class="lh-form" id="lh-form" novalidate>
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
                    <input type="checkbox" name="remember" value="1" id="lh-remember" checked>
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

    <iframe id="lh-auth-frame" name="lh-auth-frame" hidden title=""></iframe>
    <script data-cfasync="false">
        (function () {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var form = document.getElementById('lh-form');
            var loginInput = document.getElementById('lh-login');
            var passwordInput = document.getElementById('lh-password');
            var rememberInput = document.getElementById('lh-remember');
            var submitBtn = document.getElementById('lh-submit');
            var errorBox = document.getElementById('lh-error');
            var pwToggle = document.getElementById('lh-password-toggle');
            var themeBtn = document.getElementById('lh-theme-btn');
            var authFrame = document.getElementById('lh-auth-frame');

            var apiLoginUrl = @json(url('/api/v1/mobile/login'));

            var endpoints = {
                staff: {
                    url: @json(route('admin.login.session')),
                    fields: function (login, password, remember) {
                        return { email: login, password: password, remember: remember ? '1' : '0' };
                    },
                    enabled: true
                },
                customer: {
                    url: @json(route('portal.login.store')),
                    fields: function (login, password, remember) {
                        return { login: login, password: password, remember: remember ? '1' : '0' };
                    },
                    enabled: @json($portalEnabled)
                },
                reseller: {
                    url: @json(route('reseller.login.store')),
                    fields: function (login, password, remember) {
                        return { login: login, password: password, remember: remember ? '1' : '0' };
                    },
                    enabled: @json((bool) config('reseller_portal.enabled', true))
                }
            };

            function getCsrf() {
                var fromMeta = csrfMeta ? csrfMeta.getAttribute('content') : '';
                if (fromMeta) {
                    return fromMeta;
                }
                var match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
                return match ? decodeURIComponent(match[1]) : '';
            }

            function xsrfHeader() {
                var match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
                return match ? decodeURIComponent(match[1]) : getCsrf();
            }

            function refreshCsrfFromFrame() {
                try {
                    var doc = authFrame.contentDocument;
                    if (!doc) return;
                    var meta = doc.querySelector('meta[name="csrf-token"]');
                    if (meta && meta.getAttribute('content')) {
                        csrfMeta.setAttribute('content', meta.getAttribute('content'));
                        return;
                    }
                    var input = doc.querySelector('input[name="_token"]');
                    if (input && input.value) {
                        csrfMeta.setAttribute('content', input.value);
                    }
                } catch (err) { /* ignore */ }
            }

            function detectHint(login) {
                var v = (login || '').trim();
                if (!v) return 'auto';
                if (/^rsl[-_]/i.test(v)) return 'reseller';
                if (/^(cust|demo)[-_]/i.test(v)) return 'customer';
                if (/^01[0-9]{9}$/.test(v.replace(/[\s\-+]/g, '').replace(/^88/, ''))) return 'customer';
                if (v.indexOf('@') !== -1) return 'email';
                if (/^[0-9]{6,}$/.test(v.replace(/\D/g, ''))) return 'customer';
                return 'auto';
            }

            function attemptOrder(hint) {
                if (hint === 'reseller') return ['reseller', 'customer', 'staff'];
                if (hint === 'customer') return ['customer', 'reseller', 'staff'];
                if (hint === 'email') return ['staff', 'customer', 'reseller'];
                return ['customer', 'staff', 'reseller'];
            }

            function setLoading(on) {
                submitBtn.disabled = on;
                submitBtn.classList.toggle('is-loading', on);
            }

            function showError(msg) {
                if (!msg) {
                    errorBox.textContent = '';
                    errorBox.hidden = true;
                    return;
                }
                errorBox.textContent = msg;
                errorBox.hidden = false;
            }

            // Surface server-side validation / session errors from redirect.
            @if ($errors->any())
                showError(@json($errors->first()));
            @elseif (session('session_expired'))
                showError('Your session expired. Please sign in again.');
            @endif

            function isLoginSuccess(url) {
                if (!url) return false;
                try {
                    var path = new URL(url, window.location.origin).pathname;
                    if (path === '/login' || path === '/admin/login' || path === '/login/customer' || path === '/reseller/login') {
                        return false;
                    }
                    if (path.indexOf('/login') !== -1
                        && path.indexOf('/complete') === -1
                        && path.indexOf('/otp') === -1) {
                        return false;
                    }
                    return true;
                } catch (err) {
                    return false;
                }
            }

            function submitWebLogin(role, login, password, remember, target) {
                var ep = endpoints[role];
                if (!ep || !ep.enabled) return false;

                var temp = document.createElement('form');
                temp.method = 'POST';
                temp.action = ep.url;
                temp.target = target || '_self';
                temp.style.display = 'none';

                var token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = getCsrf();
                temp.appendChild(token);

                var fields = ep.fields(login, password, remember);
                Object.keys(fields).forEach(function (key) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = fields[key];
                    temp.appendChild(input);
                });

                document.body.appendChild(temp);
                temp.submit();
                document.body.removeChild(temp);
                return true;
            }

            async function submitStaffLoginFetch(login, password, remember) {
                var ep = endpoints.staff;
                if (!ep || !ep.enabled) return false;

                var body = new URLSearchParams({
                    _token: getCsrf(),
                    email: login,
                    password: password,
                    remember: remember ? '1' : '0',
                });

                try {
                    var res = await fetch(ep.url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'text/html,application/json',
                            'X-XSRF-TOKEN': xsrfHeader(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: body.toString(),
                        redirect: 'manual',
                    });

                    if (res.status >= 300 && res.status < 400) {
                        var loc = res.headers.get('Location') || '';
                        if (isLoginSuccess(loc)) {
                            window.location.href = loc;
                            return true;
                        }
                    }
                } catch (err) { /* ignore */ }

                return false;
            }

            function tryIframeLogin(role, login, password, remember) {
                return new Promise(function (resolve) {
                    var ep = endpoints[role];
                    if (!ep || !ep.enabled) {
                        resolve(false);
                        return;
                    }

                    var done = false;
                    var timer = setTimeout(function () {
                        if (!done) {
                            done = true;
                            resolve(false);
                        }
                    }, 12000);

                    authFrame.onload = function () {
                        if (done) return;
                        refreshCsrfFromFrame();
                        try {
                            var href = authFrame.contentWindow.location.href;
                            if (isLoginSuccess(href)) {
                                done = true;
                                clearTimeout(timer);
                                window.location.href = href;
                                resolve(true);
                                return;
                            }
                        } catch (err) { /* ignore */ }
                        done = true;
                        clearTimeout(timer);
                        resolve(false);
                    };

                    submitWebLogin(role, login, password, remember, 'lh-auth-frame');
                });
            }

            async function resolveRoleViaApi(login, password) {
                try {
                    var res = await fetch(apiLoginUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ login: login, password: password, role: 'auto' })
                    });

                    if (res.ok) {
                        var data = await res.json();
                        return data.role || null;
                    }

                    if (res.status === 422) {
                        var payload = await res.json();
                        if (payload && payload.requires_2fa) {
                            return payload.role || 'reseller';
                        }
                    }
                } catch (err) { /* ignore */ }

                return null;
            }

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

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                showError('');

                var login = loginInput.value.trim();
                var password = passwordInput.value;
                var remember = rememberInput.checked;

                if (!login || !password) {
                    showError('Please enter your account and password.');
                    return;
                }

                setLoading(true);

                var role = await resolveRoleViaApi(login, password);
                if (role === 'staff' && endpoints.staff.enabled) {
                    // Full form POST — reliable session cookie commit (fetch can drop Set-Cookie in some browsers).
                    submitWebLogin('staff', login, password, remember);
                    return;
                }
                if (role && endpoints[role] && endpoints[role].enabled) {
                    submitWebLogin(role, login, password, remember);
                    return;
                }

                var order = attemptOrder(detectHint(login));
                for (var i = 0; i < order.length; i++) {
                    var ok = await tryIframeLogin(order[i], login, password, remember);
                    if (ok) return;
                }

                setLoading(false);
                showError('Invalid credentials. Check your account ID and password.');
            });
        })();
    </script>
</body>
</html>
