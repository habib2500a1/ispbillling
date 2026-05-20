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
<link rel="stylesheet" href="{{ asset('css/admin-saas.css') }}?v={{ @filemtime(public_path('css/admin-saas.css')) ?: '11' }}">
<script src="{{ asset('js/admin-theme.js') }}?v=4" data-cfasync="false"></script>
<script src="{{ asset('js/admin-sidebar-search.js') }}?v=1" defer data-cfasync="false"></script>
<script src="{{ asset('js/admin-sidebar-layout.js') }}?v=8" defer data-cfasync="false"></script>
<script src="{{ asset('js/isp-admin-resilience.js') }}?v={{ @filemtime(public_path('js/isp-admin-resilience.js')) ?: 1 }}" defer data-cfasync="false"></script>
@if (request()->routeIs('filament.admin.pages.dashboard*', 'filament.admin.pages.dashboard-hub*'))
<script src="{{ asset('js/isp-dashboard-realtime.js') }}?v={{ @filemtime(public_path('js/isp-dashboard-realtime.js')) ?: 1 }}" defer data-cfasync="false"></script>
@endif
