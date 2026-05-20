<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div
        class="fi-global-notification fixed start-0 end-0 top-0 z-50 mx-auto flex max-w-lg justify-center p-4"
        role="status"
    >
        <div
            class="w-full rounded-lg bg-success-600 px-4 py-3 text-sm font-medium text-white shadow-lg dark:bg-success-500"
        >
            <?php echo e(session('success')); ?>

        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('danger')): ?>
    <div
        class="fi-global-notification fixed start-0 end-0 top-0 z-50 mx-auto flex max-w-lg justify-center p-4"
        role="alert"
    >
        <div
            class="w-full rounded-lg bg-danger-600 px-4 py-3 text-sm font-medium text-white shadow-lg dark:bg-danger-500"
        >
            <?php echo e(session('danger')); ?>

        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /var/www/isp-platform/resources/views/filament/flash-banners.blade.php ENDPATH**/ ?>