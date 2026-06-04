@include('filament.hooks.sidebar-accordion-head')
<script data-cfasync="false">
(function () {
    var pref = localStorage.getItem('theme') || localStorage.getItem('isp-admin-theme') || 'system';
    if (localStorage.getItem('isp-admin-theme') && !localStorage.getItem('theme')) {
        localStorage.setItem('theme', localStorage.getItem('isp-admin-theme'));
    }
    function resolved(m) {
        return m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark'
            : 'light';
    }
    var r = resolved(pref);
    document.documentElement.classList.toggle('dark', r === 'dark');
    document.documentElement.setAttribute('data-theme', r);
    document.documentElement.dataset.themeMode = pref;
})();
</script>
@unless (request()->routeIs('filament.admin.auth.*'))
@php($mobileBarCssV = @filemtime(public_path('css/admin-mobile-bar.css')) ?: 1)
@include('filament.hooks.mobile-bar-critical-css')
<link rel="preload" href="{{ \App\Support\AdminSaasStyles::preloadHref() }}" as="style">
<link rel="stylesheet" href="{{ asset('css/admin-mobile-bar.css') }}?v={{ $mobileBarCssV }}" media="(max-width: 1023px)">
@endunless
{!! \App\Support\AdminSaasStyles::html() !!}
@unless (\App\Support\AdminSaasStyles::bundleIncludesExtras())
<link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: 1 }}">
@endunless
@include('partials.isp-premium-theme', ['tailwind' => false, 'motion' => false, 'fonts' => false])
@unless (request()->routeIs('filament.admin.auth.*') || \App\Support\AdminSaasStyles::bundleIncludesExtras())
<link rel="stylesheet" href="{{ asset('css/admin-responsive.css') }}?v={{ @filemtime(public_path('css/admin-responsive.css')) ?: 1 }}">
@endunless
<script src="{{ asset('js/admin-theme.js') }}?v={{ @filemtime(public_path('js/admin-theme.js')) ?: 4 }}" defer data-cfasync="false"></script>
@unless (request()->routeIs('filament.admin.auth.*'))
<script src="{{ asset('js/admin-sidebar-search.js') }}?v={{ @filemtime(public_path('js/admin-sidebar-search.js')) ?: 1 }}" defer data-cfasync="false"></script>
@if (request()->routeIs('filament.admin.pages.dashboard*', 'filament.admin.pages.dashboard-hub*', 'filament.admin.pages.*-dashboard*', 'filament.admin.pages.operations-hub'))
<script src="{{ asset('js/isp-dashboard-realtime.js') }}?v={{ @filemtime(public_path('js/isp-dashboard-realtime.js')) ?: 1 }}" defer data-cfasync="false"></script>
@endif
@endunless
