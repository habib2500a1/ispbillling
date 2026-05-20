<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
    <div
        class="isp-sidebar-search"
        x-show="$store.sidebar.isOpen"
        x-collapse
        x-data="{
            query: '',
            clear() {
                this.query = '';
                window.ispFilterSidebarMenu?.('');
            },
        }"
        x-init="$watch('query', (v) => window.ispFilterSidebarMenu?.(v))"
    >
        <label class="sr-only" for="isp-sidebar-menu-search">Search menu</label>
        <div class="isp-sidebar-search__field">
            <?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => 'heroicon-m-magnifying-glass','class' => 'isp-sidebar-search__icon h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-m-magnifying-glass','class' => 'isp-sidebar-search__icon h-5 w-5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
            <input
                id="isp-sidebar-menu-search"
                type="search"
                x-model.debounce.120ms="query"
                class="isp-sidebar-search__input"
                placeholder="Menu search…"
                autocomplete="off"
                spellcheck="false"
            />
            <button
                type="button"
                class="isp-sidebar-search__clear"
                x-show="query.length > 0"
                x-cloak
                @click="clear()"
                aria-label="Clear menu search"
            >&times;</button>
        </div>
        <p id="isp-sidebar-search-empty" class="isp-sidebar-search__empty" hidden>No menu items match</p>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /var/www/isp-platform/resources/views/filament/hooks/sidebar-menu-search.blade.php ENDPATH**/ ?>