@auth
    @php
        $onDashboard = request()->routeIs('filament.admin.pages.dashboard');
        $onSubscribers = request()->routeIs('filament.admin.pages.subscriber-lists-hub', 'filament.admin.resources.subscribers.*');
        $onBilling = request()->routeIs(
            'filament.admin.pages.bill-collection',
            'filament.admin.pages.billing-overview',
            'filament.admin.pages.collection-desk-report',
            'filament.admin.pages.collector-mobile',
        );
        $onOps = request()->routeIs('filament.admin.pages.operations-hub', 'filament.admin.pages.dashboard-hub');
    @endphp
    <nav class="isp-mobile-dock" aria-label="Mobile quick menu">
        <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="isp-mobile-dock-item {{ $onDashboard ? 'isp-mobile-dock-item--active' : '' }}">
            <x-filament::icon icon="heroicon-o-home" class="h-5 w-5" />
            <span>Home</span>
        </a>
        <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="isp-mobile-dock-item {{ $onBilling ? 'isp-mobile-dock-item--active' : '' }}">
            <x-filament::icon icon="heroicon-o-currency-bangladeshi" class="h-5 w-5" />
            <span>Collect</span>
        </a>
        <a href="{{ \App\Filament\Pages\SubscriberListsHub::getUrl() }}" class="isp-mobile-dock-item {{ $onSubscribers ? 'isp-mobile-dock-item--active' : '' }}">
            <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
            <span>Users</span>
        </a>
        <a href="{{ \App\Filament\Pages\DashboardHub::getUrl() }}" class="isp-mobile-dock-item {{ $onOps ? 'isp-mobile-dock-item--active' : '' }}">
            <x-filament::icon icon="heroicon-o-squares-2x2" class="h-5 w-5" />
            <span>Hubs</span>
        </a>
        <button
            type="button"
            class="isp-mobile-dock-item"
            title="Theme: light / dark / auto"
            x-data="{ t: window.ispGetTheme?.() || 'system' }"
            @click="
                const order = ['light', 'dark', 'system'];
                const next = order[(order.indexOf(t) + 1) % order.length];
                window.ispSetTheme?.(next);
                t = next;
            "
            @isp-theme-changed.window="t = $event.detail.mode"
        >
            <span class="text-base" x-text="{ light: '☀️', dark: '🌙', system: '◐' }[t] || '◐'"></span>
            <span>Theme</span>
        </button>
    </nav>
@endauth
