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
<link rel="stylesheet" href="{{ asset('css/admin-saas.css') }}?v={{ @filemtime(public_path('css/admin-saas.css')) ?: '25' }}">
@include('partials.isp-premium-theme', ['tailwind' => true, 'motion' => true])
@unless (request()->routeIs('filament.admin.auth.*'))
<link rel="stylesheet" href="{{ asset('css/clients-hub-pro.css') }}?v={{ @filemtime(public_path('css/clients-hub-pro.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/billing-hub-pro.css') }}?v={{ @filemtime(public_path('css/billing-hub-pro.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/inventory-hub-pro.css') }}?v={{ @filemtime(public_path('css/inventory-hub-pro.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/olt-hub-pro.css') }}?v={{ @filemtime(public_path('css/olt-hub-pro.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/subscriber-view-pro.css') }}?v={{ @filemtime(public_path('css/subscriber-view-pro.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/optical-noc.css') }}?v={{ @filemtime(public_path('css/optical-noc.css')) ?: 1 }}">
@endunless
<script src="{{ asset('js/admin-theme.js') }}?v=4" data-cfasync="false"></script>
@unless (request()->routeIs('filament.admin.auth.*'))
<script src="{{ asset('js/admin-sidebar-search.js') }}?v={{ @filemtime(public_path('js/admin-sidebar-search.js')) ?: 1 }}" defer data-cfasync="false"></script>
@if (request()->routeIs('filament.admin.pages.dashboard*', 'filament.admin.pages.dashboard-hub*', 'filament.admin.pages.*-dashboard*', 'filament.admin.pages.operations-hub'))
<script src="{{ asset('js/isp-dashboard-realtime.js') }}?v={{ @filemtime(public_path('js/isp-dashboard-realtime.js')) ?: 1 }}" defer data-cfasync="false"></script>
@endif
@endunless
