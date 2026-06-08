@php
    $intel = $fieldIntel;
    $kpis = $intel['kpis'] ?? [];
    $tasks = $intel['tasks'] ?? [];
    $activeTab = $activeTaskTab;
    $taskItems = $tasks[$activeTab]['items'] ?? [];
    $next = $intel['next_visit'] ?? null;
    $alerts = $intel['alerts'] ?? [];
    $assets = $intel['assets'] ?? [];
    $tech = $intel['technician'] ?? ['name' => 'Technician'];
    $visits = $intel['visits'] ?? [];
    $links = $this->hubLinks();
    $mapUrl = $intel['links']['map'] ?? \App\Filament\Pages\FiberPlantMap::getUrl();
@endphp

{!! \App\Support\IspOsStyles::navigatedScript() !!}
<link rel="manifest" href="{{ asset('manifest-field.json') }}">
<script src="{{ asset('js/field-ops.js') }}?v={{ @filemtime(public_path('js/field-ops.js')) ?: 1 }}" defer data-cfasync="false"></script>

<x-filament-panels::page class="field-ops-page">
    <div class="field-shell" data-field-ops wire:poll.60s="refreshIntel">
        {{-- Offline banner --}}
        <div class="field-offline-banner" data-field-offline hidden role="status">
            <x-filament::icon icon="heroicon-o-signal-slash" class="h-4 w-4" />
            <span>Offline mode · changes will sync when connected</span>
        </div>

        {{-- Top bar --}}
        <header class="field-topbar field-glass">
            <div class="field-topbar__brand">
                <p class="field-eyebrow">Field operations OS</p>
                <h1 class="field-topbar__title">Hi, {{ $tech['name'] }}</h1>
            </div>
            <button type="button" class="field-icon-btn" data-field-theme-toggle aria-label="Toggle theme">
                <x-filament::icon icon="heroicon-o-moon" class="h-5 w-5 field-theme-icon--dark" />
                <x-filament::icon icon="heroicon-o-sun" class="h-5 w-5 field-theme-icon--light" />
            </button>
        </header>

        {{-- Search --}}
        <div class="field-search field-glass">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="field-search__icon h-5 w-5" />
            <input
                type="search"
                class="field-search__input"
                placeholder="Customer · ticket · ONU · phone · address…"
                wire:model.live.debounce.300ms="searchQuery"
                autocomplete="off"
            >
        </div>
        @if (filled($searchQuery) && count($searchResults) > 0)
            <div class="field-search-results field-glass">
                @foreach ($searchResults as $hit)
                    <a href="{{ $hit['url'] }}" class="field-search-hit">
                        <span class="field-search-hit__type">{{ $hit['type'] }}</span>
                        <strong>{{ $hit['label'] }}</strong>
                        <span class="field-search-hit__sub">{{ $hit['sub'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- HOME panel --}}
        <section class="field-panel" data-field-panel="home">
            <div class="field-kpi-scroll">
                @foreach ([
                    ['label' => 'Assigned', 'value' => $kpis['assigned_tickets'] ?? 0, 'tone' => 'cyan'],
                    ['label' => 'Today', 'value' => $kpis['visits_today'] ?? 0, 'tone' => 'amber'],
                    ['label' => 'Pending', 'value' => $kpis['pending_tasks'] ?? 0, 'tone' => 'orange'],
                    ['label' => 'Done', 'value' => $kpis['completed_today'] ?? 0, 'tone' => 'emerald'],
                    ['label' => 'Faults', 'value' => $kpis['nearby_faults'] ?? 0, 'tone' => 'rose'],
                    ['label' => 'Assets', 'value' => $kpis['devices_out'] ?? 0, 'tone' => 'violet'],
                ] as $kpi)
                    <article @class(['field-kpi', 'field-kpi--' . $kpi['tone']])>
                        <span class="field-kpi__label">{{ $kpi['label'] }}</span>
                        <strong class="field-kpi__value">{{ $kpi['value'] }}</strong>
                    </article>
                @endforeach
            </div>

            @if ($next)
                <article class="field-hero-card field-hero-card--gradient">
                    <p class="field-hero-card__eyebrow">Next visit</p>
                    <h2 class="field-hero-card__title">{{ $next['customer'] }}</h2>
                    <p class="field-hero-card__sub">{{ $next['subject'] }} · {{ $next['scheduled'] }}</p>
                    <div class="field-hero-card__actions">
                        @if ($next['maps_url'])
                            <a href="{{ $next['maps_url'] }}" target="_blank" rel="noopener" class="field-btn field-btn--white">Navigate</a>
                        @endif
                        @if ($next['phone'])
                            <a href="tel:{{ $next['phone'] }}" class="field-btn field-btn--ghost">Call</a>
                        @endif
                        <a href="{{ $next['url'] }}" class="field-btn field-btn--ghost">Ticket</a>
                    </div>
                </article>
            @endif

            @if (count($alerts) > 0)
                <div class="field-alerts" data-field-alerts>
                    @foreach ($alerts as $alert)
                        <a href="{{ $alert['url'] ?? '#' }}" @class(['field-alert', 'field-alert--' . ($alert['tone'] ?? 'amber')])>
                            <span class="field-alert__label">{{ $alert['label'] }}</span>
                            <strong class="field-alert__value">{{ $alert['value'] }}</strong>
                            <span class="field-alert__hint">{{ $alert['hint'] ?? '' }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="field-section-head">
                <h3>Today's route</h3>
                <span>{{ count($visits) }} stops</span>
            </div>
            <div class="field-route-list">
                @forelse ($visits as $visit)
                    <a href="{{ $visit['url'] }}" class="field-route-card">
                        <span class="field-route-card__time">{{ $visit['scheduled'] }}</span>
                        <div>
                            <strong>{{ $visit['customer'] }}</strong>
                            <p>{{ $visit['subject'] }}</p>
                            <span @class(['field-badge', 'field-badge--' . $visit['status']])>{{ str_replace('_', ' ', $visit['status']) }}</span>
                        </div>
                        @if ($visit['maps_url'])
                            <span class="field-route-card__nav" onclick="event.preventDefault();event.stopPropagation();window.open('{{ $visit['maps_url'] }}','_blank')">
                                <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" />
                            </span>
                        @endif
                    </a>
                @empty
                    <p class="field-empty">No scheduled visits for you.</p>
                @endforelse
            </div>

            <div class="field-module-grid">
                @foreach (array_slice($links, 0, 4) as $mod)
                    <a href="{{ $mod['url'] }}" class="field-module-card">
                        <x-filament::icon :icon="$mod['icon']" class="field-module-card__icon h-5 w-5" />
                        <strong>{{ $mod['label'] }}</strong>
                        <span>{{ $mod['desc'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- TASKS panel --}}
        <section class="field-panel" data-field-panel="tasks" hidden>
            <div class="field-tabs" role="tablist">
                @foreach (['new' => 'New', 'assigned' => 'Assigned', 'in_progress' => 'Active', 'completed' => 'Done', 'escalated' => 'Escalated'] as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        @class(['field-tab', 'field-tab--active' => $activeTab === $key])
                        wire:click="setTaskTab('{{ $key }}')"
                    >
                        {{ $label }}
                        <span class="field-tab__count">{{ $tasks[$key]['count'] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
            <div class="field-task-list">
                @forelse ($taskItems as $task)
                    <article class="field-task-card">
                        <div class="field-task-card__head">
                            <span @class(['field-badge', 'field-badge--' . ($task['priority'] ?? 'normal')])>{{ $task['priority'] ?? $task['status'] }}</span>
                            <span class="field-task-card__kind">{{ $task['kind'] ?? 'ticket' }}</span>
                        </div>
                        <h4>{{ $task['subject'] ?? ($task['customer'] ?? 'Task') }}</h4>
                        <p>{{ $task['customer'] ?? '' }} @if(!empty($task['code']))· {{ $task['code'] }}@endif</p>
                        <div class="field-task-card__actions">
                            <a href="{{ $task['url'] }}" class="field-btn field-btn--sm field-btn--primary">Open</a>
                            @if (!empty($task['maps_url']))
                                <a href="{{ $task['maps_url'] }}" target="_blank" class="field-btn field-btn--sm field-btn--ghost">Navigate</a>
                            @endif
                            @php $c360Id = $task['ticket_id'] ?? (($task['kind'] ?? '') === 'ticket' ? ($task['id'] ?? null) : null); @endphp
                            @if ($c360Id)
                                <button type="button" class="field-btn field-btn--sm field-btn--ghost" data-field-c360="{{ $c360Id }}">Customer 360</button>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="field-empty">No tasks in this queue.</p>
                @endforelse
            </div>
        </section>

        {{-- MAP panel --}}
        <section class="field-panel" data-field-panel="map" hidden>
            <div class="field-map-card field-glass">
                <x-filament::icon icon="heroicon-o-map" class="h-10 w-10 field-map-card__icon" />
                <h3>GIS Map Center</h3>
                <p>Customers · ONUs · OLTs · splitters · fiber routes · technician pins</p>
                <div class="field-map-card__actions">
                    <a href="{{ $mapUrl }}" class="field-btn field-btn--primary">Open fiber plant map</a>
                    <a href="{{ $intel['links']['faults'] ?? '#' }}" class="field-btn field-btn--ghost">Fault overlay</a>
                </div>
            </div>
            <div class="field-nav-grid">
                <a href="{{ $mapUrl }}" class="field-nav-tile">
                    <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
                    <span>Customer pins</span>
                </a>
                <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::getUrl() }}" class="field-nav-tile">
                    <x-filament::icon icon="heroicon-o-signal" class="h-5 w-5" />
                    <span>ONU layer</span>
                </a>
                <a href="{{ \App\Filament\Pages\OltHub::getUrl() }}" class="field-nav-tile">
                    <x-filament::icon icon="heroicon-o-server" class="h-5 w-5" />
                    <span>OLT layer</span>
                </a>
                <a href="{{ $mapUrl }}" class="field-nav-tile">
                    <x-filament::icon icon="heroicon-o-map-pin" class="h-5 w-5" />
                    <span>Fiber routes</span>
                </a>
            </div>
        </section>

        {{-- SCAN panel --}}
        <section class="field-panel" data-field-panel="scan" hidden>
            <div class="field-scanner field-glass">
                <div id="field-qr-reader" class="field-scanner__viewport"></div>
                <p class="field-scanner__hint">Scan ONU · router · asset · inventory barcode</p>
                <input type="text" class="field-scanner__manual" placeholder="Or type serial / barcode…" data-field-manual-scan>
                <div class="field-scanner__results" data-field-scan-results hidden></div>
            </div>
        </section>

        {{-- MORE panel --}}
        <section class="field-panel" data-field-panel="more" hidden>
            <div class="field-section-head"><h3>Assigned assets</h3><span>{{ count($assets) }}</span></div>
            <div class="field-asset-list">
                @forelse ($assets as $asset)
                    <article class="field-asset-card">
                        <strong>{{ $asset['device'] }}</strong>
                        <span class="font-mono text-xs">{{ $asset['serial'] }}</span>
                        <p>{{ strtoupper($asset['type']) }} · {{ $asset['customer'] }} · due {{ $asset['due'] }}</p>
                    </article>
                @empty
                    <p class="field-empty">No devices checked out.</p>
                @endforelse
            </div>

            <div class="field-section-head"><h3>Modules</h3></div>
            <div class="field-more-list">
                @foreach ($links as $mod)
                    <a href="{{ $mod['url'] }}" class="field-more-row">
                        <x-filament::icon :icon="$mod['icon']" class="h-5 w-5" />
                        <div>
                            <strong>{{ $mod['label'] }}</strong>
                            <span>{{ $mod['desc'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="field-section-head"><h3>Field visit tools</h3></div>
            <div class="field-evidence-grid">
                <div class="field-evidence-tile">Before photo</div>
                <div class="field-evidence-tile">After photo</div>
                <div class="field-evidence-tile">Fault photo</div>
                <div class="field-evidence-tile">Installation</div>
            </div>
            <p class="field-footnote">Photos attach via ticket workspace — existing upload workflow preserved.</p>
        </section>

        {{-- Customer 360 drawer --}}
        <div class="field-drawer" data-field-drawer hidden>
            <div class="field-drawer__backdrop" data-field-drawer-close></div>
            <div class="field-drawer__sheet field-glass">
                <header class="field-drawer__head">
                    <h3>Customer 360</h3>
                    <button type="button" class="field-icon-btn" data-field-drawer-close aria-label="Close">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </header>
                <div class="field-drawer__body" data-field-drawer-body>
                    <div class="field-skeleton field-skeleton--card"></div>
                    <div class="field-skeleton field-skeleton--line"></div>
                    <div class="field-skeleton field-skeleton--line"></div>
                </div>
            </div>
        </div>

        {{-- Bottom navigation --}}
        <nav class="field-bottom-nav field-glass" aria-label="Field operations">
            <button type="button" class="field-nav-item field-nav-item--active" data-field-nav="home">
                <x-filament::icon icon="heroicon-o-home" class="h-5 w-5" />
                <span>Home</span>
            </button>
            <button type="button" class="field-nav-item" data-field-nav="tasks">
                <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-5 w-5" />
                <span>Tasks</span>
            </button>
            <button type="button" class="field-nav-item" data-field-nav="map">
                <x-filament::icon icon="heroicon-o-map" class="h-5 w-5" />
                <span>Map</span>
            </button>
            <button type="button" class="field-nav-item" data-field-nav="scan">
                <x-filament::icon icon="heroicon-o-qr-code" class="h-5 w-5" />
                <span>Scan</span>
            </button>
            <button type="button" class="field-nav-item" data-field-nav="more">
                <x-filament::icon icon="heroicon-o-ellipsis-horizontal" class="h-5 w-5" />
                <span>More</span>
            </button>
        </nav>
    </div>
</x-filament-panels::page>

<script data-cfasync="false">
    window.FIELD_OPS_C360_URL = @json(url('/admin/field-technicians'));
</script>
