{{-- Auth login assets — mirrors design-system.blade.php for filament.admin.auth.* (CSS unchanged). --}}
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
{!! \App\Support\AdminSaasStyles::html() !!}
@unless (\App\Support\AdminSaasStyles::bundleIncludesExtras())
<link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: 1 }}">
@endunless
@include('partials.isp-premium-theme', ['tailwind' => false, 'motion' => false, 'fonts' => false])
@include('filament.hooks.auth-head')
<script src="{{ asset('js/admin-theme.js') }}?v={{ @filemtime(public_path('js/admin-theme.js')) ?: 4 }}" defer data-cfasync="false"></script>
