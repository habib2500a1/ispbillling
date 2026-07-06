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

    $moreLinks = array_values(array_filter([
        ($cap->canBilling() && InvoiceResource::canCreate())
            ? ['label' => 'Create invoice', 'url' => InvoiceResource::getUrl('create')]
            : null,
        $cap->canPayments()
            ? ['label' => 'Payments', 'url' => PaymentResource::getUrl('index')]
            : null,
        ($cap->canMikrotik() && MikrotikDashboard::canAccess())
            ? ['label' => 'Routers', 'url' => MikrotikDashboard::getUrl()]
            : null,
        ($cap->canReports() && ReportsHub::canAccess())
            ? ['label' => 'Reports', 'url' => ReportsHub::getUrl()]
            : null,
        ($cap->canSms() && SmsGatewaySetup::canAccess())
            ? ['label' => 'SMS', 'url' => SmsGatewaySetup::getUrl()]
            : null,
        $cap->canCustomers()
            ? ['label' => 'Suspended', 'url' => ListSuspendedCustomers::getUrl()]
            : null,
    ]));
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
            <a href="{{ BillCollectionDesk::getUrl() }}" class="isp-quick-pill isp-quick-pill--collection">Collection desk</a>
        @endif
        @if ($cap->canMikrotik())
            <a href="{{ OnlineClientsMonitoring::getUrl() }}" class="isp-quick-pill isp-quick-pill--online">Online users</a>
        @endif
        @if ($cap->canBilling() && InvoiceResource::canCreate())
            <a href="{{ InvoiceResource::getUrl('create') }}" class="isp-quick-pill isp-quick-pill--invoice isp-dash-header__action--desktop">Create invoice</a>
        @endif
        @if ($cap->canPayments())
            <a href="{{ PaymentResource::getUrl('index') }}" class="isp-quick-pill isp-quick-pill--payments isp-dash-header__action--desktop">Payments</a>
        @endif
        @if ($cap->canMikrotik() && MikrotikDashboard::canAccess())
            <a href="{{ MikrotikDashboard::getUrl() }}" class="isp-quick-pill isp-quick-pill--network isp-dash-header__action--desktop">Routers</a>
        @endif
        @if ($cap->canReports() && ReportsHub::canAccess())
            <a href="{{ ReportsHub::getUrl() }}" class="isp-quick-pill isp-quick-pill--reports isp-dash-header__action--desktop">Reports</a>
        @endif
        @if ($cap->canSms() && SmsGatewaySetup::canAccess())
            <a href="{{ SmsGatewaySetup::getUrl() }}" class="isp-quick-pill isp-quick-pill--sms isp-dash-header__action--desktop">SMS</a>
        @endif
        @if ($cap->canCustomers())
            <a href="{{ ListSuspendedCustomers::getUrl() }}" class="isp-quick-pill isp-quick-pill--warning isp-dash-header__action--desktop">Suspended</a>
        @endif

        @if ($moreLinks !== [])
            <details class="isp-dash-header__more">
                <summary class="isp-quick-pill isp-dash-header__more-toggle">More</summary>
                <div class="isp-dash-header__more-menu">
                    @foreach ($moreLinks as $link)
                        <a href="{{ $link['url'] }}" class="isp-dash-header__more-link">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </details>
        @endif
    </nav>
</header>
