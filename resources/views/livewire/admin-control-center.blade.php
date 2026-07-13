<div>
    <x-slot name="header">{{ __('Admin Control Center') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 mt-1">
        <div>
            <h4 class="mb-1"><i class="bi bi-sliders2 text-primary me-2"></i>{{ __('Admin Control Center') }}</h4>
            <p class="text-muted small mb-0">{{ __('Manage your ISP from admin — no code edits. Site, jobs, SMS, tickets, and deploy sync in one place.') }}</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" wire:click="runMaintenance" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="runMaintenance"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Run system sync') }}</span>
            <span wire:loading wire:target="runMaintenance">{{ __('Syncing...') }}</span>
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="{{ $site['logo'] }}" alt="" class="rounded" style="width: 48px; height: 48px; object-fit: contain;">
                        <div>
                            <h5 class="mb-0 fw-bold">{{ $site['name'] }}</h5>
                            <div class="small text-muted">{{ $site['url'] }}</div>
                        </div>
                    </div>
                    <p class="small text-muted mb-3">{{ __('Branding comes from Site Settings — not hardcoded. Change name, logo, and theme anytime.') }}</p>
                    <a href="{{ route('site-settings') }}" class="btn btn-outline-primary btn-sm" wire:navigate.hover>
                        <i class="bi bi-palette me-1"></i>{{ __('Edit site settings') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 fw-semibold">{{ __('System health') }}</div>
                <div class="card-body pt-0">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded bg-light">
                                <div class="small text-muted">{{ __('Database') }}</div>
                                <div class="fw-bold {{ $health['database'] ? 'text-success' : 'text-danger' }}">{{ $health['database'] ? __('OK') : __('Down') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded bg-light">
                                <div class="small text-muted">{{ __('Pending migrations') }}</div>
                                <div class="fw-bold {{ $health['pending_migrations'] ? 'text-warning' : 'text-success' }}">{{ $health['pending_migrations'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded bg-light">
                                <div class="small text-muted">{{ __('Auto jobs on') }}</div>
                                <div class="fw-bold">{{ $health['automatic_processes'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded bg-light">
                                <div class="small text-muted">{{ __('Open tickets') }}</div>
                                <div class="fw-bold">{{ $health['open_tickets'] }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-3">
                        {{ __('Environment') }}: {{ $health['app_env'] }} · PHP {{ $health['php'] }} ·
                        {{ $health['storage_writable'] ? __('Storage writable') : __('Storage not writable') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($maintenanceOutput)
        <div class="alert {{ $maintenanceOk ? 'alert-success' : 'alert-warning' }} border-0 shadow-sm">
            <div class="fw-semibold mb-2">{{ __('Last maintenance output') }}</div>
            <pre class="mb-0 small" style="white-space: pre-wrap;">{{ $maintenanceOutput }}</pre>
        </div>
    @elseif($last_maintenance)
        <div class="alert alert-light border shadow-sm">
            <div class="fw-semibold">{{ __('Last system sync') }}</div>
            <div class="small text-muted">
                {{ \Carbon\Carbon::parse($last_maintenance['at'])->diffForHumans() }}
                @if(!empty($last_maintenance['by'])) · {{ $last_maintenance['by'] }} @endif
                · {{ ($last_maintenance['ok'] ?? false) ? __('Success') : __('With warnings') }}
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 fw-semibold">{{ __('Code safety') }}</div>
        <div class="card-body pt-0">
            <ul class="small mb-0">
                <li>{{ __('All changes are saved in GitHub (`codepagol/main`) — code will not be lost after push.') }}</li>
                <li>{{ __('After deploy, container auto-runs migrate + sync (or click Run system sync above).') }}</li>
                <li>{{ __('Fix daily ops here: site name, SMS, tickets, cron jobs — no developer needed.') }}</li>
            </ul>
        </div>
    </div>

    <div class="row g-3">
        @foreach($modules as $module)
            <div class="col-12 col-sm-6 col-lg-3">
                <a href="{{ route($module['route']) }}" class="text-decoration-none" wire:navigate.hover>
                    <div class="card border-0 shadow-sm h-100 card-hover">
                        <div class="card-body">
                            <div class="text-primary mb-2"><i class="{{ $module['icon'] }} fs-4"></i></div>
                            <h6 class="fw-bold text-dark mb-1">{{ __($module['label']) }}</h6>
                            <p class="small text-muted mb-0">{{ __($module['description']) }}</p>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
