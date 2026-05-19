@php
    $m = $this->getMetrics();
    $company = config('isp.company_name', 'ISP');
@endphp

<x-filament-widgets::widget>
    <div class="isp-dash-hero">
        <div class="isp-dash-hero__inner">
            <div class="isp-dash-hero__top">
                <div>
                    <div class="isp-dash-hero__live">
                        <span class="isp-live-dot" aria-hidden="true"></span>
                        Live operations
                        <span class="isp-dash-hero__sep">·</span>
                        <span>{{ now()->format('l, d M Y') }}</span>
                    </div>
                    <h1>Welcome back, {{ auth()->user()?->name ?? 'Admin' }}</h1>
                    <p class="isp-dash-hero__lead">
                        {{ $company }} command center — subscribers, collections, network health, and support at a glance.
                    </p>
                </div>
                <div class="isp-dash-hero__actions">
                    <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('create') }}" class="isp-quick-pill isp-quick-pill-primary">
                        <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                        New customer
                    </a>
                    <a href="{{ url('/pay') }}" target="_blank" rel="noopener" class="isp-quick-pill">
                        <x-filament::icon icon="heroicon-m-credit-card" class="h-4 w-4" />
                        Public /pay
                    </a>
                    <a href="{{ \App\Filament\Pages\OperationsHub::getUrl() }}" class="isp-quick-pill">All modules</a>
                </div>
            </div>

            <div class="isp-dash-kpi-grid">
                <div class="isp-dash-kpi">
                    <span class="isp-dash-kpi-label">Active subscribers</span>
                    <strong>{{ number_format($m['active_subscribers']) }}</strong>
                    <span class="isp-dash-kpi-sub">+{{ number_format($m['new_subscribers'] ?? 0) }} this month</span>
                </div>
                <div class="isp-dash-kpi">
                    <span class="isp-dash-kpi-label">Online now</span>
                    <strong class="isp-dash-kpi--ok">{{ number_format($m['online_now']) }}</strong>
                    <span class="isp-dash-kpi-sub">PPPoE sessions</span>
                </div>
                <div class="isp-dash-kpi">
                    <span class="isp-dash-kpi-label">Collected today</span>
                    <strong>{{ number_format($m['collected_today'], 0) }}</strong>
                    <span class="isp-dash-kpi-sub">BDT</span>
                </div>
                <div class="isp-dash-kpi">
                    <span class="isp-dash-kpi-label">Outstanding</span>
                    <strong>{{ number_format($m['outstanding'] ?? 0, 0) }}</strong>
                    <span class="isp-dash-kpi-sub">BDT due</span>
                </div>
                <div class="isp-dash-kpi">
                    <span class="isp-dash-kpi-label">Due accounts</span>
                    <strong>{{ number_format($m['due_customers'] ?? 0) }}</strong>
                    <span class="isp-dash-kpi-sub">with open balance</span>
                </div>
                <div class="isp-dash-kpi">
                    <span class="isp-dash-kpi-label">Open tickets</span>
                    <strong class="{{ ($m['open_tickets'] ?? 0) > 0 ? 'isp-dash-kpi--warn' : '' }}">{{ number_format($m['open_tickets'] ?? 0) }}</strong>
                    <span class="isp-dash-kpi-sub">need attention</span>
                </div>
            </div>

            <div class="isp-dash-hero__footer">
                <a href="{{ \App\Filament\Resources\InvoiceResource::getUrl('index') }}" class="isp-quick-pill">Invoices</a>
                <a href="{{ \App\Filament\Resources\PaymentResource::getUrl('index') }}" class="isp-quick-pill">Payments</a>
                <a href="{{ \App\Filament\Pages\BandwidthMonitor::getUrl() }}" class="isp-quick-pill">Bandwidth</a>
                <a href="{{ \App\Filament\Pages\SupportHub::getUrl() }}" class="isp-quick-pill">Support</a>
                <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="isp-quick-pill">Reports</a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
