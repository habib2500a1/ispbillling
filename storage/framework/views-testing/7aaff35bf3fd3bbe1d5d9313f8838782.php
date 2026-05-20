<?php
    $stats = $this->getMonitoringStats();
    $sync = $this->getSyncStatus();
    $pollSeconds = (int) config('bandwidth.live_page_poll_seconds', 60);
    $syncAt = ! empty($sync['updated_at'])
        ? rescue(fn () => \Carbon\Carbon::parse($sync['updated_at'])->format('d M Y h:i A'), $sync['updated_at'])
        : null;
?>

<?php if (isset($component)) { $__componentOriginal166a02a7c5ef5a9331faf66fa665c256 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament-panels::components.page.index','data' => ['class' => 'isp-online-clients-page']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament-panels::page'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'isp-online-clients-page']); ?>
    <div
        class="space-y-4"
        <?php if($pollSeconds > 0): ?>
            wire:poll.<?php echo e($pollSeconds); ?>s="refreshLiveData"
        <?php endif; ?>
    >
        <section class="isp-online-clients-hero">
            <div>
                <p class="isp-online-clients-hero__eyebrow">Network operations</p>
                <h2 class="isp-online-clients-hero__title">Live PPP / online clients</h2>
                <p class="isp-online-clients-hero__sub">
                    Real-time sessions from MikroTik — login, logout, client IP, router NAS, MAC, and traffic.
                </p>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($syncAt): ?>
                <div class="isp-online-clients-hero__sync">
                    <span class="isp-live-dot" aria-hidden="true"></span>
                    <div>
                        <strong>Last sync</strong>
                        <span><?php echo e($syncAt); ?></span>
                        <span class="block text-xs opacity-80">
                            Router: <?php echo e(number_format((int) ($sync['api']['sessions'] ?? 0))); ?> sessions
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($sync['matched_subscribers'])): ?>
                                · Matched <?php echo e(number_format((int) $sync['matched_subscribers'])); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        <div class="isp-online-clients-stats">
            <div class="isp-online-clients-stat isp-online-clients-stat--blue">
                <span class="isp-online-clients-stat__label">PPP subscribers</span>
                <strong><?php echo e(number_format($stats['total'])); ?></strong>
            </div>
            <div class="isp-online-clients-stat isp-online-clients-stat--teal">
                <span class="isp-online-clients-stat__label">Online now</span>
                <strong><?php echo e(number_format($stats['online'])); ?></strong>
            </div>
            <div class="isp-online-clients-stat isp-online-clients-stat--slate">
                <span class="isp-online-clients-stat__label">Offline</span>
                <strong><?php echo e(number_format($stats['offline'])); ?></strong>
            </div>
            <div class="isp-online-clients-stat isp-online-clients-stat--violet">
                <span class="isp-online-clients-stat__label">DB active sessions</span>
                <strong><?php echo e(number_format($stats['active_sessions'])); ?></strong>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['unmatched_hint']): ?>
            <div class="isp-online-clients-alert" role="status">
                Router reports active sessions but no subscriber is marked online.
                Click <strong>Sync live sessions</strong> or check PPP usernames match MikroTik secrets.
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pollSeconds > 0): ?>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Auto-refresh every <?php echo e($pollSeconds); ?>s while this page is open.
                Use filter <strong>Online only</strong> to hide offline users.
            </p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="isp-online-clients-table-wrap">
            <?php echo e($this->table); ?>

        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $attributes = $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $component = $__componentOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?>
<?php /**PATH /var/www/isp-platform/resources/views/filament/pages/online-clients-monitoring.blade.php ENDPATH**/ ?>