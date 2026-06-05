<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company }} — Internet Packages</title>
    <meta name="description" content="{{ $tagline }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @include('partials.isp-premium-theme', ['tailwind' => false, 'glass' => false, 'motion' => false])
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) ?: 1 }}">
</head>
<body class="isp-landing-page">
    <div class="isp-premium-orbs" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="wrap">
        <header>
            <div class="brand">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $company }}">
                @endif
                <div>
                    <h1>{{ $company }}</h1>
                    <p>{{ $tagline }}</p>
                </div>
            </div>
            <nav>
                @if (! empty($shopUrl))
                    <a class="btn-ghost" href="{{ $shopUrl }}">Shop</a>
                @endif
                @if ($loginHubUrl ?? $portalUrl)
                    <a class="btn-primary" href="{{ $loginHubUrl ?? $portalUrl }}">Sign in</a>
                @endif
                <a class="btn-app" href="{{ $appDownloadUrl }}">Mobile app</a>
                @if ($signupUrl ?? null)
                    <a class="btn-ghost" href="{{ $signupUrl }}">New connection</a>
                @endif
                <a class="btn-ghost" href="{{ $payUrl }}">Pay bill</a>
            </nav>
        </header>

        <x-portal-marquee :items="$portalMarquee ?? collect()" variant="landing" />
        <x-portal-notices-banner :notices="$portalNotices ?? collect()" variant="landing" />

        <section class="hero">
            <h2>Fast, reliable internet for home & business</h2>
            <p class="lead">{{ $tagline }}. Choose a package, pay online, and manage your connection from our customer portal.</p>
            <div class="hero-actions">
                @if ($loginHubUrl ?? $portalUrl)
                    <a class="btn-primary" href="{{ $loginHubUrl ?? $portalUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Sign in</a>
                @endif
                <a class="btn-app" href="{{ $appDownloadUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Download mobile app</a>
                <a class="btn-ghost" href="#packages" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">View packages</a>
                @if ($signupUrl ?? null)
                    <a class="btn-ghost" href="{{ $signupUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Request new connection</a>
                @endif
                <a class="btn-ghost" href="{{ $payUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Quick bill pay</a>
            </div>
        </section>

        @if ($loginHubUrl ?? $portalUrl)
            <section class="sign-in-hub" id="sign-in" aria-label="Sign in options">
                <header class="sign-in-hub__head">
                    <p class="sign-in-hub__eyebrow">One place to sign in</p>
                    <h2 class="sign-in-hub__title">Customer, staff, or partner</h2>
                    <p class="sign-in-hub__lead">Choose the portal that matches your account — same links as our mobile app.</p>
                </header>
                <div class="sign-in-hub__grid">
                    @if ($customerLoginUrl ?? null)
                        <a href="{{ $customerLoginUrl }}" class="sign-in-hub__card sign-in-hub__card--customer">
                            <span class="sign-in-hub__badge">Customer</span>
                            <span class="sign-in-hub__card-title">Customer portal</span>
                            <span class="sign-in-hub__card-desc">Bills, usage, speed test, tickets</span>
                            <span class="sign-in-hub__cta">Customer login →</span>
                        </a>
                    @endif
                    <a href="{{ $staffLoginUrl ?? $adminUrl }}" class="sign-in-hub__card sign-in-hub__card--staff">
                        <span class="sign-in-hub__badge">Staff</span>
                        <span class="sign-in-hub__card-title">Admin / operations</span>
                        <span class="sign-in-hub__card-desc">Billing desk, subscribers, network</span>
                        <span class="sign-in-hub__cta">Staff login →</span>
                    </a>
                    @if ($resellerLoginUrl ?? null)
                        <a href="{{ $resellerLoginUrl }}" class="sign-in-hub__card sign-in-hub__card--reseller">
                            <span class="sign-in-hub__badge">Partner</span>
                            <span class="sign-in-hub__card-title">Reseller portal</span>
                            <span class="sign-in-hub__card-desc">Collections, due reports</span>
                            <span class="sign-in-hub__cta">Reseller login →</span>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        <section class="app-banner" aria-label="Mobile app">
            <div>
                <strong>RADIANT ISP Mobile App</strong>
                <p>Admin, staff ও subscriber — এক অ্যাপে বিল, usage, টিকেট ও collection। Android APK ডাউনলোড করে install করুন।</p>
            </div>
            <div class="app-banner-actions">
                <a class="btn-app" href="{{ $appDownloadUrl }}" style="padding:.65rem 1.1rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Download APK</a>
                @if ($loginHubUrl ?? $portalUrl)
                    <a class="btn-ghost" href="{{ $loginHubUrl ?? $portalUrl }}" style="padding:.65rem 1.1rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Sign in</a>
                @endif
            </div>
        </section>

        <div class="features">
            <div class="feature">
                <h3>Online bill pay</h3>
                <p>bKash, Nagad, SSLCommerz & PipraPay — pay anytime from your phone.</p>
            </div>
            <div class="feature">
                <h3>Customer portal</h3>
                <p>Invoices, usage, speed test — @if ($customerLoginUrl ?? $portalUrl)<a href="{{ $customerLoginUrl ?? $portalUrl }}" style="color:var(--accent);">customer login</a>@else online @endif.</p>
            </div>
            <div class="feature">
                <h3>Mobile app</h3>
                <p>Android app for clients & staff. <a href="{{ $appDownloadUrl }}" style="color:var(--accent);">Download APK</a>.</p>
            </div>
            <div class="feature">
                <h3>PPPoE & fiber</h3>
                <p>MikroTik-powered network with optical monitoring and fair usage.</p>
            </div>
            <div class="feature">
                <h3>Local support</h3>
                <p>@if($phone) Call {{ $phone }} @else Contact our support desk @endif for new connections.</p>
            </div>
        </div>

        <x-movie-servers-showcase :servers="$movieServers" id="entertainment" />

        <h2 class="section-title" id="packages">Internet packages</h2>
        <p style="text-align:center;color:var(--muted);margin:-1rem 0 1.5rem;font-size:0.95rem;">Only packages with “Show on website” on appear here.</p>
        <div class="packages">
            @forelse ($packages as $i => $pkg)
                <article class="pkg {{ $i === 1 ? 'popular' : '' }}">
                    <h3>{{ $pkg->name }}</h3>
                    <div class="speed">{{ $pkg->download_mbps ?? '—' }} Mbps</div>
                    <div class="price">{{ number_format((float) $pkg->price_monthly, 0) }} BDT / month</div>
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
                    @elseif ($customerLoginUrl ?? $portalUrl)
                        <a href="{{ $customerLoginUrl ?? $portalUrl }}">Customer portal</a>
                    @else
                        <a href="{{ $payUrl }}">Pay bill / contact</a>
                    @endif
                </article>
            @empty
                <p style="grid-column:1/-1;text-align:center;color:var(--muted);">Packages coming soon — call us for pricing.</p>
            @endforelse
        </div>

        <footer>
            <p>{{ $company }} @if($address) · {{ $address }} @endif</p>
            @if ($phone)<p>Phone: <a href="tel:{{ $phone }}">{{ $phone }}</a></p>@endif
            @if ($email)<p>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            <p style="margin-top:1rem;">
                @if ($loginHubUrl ?? $portalUrl)<a href="{{ $loginHubUrl ?? $portalUrl }}">Sign in</a> · @endif
                <a href="{{ $appDownloadUrl }}">Mobile app (APK)</a> ·
                <a href="{{ $payUrl }}">Pay bill</a>
            </p>
        </footer>
    </div>
</body>
</html>
