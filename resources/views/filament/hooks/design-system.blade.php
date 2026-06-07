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
@unless (request()->routeIs('filament.admin.auth.*'))
@php($dashHomeCssV = @filemtime(public_path('css/admin/saas/12-dashboard-home.css')) ?: 1)
@php($dashInsightsCssV = @filemtime(public_path('css/admin/saas/13-dashboard-insights.css')) ?: 1)
@php($revChartFixV = @filemtime(public_path('css/dashboard-revenue-chart-fix.css')) ?: 1)
@php($opsFeedFixV = @filemtime(public_path('css/dashboard-ops-feed-fix.css')) ?: 1)
@php($dashV2CssV = @filemtime(public_path('css/admin/saas/15-dashboard-v2-zones.css')) ?: 1)
<link rel="stylesheet" href="{{ asset('css/admin/saas/12-dashboard-home.css') }}?v={{ $dashHomeCssV }}">
<link rel="stylesheet" href="{{ asset('css/admin/saas/13-dashboard-insights.css') }}?v={{ $dashInsightsCssV }}">
@if (request()->routeIs('filament.admin.pages.dashboard'))
<link rel="stylesheet" href="{{ asset('css/admin/saas/15-dashboard-v2-zones.css') }}?v={{ $dashV2CssV }}">
<link rel="stylesheet" href="{{ asset('css/dashboard-revenue-chart-fix.css') }}?v={{ $revChartFixV }}" data-isp-rev-chart-fix="1">
<link rel="stylesheet" href="{{ asset('css/dashboard-ops-feed-fix.css') }}?v={{ $opsFeedFixV }}" data-isp-ops-feed-fix="1">
@php($dashV2JsV = @filemtime(public_path('js/isp-dashboard-v2.js')) ?: 1)
<script src="{{ asset('js/isp-dashboard-v2.js') }}?v={{ $dashV2JsV }}" defer data-cfasync="false"></script>
@endif
@include('filament.hooks.today-snapshot-fix')
@endunless
<script src="{{ asset('js/admin-theme.js') }}?v={{ @filemtime(public_path('js/admin-theme.js')) ?: 4 }}" defer data-cfasync="false"></script>
@unless (request()->routeIs('filament.admin.auth.*'))
<script src="{{ asset('js/admin-sidebar-search.js') }}?v={{ @filemtime(public_path('js/admin-sidebar-search.js')) ?: 1 }}" defer data-cfasync="false"></script>
@if (request()->routeIs('filament.admin.*') && ! request()->routeIs('filament.admin.auth.*'))
<script src="{{ asset('js/isp-dashboard-realtime.js') }}?v={{ @filemtime(public_path('js/isp-dashboard-realtime.js')) ?: 1 }}" defer data-cfasync="false"></script>
@endif
@endunless
