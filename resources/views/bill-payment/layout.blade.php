<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bill payment') — {{ $companyName ?? config('isp.company_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/bill-payment.css') }}?v=2">
    <script src="{{ asset('js/portal-theme.js') }}?v=1"></script>
</head>
<body class="bp-bg antialiased">
    <header class="bp-topbar">
        <div class="bp-topbar-inner">
            <div>
                <p class="bp-topbar-kicker">Online payment</p>
                <p class="bp-topbar-title">{{ $companyName ?? config('isp.company_name') }}</p>
            </div>
            <button type="button" class="bp-theme-btn" onclick="portalCycleTheme?.() || (window.portalSetTheme && portalCycleTheme())" id="bp-theme-btn" title="Theme">◐</button>
        </div>
    </header>
    <div class="bp-split">
        <aside class="bp-brand">
            <div>
                <p class="text-sm uppercase tracking-widest text-teal-300/80">Online payment</p>
                <h1>{{ $companyName ?? config('isp.company_name') }}</h1>
                <p class="mt-2 text-sm text-white/65">{{ config('isp.company_tagline') }}</p>
                <ol class="bp-brand-steps">
                    <li>Enter your client code</li>
                    @if ($otpEnabled ?? false)
                        <li>Verify mobile OTP</li>
                    @endif
                    <li>Review invoice &amp; due amount</li>
                    <li>Pay via bKash, SSLCommerz or Nagad</li>
                </ol>
            </div>
            <p class="text-xs text-white/40">Secure bill payment · No login required</p>
        </aside>
        <main class="bp-main">
            @yield('content')
        </main>
    </div>
    <script>
        function portalCycleTheme() {
            const order = ['light', 'dark', 'system'];
            const cur = window.portalGetTheme?.() || 'system';
            const next = order[(order.indexOf(cur) + 1) % order.length];
            window.portalSetTheme?.(next);
            const btn = document.getElementById('bp-theme-btn');
            if (btn) btn.textContent = { light: '☀️', dark: '🌙', system: '◐' }[next] || '◐';
        }
    </script>
</body>
</html>
