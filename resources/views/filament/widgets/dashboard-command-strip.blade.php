<x-filament-widgets::widget>
    <div class="isp-dash-strip">
        <a href="{{ \App\Filament\Pages\NocWall::getUrl() }}" class="isp-dash-strip__btn isp-dash-strip__btn--noc">
            <x-filament::icon icon="heroicon-o-tv" class="h-4 w-4" />
            NOC wall
        </a>
        <a href="{{ \App\Filament\Pages\NetworkTopology::getUrl() }}" class="isp-dash-strip__btn">
            <x-filament::icon icon="heroicon-o-share" class="h-4 w-4" />
            Fiber topology
        </a>
        <a href="{{ \App\Filament\Pages\AiAnalyticsDashboard::getUrl() }}" class="isp-dash-strip__btn">
            <x-filament::icon icon="heroicon-o-sparkles" class="h-4 w-4" />
            AI analytics
        </a>
        <a href="{{ \App\Filament\Pages\SecurityDashboard::getUrl() }}" class="isp-dash-strip__btn">
            <x-filament::icon icon="heroicon-o-shield-check" class="h-4 w-4" />
            Security
        </a>
        <a href="{{ \App\Filament\Pages\TechnicianDashboard::getUrl() }}" class="isp-dash-strip__btn">
            <x-filament::icon icon="heroicon-o-wrench" class="h-4 w-4" />
            Technician
        </a>
        <a href="{{ \App\Filament\Pages\DashboardHub::getUrl() }}" class="isp-dash-strip__btn">
            <x-filament::icon icon="heroicon-o-presentation-chart-line" class="h-4 w-4" />
            Dashboards
        </a>
        <a href="{{ \App\Filament\Pages\BillingOverview::getUrl() }}" class="isp-dash-strip__btn">
            <x-filament::icon icon="heroicon-o-banknotes" class="h-4 w-4" />
            Billing
        </a>
        <a href="{{ \App\Filament\Pages\OperationsHub::getUrl() }}" class="isp-dash-strip__btn isp-dash-strip__btn--muted">
            <x-filament::icon icon="heroicon-o-squares-plus" class="h-4 w-4" />
            All modules
        </a>
        <button
            type="button"
            class="isp-dash-strip__btn isp-dash-strip__btn--muted"
            x-data
            @click="$dispatch('open-modal', { id: 'dashboard-layout-modal' })"
        >
            <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-4 w-4" />
            Layout
        </button>
    </div>

</x-filament-widgets::widget>
