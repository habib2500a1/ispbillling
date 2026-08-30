<div>
    <x-slot name="header">
        {{ __('ISP OS') }}
    </x-slot>

    <style>
        .os-shell { --os-ink:#1e3a5f; --os-muted:#64748b; --os-line:#e8eef5; --os-soft:#f4f7fb; --os-brand:#06ad73; --os-brand-dark:#05885b; }
        .os-shell .os-hero { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:1.15rem 1.25rem; margin-bottom:1rem; }
        .os-shell .os-kicker { font-size:.68rem; letter-spacing:.12em; text-transform:uppercase; font-weight:800; color:var(--os-brand-dark); margin-bottom:.2rem; }
        .os-shell .os-title { margin:0; font-size:1.35rem; font-weight:800; color:var(--os-ink); letter-spacing:-.02em; }
        .os-shell .os-sub { margin:.25rem 0 0; font-size:.86rem; color:var(--os-muted); }
        .os-shell .os-meta { display:flex; flex-wrap:wrap; gap:.45rem; justify-content:flex-end; }
        .os-shell .os-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.28rem .65rem; border-radius:999px; font-size:.72rem; font-weight:700; border:1px solid var(--os-line); background:var(--os-soft); color:var(--os-ink); }
        .os-shell .os-chip.ok { background:rgba(6,173,115,.1); color:var(--os-brand-dark); border-color:rgba(6,173,115,.25); }
        .os-shell .os-chip.warn { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
        .os-shell .os-kpi { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:.95rem 1rem; height:100%; }
        .os-shell .os-kpi .label { font-size:.68rem; letter-spacing:.07em; text-transform:uppercase; color:var(--os-muted); font-weight:700; margin-bottom:.3rem; }
        .os-shell .os-kpi .value { font-size:1.45rem; font-weight:800; color:var(--os-ink); line-height:1.1; }
        .os-shell .os-kpi .meta { font-size:.78rem; color:var(--os-muted); margin-top:.35rem; font-weight:600; }
        .os-shell .os-quick { display:flex; flex-wrap:wrap; gap:.5rem; }
        .os-shell .os-quick a { display:inline-flex; align-items:center; gap:.45rem; padding:.5rem .8rem; border:1px solid var(--os-line); border-radius:10px; background:#fff; color:var(--os-ink); text-decoration:none; font-weight:700; font-size:.82rem; }
        .os-shell .os-quick a:hover { border-color:var(--os-brand); background:rgba(6,173,115,.05); color:var(--os-ink); }
        .os-shell .os-quick a i { color:var(--os-brand-dark); }
        .os-shell .os-toolbar { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:1rem 1.1rem; margin-bottom:1rem; }
        .os-shell .os-tabs { display:flex; flex-wrap:wrap; gap:.4rem; }
        .os-shell .os-tab { border:1px solid var(--os-line); background:#fff; color:var(--os-ink); border-radius:999px; padding:.32rem .7rem; font-size:.75rem; font-weight:700; }
        .os-shell .os-tab.active { background:var(--os-ink); color:#fff; border-color:var(--os-ink); }
        .os-shell .os-search { max-width:280px; border-radius:10px; border-color:#dbe3ee; font-size:.86rem; }
        .os-shell .os-panel { background:#fff; border:1px solid var(--os-line); border-radius:14px; overflow:hidden; height:100%; }
        .os-shell .os-panel .panel-h { padding:.75rem 1rem; border-bottom:1px solid var(--os-line); background:var(--os-soft); display:flex; align-items:center; justify-content:space-between; }
        .os-shell .os-panel .panel-h h6 { margin:0; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; font-weight:800; color:var(--os-ink); }
        .os-shell .os-row { display:flex; align-items:center; gap:.75rem; padding:.72rem 1rem; border-bottom:1px solid #f1f5f9; text-decoration:none; color:inherit; }
        .os-shell .os-row:last-child { border-bottom:0; }
        .os-shell .os-row:hover { background:rgba(6,173,115,.04); }
        .os-shell .os-ico { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:rgba(6,173,115,.1); color:var(--os-brand-dark); }
        .os-shell .os-row .name { font-size:.9rem; font-weight:700; color:var(--os-ink); margin:0; }
        .os-shell .os-row .desc { font-size:.76rem; color:var(--os-muted); margin:0; }
        .os-shell .os-row .sec { font-size:.65rem; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; font-weight:700; }
        .os-shell .os-empty { text-align:center; padding:2.5rem 1rem; color:var(--os-muted); }
        @media (max-width: 575.98px) {
            .os-shell .os-title { font-size:1.15rem; }
            .os-shell .os-kpi .value { font-size:1.2rem; }
            .os-shell .os-search { max-width:100%; }
        }
    </style>

    @php
        $ops = $ops ?? [];
        $env = strtolower((string) ($ops['env'] ?? 'production'));
        $jobsOn = (int) ($ops['jobs_on'] ?? 0);
        $jobsTotal = (int) ($ops['jobs_total'] ?? 0);
    @endphp

    <div class="os-shell" data-os-rev="20260831e">
        <div class="os-hero">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="os-kicker">{{ site_brand() }} · {{ __('Command') }}</div>
                    <h1 class="os-title">{{ __('ISP OS') }}</h1>
                    <p class="os-sub">{{ __('Operations console — subscribers, billing, network, OLT, and support from one desk.') }}</p>
                </div>
                <div class="os-meta">
                    <span class="os-chip {{ $env === 'production' ? 'ok' : 'warn' }}">{{ strtoupper($env) }}</span>
                    <span class="os-chip ok"><span style="width:7px;height:7px;border-radius:50%;background:#06ad73;display:inline-block;"></span> {{ __('Database') }}</span>
                    <span class="os-chip {{ $jobsOn > 0 ? 'ok' : 'warn' }}">{{ __('Jobs') }} {{ $jobsOn }}/{{ $jobsTotal }}</span>
                    <span class="os-chip">{{ $ops['generated_at'] ?? now()->format('d M Y · H:i') }}</span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="os-kpi">
                    <div class="label">{{ __('Subscribers') }}</div>
                    <div class="value">{{ number_format((int) ($ops['subscribers'] ?? 0)) }}</div>
                    <div class="meta">{{ number_format((int) ($ops['active'] ?? 0)) }} {{ __('active') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="os-kpi">
                    <div class="label">{{ __('MikroTik') }}</div>
                    <div class="value">{{ number_format((int) ($ops['routers'] ?? 0)) }}</div>
                    <div class="meta">{{ __('Routers in billing') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="os-kpi">
                    <div class="label">{{ __('OLT') }}</div>
                    <div class="value">{{ number_format((int) ($ops['olts'] ?? 0)) }}</div>
                    <div class="meta">{{ __('Optical nodes') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="os-kpi">
                    <div class="label">{{ __('Open tickets') }}</div>
                    <div class="value">{{ number_format((int) ($ops['open_tickets'] ?? 0)) }}</div>
                    <div class="meta">{{ __('Support queue') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="os-kpi">
                    <div class="label">{{ __('Today collection') }}</div>
                    <div class="value">৳{{ number_format((float) ($ops['today_collection'] ?? 0), 0) }}</div>
                    <div class="meta">{{ __('Received today') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="os-kpi">
                    <div class="label">{{ __('Packages') }}</div>
                    <div class="value">{{ number_format((int) ($ops['packages'] ?? 0)) }}</div>
                    <div class="meta">{{ __(':total modules', ['total' => $total]) }}</div>
                </div>
            </div>
        </div>

        @if(!empty($ops['quick']))
            <div class="os-quick mb-3">
                @foreach($ops['quick'] as $action)
                    <a href="{{ $action['url'] }}"><i class="bi {{ $action['icon'] }}"></i>{{ __($action['label']) }}</a>
                @endforeach
            </div>
        @endif

        <div class="os-toolbar">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="small fw-bold text-uppercase" style="letter-spacing:.08em;color:#64748b;">{{ __('Module catalog') }}</div>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-control form-control-sm os-search" placeholder="{{ __('Search modules…') }}">
            </div>
            <div class="os-tabs">
                <button type="button" class="os-tab {{ $this->groupFilter === null ? 'active' : '' }}" wire:click="setGroup(null)">{{ __('All') }}</button>
                @foreach($groups as $g)
                    <button type="button" class="os-tab {{ $this->groupFilter === $g ? 'active' : '' }}" wire:click="setGroup('{{ $g }}')">{{ __($g) }}</button>
                @endforeach
            </div>
        </div>

        @if(count($modules) === 0)
            <div class="os-panel">
                <div class="os-empty">{{ __('No modules match your search.') }}</div>
            </div>
        @else
            <div class="row g-3">
                @foreach($grouped as $groupName => $items)
                    <div class="col-12 col-xl-6" wire:key="grp-{{ \Illuminate\Support\Str::slug($groupName) }}">
                        <div class="os-panel">
                            <div class="panel-h">
                                <h6>{{ __($groupName) }}</h6>
                                <span class="os-chip">{{ count($items) }}</span>
                            </div>
                            @foreach($items as $mod)
                                <a href="{{ \App\Support\FeatureModuleRegistry::url($mod) }}" class="os-row" wire:key="mod-{{ $mod['slug'] }}">
                                    <div class="os-ico"><i class="bi {{ $mod['icon'] }}"></i></div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="sec">{{ __($mod['section'] ?? $mod['group']) }}</div>
                                        <h6 class="name">{{ __($mod['label']) }}</h6>
                                        <p class="desc text-truncate">{{ __($mod['description']) }}</p>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
