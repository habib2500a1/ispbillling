<div>
    <x-slot name="header">
        {{ $title }}
    </x-slot>

    <style>
        .os-shell { --os-ink:#1e3a5f; --os-muted:#64748b; --os-line:#e8eef5; --os-soft:#f4f7fb; --os-brand:#06ad73; --os-brand-dark:#05885b; }
        .os-shell .os-hero { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:1.1rem 1.2rem; margin-bottom:1rem; }
        .os-shell .os-kicker { font-size:.68rem; letter-spacing:.12em; text-transform:uppercase; font-weight:800; color:var(--os-brand-dark); }
        .os-shell .os-title { margin:.15rem 0 0; font-size:1.25rem; font-weight:800; color:var(--os-ink); }
        .os-shell .os-sub { margin:.3rem 0 0; color:var(--os-muted); font-size:.86rem; }
        .os-shell .os-kpi { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:.95rem 1rem; height:100%; }
        .os-shell .os-kpi .label { font-size:.68rem; letter-spacing:.07em; text-transform:uppercase; color:var(--os-muted); font-weight:700; }
        .os-shell .os-kpi .value { font-size:1.45rem; font-weight:800; color:var(--os-ink); }
        .os-shell .os-quick a { display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .75rem; border:1px solid var(--os-line); border-radius:10px; background:#fff; color:var(--os-ink); text-decoration:none; font-weight:700; font-size:.82rem; }
        .os-shell .os-quick a:hover { border-color:var(--os-brand); color:var(--os-ink); }
        .os-shell .os-panel { background:#fff; border:1px solid var(--os-line); border-radius:14px; overflow:hidden; }
        .os-shell .os-notice { background:var(--os-soft); border:1px solid var(--os-line); border-radius:12px; padding:.85rem 1rem; color:#334155; font-size:.88rem; }
        .os-shell table { font-size:.86rem; }
        .os-shell thead th { font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; color:var(--os-muted); }
    </style>

    <div class="os-shell">
        <div class="os-hero d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <div class="os-kicker">{{ __('ISP OS') }}</div>
                <h1 class="os-title">{{ $title }}</h1>
                @if(!empty($description))
                    <p class="os-sub">{{ $description }}</p>
                @endif
            </div>
            <a href="{{ route('isp-os') }}" style="display:inline-flex;align-items:center;gap:.4rem;border:1px solid #e8eef5;border-radius:10px;padding:.4rem .7rem;font-weight:700;font-size:.82rem;color:#1e3a5f;text-decoration:none;background:#fff;">{{ __('Back to console') }}</a>
        </div>

        @if(!empty($kpis))
            <div class="row g-3 mb-3">
                @foreach($kpis as $kpi)
                    <div class="col-6 col-md-3">
                        <div class="os-kpi">
                            <div class="label">{{ $kpi['label'] }}</div>
                            <div class="value text-{{ $kpi['color'] ?? 'dark' }}">{{ $kpi['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if(!empty($actions))
            <div class="os-quick d-flex flex-wrap gap-2 mb-3">
                @foreach($actions as $action)
                    <a href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                @endforeach
            </div>
        @endif

        @if(!empty($notice))
            <div class="os-notice mb-3">{{ $notice }}</div>
        @endif

        @if(!empty($columns) && !empty($rows))
            <div class="os-panel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                @foreach($columns as $col)
                                    <th>{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    @foreach($columns as $col)
                                        <td>{{ $row[$col['key']] ?? '—' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">{{ __('No data yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
