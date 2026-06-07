@php
    use App\Support\Rbac\StaffCapability;
    use App\Filament\Resources\CustomerResource;
    use App\Filament\Resources\CustomerResource\Pages\ListSuspendedCustomers;
    use App\Filament\Resources\InvoiceResource;
    use App\Filament\Resources\PaymentResource;
    use App\Filament\Pages\BillCollectionDesk;
    use App\Filament\Pages\OnlineClientsMonitoring;
    use App\Filament\Pages\MikrotikDashboard;
    use App\Filament\Pages\ReportsHub;
    use App\Filament\Pages\SmsGatewaySetup;

    $cap = StaffCapability::for(auth()->user());
    $company = config('isp.company_name', config('app.name'));
@endphp

<header class="isp-dash-header isp-dash-header--hero" aria-label="Dashboard command header">
    <div class="isp-dash-header__glow" aria-hidden="true"></div>
    <div class="isp-dash-header__main">
        <div class="isp-dash-header__welcome">
            <div class="isp-dash-header__live">
                <span class="isp-live-dot" aria-hidden="true"></span>
                Operations center
                <span class="isp-dash-header__badge">Live</span>
            </div>
            <h1 class="isp-dash-header__title">Welcome, {{ auth()->user()?->name }}</h1>
            <p class="isp-dash-header__meta">
                <span class="isp-dash-header__company">{{ $company }}</span>
                <span class="isp-dash-header__sep">·</span>
                <time datetime="{{ now()->toIso8601String() }}">{{ now()->format('l, d F Y') }}</time>
                <span class="isp-dash-header__sep">·</span>
                <span
                    class="isp-dash-header__clock"
                    x-data="{
                        time: '',
                        init() {
                            this.tick();
                            this._timer = setInterval(() => this.tick(), 1000);
                        },
                        tick() {
                            this.time = new Intl.DateTimeFormat(undefined, {
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit',
                                hour12: true,
                            }).format(new Date());
                        },
                    }"
                    x-text="time"
                    aria-live="polite"
                ></span>
            </p>
        </div>

        <div class="isp-dash-header__toolbar">
            <button
                type="button"
                class="isp-dash-header__icon-btn"
                x-data
                @click="window.dispatchEvent(new CustomEvent('isp-open-command-palette'))"
                title="Search (Ctrl+K)"
                aria-label="Open search"
            >
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5" />
            </button>
            <button
                type="button"
                class="isp-dash-header__icon-btn isp-dash-header__icon-btn--accent"
                @click="$dispatch('open-layout-customizer')"
                title="Customize dashboard layout"
                aria-label="Customize dashboard layout"
            >
                <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-5 w-5" />
            </button>
        </div>
    </div>

    <nav class="isp-dash-header__actions" aria-label="Quick actions">
        @if ($cap->canCustomers())
            <a href="{{ CustomerResource::getUrl('create') }}" class="isp-quick-pill isp-quick-pill-primary">
                <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                New subscriber
            </a>
        @endif
        @if ($cap->canCollect() || $cap->canPayments())
            <a href="{{ BillCollectionDesk::getUrl() }}" class="isp-quick-pill">Collection desk</a>
        @endif
        @if ($cap->canMikrotik())
            <a href="{{ OnlineClientsMonitoring::getUrl() }}" class="isp-quick-pill">Online users</a>
        @endif
        @if ($cap->canBilling() && InvoiceResource::canCreate())
            <a href="{{ InvoiceResource::getUrl('create') }}" class="isp-quick-pill isp-dash-header__action--desktop">Create invoice</a>
        @endif
        @if ($cap->canPayments())
            <a href="{{ PaymentResource::getUrl('index') }}" class="isp-quick-pill isp-dash-header__action--desktop">Payments</a>
        @endif
        @if ($cap->canMikrotik() && MikrotikDashboard::canAccess())
            <a href="{{ MikrotikDashboard::getUrl() }}" class="isp-quick-pill isp-dash-header__action--desktop">Routers</a>
        @endif
        @if ($cap->canReports() && ReportsHub::canAccess())
            <a href="{{ ReportsHub::getUrl() }}" class="isp-quick-pill isp-dash-header__action--desktop">Reports</a>
        @endif
        @if ($cap->canSms() && SmsGatewaySetup::canAccess())
            <a href="{{ SmsGatewaySetup::getUrl() }}" class="isp-quick-pill isp-dash-header__action--desktop">SMS</a>
        @endif
        @if ($cap->canCustomers())
            <a href="{{ ListSuspendedCustomers::getUrl() }}" class="isp-quick-pill isp-dash-header__action--desktop">Suspended</a>
        @endif

        <details class="isp-dash-header__more">
            <summary class="isp-quick-pill isp-dash-header__more-toggle">More</summary>
            <div class="isp-dash-header__more-menu">
                @if ($cap->canBilling() && InvoiceResource::canCreate())
                    <a href="{{ InvoiceResource::getUrl('create') }}" class="isp-dash-header__more-link isp-dash-header__action--mobile-only">Create invoice</a>
                @endif
                @if ($cap->canPayments())
                    <a href="{{ PaymentResource::getUrl('index') }}" class="isp-dash-header__more-link isp-dash-header__action--mobile-only">Payments</a>
                @endif
                @if ($cap->canMikrotik() && MikrotikDashboard::canAccess())
                    <a href="{{ MikrotikDashboard::getUrl() }}" class="isp-dash-header__more-link isp-dash-header__action--mobile-only">Routers</a>
                @endif
                @if ($cap->canReports() && ReportsHub::canAccess())
                    <a href="{{ ReportsHub::getUrl() }}" class="isp-dash-header__more-link isp-dash-header__action--mobile-only">Reports</a>
                @endif
                @if ($cap->canSms() && SmsGatewaySetup::canAccess())
                    <a href="{{ SmsGatewaySetup::getUrl() }}" class="isp-dash-header__more-link isp-dash-header__action--mobile-only">SMS</a>
                @endif
                @if ($cap->canCustomers())
                    <a href="{{ ListSuspendedCustomers::getUrl() }}" class="isp-dash-header__more-link isp-dash-header__action--mobile-only">Suspended</a>
                @endif
            </div>
        </details>
    </nav>
</header>
