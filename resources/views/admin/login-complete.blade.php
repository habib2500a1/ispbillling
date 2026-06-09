<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta http-equiv="refresh" content="1;url={{ $target }}">
    <title>Signing in — {{ $companyName }}</title>
    @if (! empty($logo))
        <link rel="icon" href="{{ $logo }}" />
    @else
        @include('partials.site-favicon')
    @endif
    <script data-cfasync="false">
        (function () {
            var key = 'isp-portal-theme';
            var stored = localStorage.getItem(key);
            document.documentElement.classList.toggle('portal-dark', stored === 'dark');
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/login-hub.css') }}?v={{ @filemtime(public_path('css/login-hub.css')) ?: time() }}">
</head>
<body class="isp-login-hub-page lh-signing-page">
    <div class="lh-signing" role="status" aria-live="polite" aria-busy="true">
        <div class="lh-signing__glow" aria-hidden="true"></div>

        <div class="lh-signing__card">
            <div class="lh-signing__brand">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="lh-brand__logo" width="64" height="64">
                @else
                    <span class="lh-brand__mark lh-signing__mark" aria-hidden="true">{{ mb_substr($companyName, 0, 1) }}</span>
                @endif
            </div>

            <div class="lh-signing__spinner" aria-hidden="true">
                <span class="lh-signing__ring"></span>
                <span class="lh-signing__ring lh-signing__ring--delay"></span>
            </div>

            <h1 class="lh-signing__title">Signing you in</h1>
            <p class="lh-signing__lead">Opening your dashboard — just a moment</p>

            <div class="lh-signing__bar" aria-hidden="true">
                <span class="lh-signing__bar-fill"></span>
            </div>

            <p class="lh-signing__hint">
                <a href="{{ $target }}" class="lh-link">Continue manually</a> if nothing happens
            </p>
        </div>
    </div>

    <script data-cfasync="false">
        window.setTimeout(function () {
            window.location.replace(@json($target));
        }, 900);
    </script>
</body>
</html>
