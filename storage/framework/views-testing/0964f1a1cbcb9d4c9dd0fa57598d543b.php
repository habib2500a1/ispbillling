<script>
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
<link rel="stylesheet" href="<?php echo e(asset('css/admin-saas.css')); ?>?v=<?php echo e(@filemtime(public_path('css/admin-saas.css')) ?: '10'); ?>">
<script src="<?php echo e(asset('js/admin-theme.js')); ?>?v=4"></script>
<script src="<?php echo e(asset('js/admin-sidebar-search.js')); ?>?v=1" defer></script>
<script src="<?php echo e(asset('js/admin-sidebar-layout.js')); ?>?v=2" defer></script>
<script src="<?php echo e(asset('js/isp-admin-resilience.js')); ?>?v=<?php echo e(@filemtime(public_path('js/isp-admin-resilience.js')) ?: 1); ?>" defer></script>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->routeIs('filament.admin.pages.dashboard*', 'filament.admin.pages.dashboard-hub*')): ?>
<script src="<?php echo e(asset('js/isp-dashboard-realtime.js')); ?>?v=<?php echo e(@filemtime(public_path('js/isp-dashboard-realtime.js')) ?: 1); ?>" defer></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /var/www/isp-platform/resources/views/filament/hooks/design-system.blade.php ENDPATH**/ ?>