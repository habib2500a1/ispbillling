<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company }} — Internet Packages</title>
    <meta name="description" content="{{ $tagline }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b1220;
            --card: #111b2e;
            --accent: #14b8a6;
            --accent2: #0f766e;
            --text: #e2e8f0;
            --muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Figtree, system-ui, sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #134e4a55, transparent),
                        radial-gradient(900px 500px at 90% 0%, #1e3a5f55, transparent),
                        var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 1.25rem; }
        header {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 1rem; padding: 1rem 0 2rem;
        }
        .brand { display: flex; align-items: center; gap: .75rem; }
        .brand img { height: 48px; width: auto; }
        .brand h1 { font-size: 1.25rem; font-weight: 700; }
        .brand p { font-size: .8rem; color: var(--muted); }
        nav { display: flex; flex-wrap: wrap; gap: .5rem; }
        nav a {
            padding: .5rem 1rem; border-radius: .5rem; text-decoration: none; font-size: .875rem; font-weight: 600;
        }
        .btn-ghost { color: var(--text); border: 1px solid #334155; }
        .btn-ghost:hover { background: #1e293b; }
        .btn-primary { background: var(--accent); color: #042f2e; }
        .btn-primary:hover { background: #2dd4bf; }
        .btn-app {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff;
            border: none;
        }
        .btn-app:hover { filter: brightness(1.1); }
        .app-banner {
            margin: 2rem 0;
            padding: 1.25rem 1.5rem;
            border-radius: 1rem;
            border: 1px solid #1e3a5f;
            background: linear-gradient(135deg, #111b2e 0%, #172554 100%);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .app-banner strong { display: block; font-size: 1.05rem; margin-bottom: .25rem; }
        .app-banner p { font-size: .85rem; color: var(--muted); margin: 0; max-width: 28rem; }
        .app-banner-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .hero {
            text-align: center; padding: 2.5rem 1rem 3rem;
            border: 1px solid #1e293b; border-radius: 1rem;
            background: linear-gradient(180deg, #111b2e 0%, #0b1220 100%);
        }
        .hero h2 { font-size: clamp(1.75rem, 4vw, 2.5rem); margin-bottom: .75rem; }
        .hero .lead { color: var(--muted); max-width: 36rem; margin: 0 auto 1.5rem; }
        .hero-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem; }
        .features {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; margin: 2.5rem 0;
        }
        .feature {
            padding: 1.25rem; border-radius: .75rem; background: var(--card); border: 1px solid #1e293b;
        }
        .feature h3 { font-size: 1rem; margin-bottom: .35rem; color: var(--accent); }
        .feature p { font-size: .85rem; color: var(--muted); }
        h2.section-title { text-align: center; font-size: 1.5rem; margin-bottom: 1.25rem; }
        .packages {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem; margin-bottom: 2.5rem;
        }
        .pkg {
            background: var(--card); border: 1px solid #1e293b; border-radius: .85rem;
            padding: 1.25rem; display: flex; flex-direction: column;
        }
        .pkg.popular { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
        .pkg h3 { font-size: 1.1rem; }
        .pkg .speed { font-size: 1.75rem; font-weight: 700; margin: .5rem 0; }
        .pkg .price { font-size: 1.25rem; color: var(--accent); font-weight: 600; }
        .pkg ul { list-style: none; margin: 1rem 0; flex: 1; font-size: .85rem; color: var(--muted); }
        .pkg li { padding: .25rem 0; }
        .pkg li::before { content: "✓ "; color: var(--accent); }
        .pkg a {
            display: block; text-align: center; padding: .65rem; border-radius: .5rem;
            background: var(--accent2); color: #fff; text-decoration: none; font-weight: 600; font-size: .875rem;
        }
        .pkg a:hover { background: var(--accent); color: #042f2e; }
        footer {
            text-align: center; padding: 2rem 1rem; color: var(--muted); font-size: .8rem;
            border-top: 1px solid #1e293b;
        }
        footer a { color: var(--accent); }
        .isp-movie-servers { margin: 2.5rem 0; }
        .isp-movie-servers__head {
            display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
            gap: 1rem; margin-bottom: 1.25rem;
        }
        .isp-movie-servers__eyebrow {
            font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: var(--accent); margin-bottom: .35rem;
        }
        .isp-movie-servers__title { font-size: 1.5rem; font-weight: 700; }
        .isp-movie-servers__lead { margin-top: .35rem; font-size: .9rem; color: var(--muted); max-width: 32rem; }
        .isp-movie-servers__count {
            font-size: .75rem; font-weight: 600; padding: .35rem .75rem; border-radius: 999px;
            background: rgba(20,184,166,.15); color: var(--accent);
        }
        .isp-movie-servers__grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;
        }
        .isp-movie-server-card {
            display: flex; align-items: flex-start; gap: .85rem; padding: 1.1rem 1.15rem;
            border-radius: .85rem; border: 1px solid #1e293b; background: linear-gradient(145deg, #111b2e 0%, #0f172a 100%);
            text-decoration: none; color: inherit; transition: border-color .15s, transform .15s, box-shadow .15s;
        }
        .isp-movie-server-card:hover {
            border-color: var(--accent); transform: translateY(-2px);
            box-shadow: 0 12px 32px -8px rgba(20,184,166,.35);
        }
        .isp-movie-server-card__icon {
            flex-shrink: 0; width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center;
            border-radius: .65rem; background: rgba(20,184,166,.12); color: var(--accent);
        }
        .isp-movie-server-card__icon svg { width: 1.35rem; height: 1.35rem; }
        .isp-movie-server-card__body { flex: 1; min-width: 0; }
        .isp-movie-server-card__name { display: block; font-weight: 700; font-size: 1rem; }
        .isp-movie-server-card__url {
            display: block; margin-top: .2rem; font-size: .75rem; font-family: ui-monospace, monospace;
            color: #fb7185; word-break: break-all;
        }
        .isp-movie-server-card__note {
            display: block; margin-top: .45rem; font-size: .78rem; color: var(--muted); line-height: 1.4;
        }
        .isp-movie-server-card__cta {
            flex-shrink: 0; align-self: center; font-size: .75rem; font-weight: 700; color: var(--accent);
        }
    </style>
</head>
<body>
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
                @if ($portalUrl)
                    <a class="btn-primary" href="{{ $portalUrl }}">Portal login</a>
                @endif
                <a class="btn-app" href="{{ $appDownloadUrl }}">Mobile app</a>
                @if ($signupUrl ?? null)
                    <a class="btn-ghost" href="{{ $signupUrl }}">New connection</a>
                @endif
                <a class="btn-ghost" href="{{ $payUrl }}">Pay bill</a>
                <a class="btn-ghost" href="{{ $staffLoginUrl ?? $adminUrl }}">Staff login</a>
            </nav>
        </header>

        <x-portal-marquee :items="$portalMarquee ?? collect()" variant="landing" />
        <x-portal-notices-banner :notices="$portalNotices ?? collect()" variant="landing" />

        <section class="hero">
            <h2>Fast, reliable internet for home & business</h2>
            <p class="lead">{{ $tagline }}. Choose a package, pay online, and manage your connection from our customer portal.</p>
            <div class="hero-actions">
                @if ($portalUrl)
                    <a class="btn-primary" href="{{ $portalUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Customer portal login</a>
                @endif
                <a class="btn-app" href="{{ $appDownloadUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Download mobile app</a>
                <a class="btn-ghost" href="#packages" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">View packages</a>
                @if ($signupUrl ?? null)
                    <a class="btn-ghost" href="{{ $signupUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Request new connection</a>
                @endif
                <a class="btn-ghost" href="{{ $payUrl }}" style="padding:.65rem 1.25rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Quick bill pay</a>
            </div>
        </section>

        <section class="app-banner" aria-label="Mobile app">
            <div>
                <strong>RADIANT ISP Mobile App</strong>
                <p>Admin, staff ও subscriber — এক অ্যাপে বিল, usage, টিকেট ও collection। Android APK ডাউনলোড করে install করুন।</p>
            </div>
            <div class="app-banner-actions">
                <a class="btn-app" href="{{ $appDownloadUrl }}" style="padding:.65rem 1.1rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Download APK</a>
                @if ($portalUrl)
                    <a class="btn-ghost" href="{{ $portalUrl }}" style="padding:.65rem 1.1rem;border-radius:.5rem;text-decoration:none;font-weight:600;">Portal login</a>
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
                <p>Invoices, usage, package change — @if ($portalUrl)<a href="{{ $portalUrl }}" style="color:var(--accent);">login here</a>@else online @endif.</p>
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
                    @elseif ($portalUrl)
                        <a href="{{ $portalUrl }}">Customer portal</a>
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
                @if ($portalUrl)<a href="{{ $portalUrl }}">Customer portal login</a> · @endif
                <a href="{{ $appDownloadUrl }}">Mobile app (APK)</a> ·
                <a href="{{ $staffLoginUrl ?? $adminUrl }}">Staff login</a>
            </p>
        </footer>
    </div>
</body>
</html>
