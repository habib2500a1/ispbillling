<!DOCTYPE html>
<html lang="bn" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company }} — Internet Packages</title>
    <meta name="description" content="{{ $tagline }}">
    <meta name="color-scheme" content="light dark">
    <style>
        html, body.isp-landing-page { margin: 0; background: #eef2ff; color: #0f172a; }
        html[data-theme="dark"], html[data-theme="dark"] body.isp-landing-page { background: #050510; color: #f4f4f5; }
        body.isp-landing-page .skip-link,
        body.isp-landing-page > a[href="#main-content"] { display: none !important; }
        .wrap, body.isp-landing-page main { width: 100%; max-width: 100%; box-sizing: border-box; }
    </style>
    <script data-cfasync="false">
        (function () {
            var stored = localStorage.getItem('isp-landing-theme');
            var theme = stored === 'light' || stored === 'dark'
                ? stored
                : (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @include('partials.isp-premium-theme', ['tailwind' => false, 'glass' => false, 'motion' => false])
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) ?: 1 }}">
</head>
<body class="isp-landing-page">
    @include('partials.demo-banner')
    <div class="wrap">
        <header class="landing-header">
            <div class="brand">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $company }}">
                @endif
                <div>
                    <h1>{{ $company }}</h1>
                    <p>{{ $tagline }}</p>
                </div>
            </div>

            <div class="landing-header__actions">
                @if ($loginHubUrl ?? $portalUrl)
                    <a class="btn-primary landing-header__signin" href="{{ $loginHubUrl ?? $portalUrl }}">Sign in</a>
                @endif
                <div class="theme-switch" role="group" aria-label="Color theme">
                    <button type="button" class="theme-switch__btn" data-theme-value="light" id="theme-light" aria-pressed="true">Light</button>
                    <button type="button" class="theme-switch__btn" data-theme-value="dark" id="theme-dark" aria-pressed="false">Dark</button>
                </div>

                <button
                    type="button"
                    class="nav-toggle"
                    id="nav-toggle"
                    aria-expanded="false"
                    aria-controls="main-nav"
                    aria-label="Open menu"
                >
                    <span class="nav-toggle__bar" aria-hidden="true"></span>
                    <span class="nav-toggle__bar" aria-hidden="true"></span>
                    <span class="nav-toggle__bar" aria-hidden="true"></span>
                </button>
            </div>

            <nav class="landing-nav" id="main-nav" aria-label="Main navigation">
                @if (! empty($shopUrl))
                    <a class="btn-ghost" href="{{ $shopUrl }}">Shop</a>
                @endif
                <a class="btn-ghost" href="#packages">Packages</a>
                @if ($signupUrl ?? null)
                    <a class="btn-ghost" href="{{ $signupUrl }}">New connection</a>
                @endif
                <a class="btn-ghost" href="{{ $payUrl }}">Pay bill</a>
                <a class="btn-app" href="{{ $appDownloadUrl }}">Mobile app</a>
            </nav>
        </header>

        <main id="main-content">
            <x-portal-marquee :items="$portalMarquee ?? collect()" variant="landing" />
            <x-portal-notices-banner :notices="$portalNotices ?? collect()" variant="landing" />

            <section class="hero" aria-labelledby="hero-title">
                <p class="hero__eyebrow">Internet service provider</p>
                <h2 id="hero-title">Fast, reliable internet for home & business</h2>
                <p class="lead">{{ $tagline }}. Choose a package, pay online, and manage your connection from our customer portal.</p>
                @if ($loginHubUrl ?? $portalUrl)
                    <p class="hero__note">One sign-in for customer, staff, or partner.</p>
                @endif
                <div class="hero-actions">
                    <a class="btn-primary btn-lg" href="#packages">View packages</a>
                    @if ($signupUrl ?? null)
                        <a class="btn-ghost btn-lg" href="{{ $signupUrl }}">New connection</a>
                    @endif
                    <a class="btn-ghost btn-lg" href="{{ $payUrl }}">Pay bill</a>
                </div>
            </section>

            <section class="app-banner" aria-label="Mobile app">
                <div class="app-banner__content">
                    <span class="app-banner__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                    </span>
                    <div class="app-banner__text">
                        <strong>RADIANT ISP Mobile App</strong>
                        <p>Admin, staff ও subscriber — এক অ্যাপে বিল, usage, টিকেট ও collection। Android APK ডাউনলোড করে install করুন।</p>
                    </div>
                </div>
                <div class="app-banner-actions">
                    <a class="btn-app btn-lg" href="{{ $appDownloadUrl }}">Download APK</a>
                </div>
            </section>

            <div class="features" role="list">
                <div class="feature" role="listitem">
                    <span class="feature__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    </span>
                    <h3>Online bill pay</h3>
                    <p>bKash, Nagad, SSLCommerz & PipraPay — pay anytime from your phone.</p>
                </div>
                <div class="feature" role="listitem">
                    <span class="feature__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <h3>Customer portal</h3>
                    <p>Invoices, usage, and speed test — all in your online account.</p>
                </div>
                <div class="feature" role="listitem">
                    <span class="feature__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                    </span>
                    <h3>Mobile app</h3>
                    <p>Android app for clients & staff — bills, usage, tickets & collection on the go.</p>
                </div>
                <div class="feature" role="listitem">
                    <span class="feature__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/></svg>
                    </span>
                    <h3>PPPoE & fiber</h3>
                    <p>MikroTik-powered network with optical monitoring and fair usage.</p>
                </div>
                <div class="feature" role="listitem">
                    <span class="feature__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </span>
                    <h3>Local support</h3>
                    <p>@if($phone) Call {{ $phone }} @else Contact our support desk @endif for new connections.</p>
                </div>
            </div>

            <x-movie-servers-showcase :servers="$movieServers" id="entertainment" />

            <section class="packages-section" aria-labelledby="packages">
                <h2 class="section-title" id="packages">Internet packages</h2>
                <p class="section-subtitle">Only packages with “Show on website” on appear here.</p>
                <div class="packages">
                    @forelse ($packages as $i => $pkg)
                        <article class="pkg {{ $i === 1 ? 'popular' : '' }}">
                            @if ($i === 1)
                                <span class="pkg__badge">Popular</span>
                            @endif
                            <h3>{{ $pkg->name }}</h3>
                            <div class="speed">{{ $pkg->download_mbps ?? '—' }} Mbps</div>
                            <div class="price">{{ number_format((float) $pkg->price_monthly, 0) }} BDT <span>/ month</span></div>
                            <ul>
                                <li>Download {{ $pkg->download_mbps ?? '—' }} Mbps</li>
                                <li>Upload {{ $pkg->upload_mbps ?? '—' }} Mbps</li>
                                @if ($pkg->setup_fee > 0)
                                    <li>Setup {{ number_format((float) $pkg->setup_fee, 0) }} BDT</li>
                                @endif
                                <li>Billing every {{ $pkg->billing_cycle_days ?? 30 }} days</li>
                            </ul>
                            @if ($signupUrl ?? null)
                                <a href="{{ $signupUrl }}">Request connection</a>
                            @else
                                <a href="{{ $payUrl }}">Pay bill / contact</a>
                            @endif
                        </article>
                    @empty
                        <p class="packages-empty">Packages coming soon — call us for pricing.</p>
                    @endforelse
                </div>
            </section>
        </main>

        <footer>
            <p class="footer-brand">{{ $company }} @if($address) · {{ $address }} @endif</p>
            @if ($phone)<p>Phone: <a href="tel:{{ $phone }}">{{ $phone }}</a></p>@endif
            @if ($email)<p>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            <p class="footer-links">
                <a href="{{ $payUrl }}">Pay bill</a>
                @if (! empty($shopUrl)) · <a href="{{ $shopUrl }}">Shop</a>@endif
            </p>
        </footer>
    </div>

    <script data-cfasync="false">
        (function () {
            var root = document.documentElement;
            var themeBtns = document.querySelectorAll('.theme-switch__btn');
            var navBtn = document.getElementById('nav-toggle');
            var nav = document.getElementById('main-nav');

            function syncThemeUi(theme) {
                themeBtns.forEach(function (btn) {
                    var on = btn.getAttribute('data-theme-value') === theme;
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }

            syncThemeUi(root.getAttribute('data-theme') || 'light');

            themeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var theme = btn.getAttribute('data-theme-value');
                    root.setAttribute('data-theme', theme);
                    localStorage.setItem('isp-landing-theme', theme);
                    syncThemeUi(theme);
                });
            });

            if (navBtn && nav) {
                navBtn.addEventListener('click', function () {
                    var open = nav.classList.toggle('is-open');
                    navBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    navBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
                });

                nav.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        nav.classList.remove('is-open');
                        navBtn.setAttribute('aria-expanded', 'false');
                        navBtn.setAttribute('aria-label', 'Open menu');
                    });
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                        nav.classList.remove('is-open');
                        navBtn.setAttribute('aria-expanded', 'false');
                        navBtn.setAttribute('aria-label', 'Open menu');
                        navBtn.focus();
                    }
                });
            }
        })();
    </script>
</body>
</html>
