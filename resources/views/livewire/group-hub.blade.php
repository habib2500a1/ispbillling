<div>
    <x-slot name="header">
        {{ __($group) }}
    </x-slot>

    <style>
        .os-shell { --os-ink:#1e3a5f; --os-muted:#64748b; --os-line:#e8eef5; --os-soft:#f4f7fb; --os-brand:#1e3a5f; --os-brand-dark:#1e3a5f; }
        .os-shell .os-hero { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:1.1rem 1.2rem; margin-bottom:1rem; }
        .os-shell .os-kicker { font-size:.68rem; letter-spacing:.12em; text-transform:uppercase; font-weight:800; color:#64748b; }
        .os-shell .os-title { margin:.15rem 0 0; font-size:1.25rem; font-weight:800; color:var(--os-ink); }
        .os-shell .os-row { display:flex; align-items:center; gap:.75rem; padding:.8rem 1rem; border-bottom:1px solid #f1f5f9; text-decoration:none; color:inherit; }
        .os-shell .os-row:last-child { border-bottom:0; }
        .os-shell .os-row:hover { background:#f8fafc; }
        .os-shell .os-panel { background:#fff; border:1px solid var(--os-line); border-radius:14px; overflow:hidden; }
        .os-shell .os-ico { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:#eef4fb; color:#1e3a5f; }
        .os-shell .os-back { display:inline-flex; align-items:center; gap:.4rem; color:var(--os-ink); font-weight:700; font-size:.82rem; text-decoration:none; border:1px solid var(--os-line); border-radius:10px; padding:.4rem .7rem; background:#fff; }
        .os-shell .os-back:hover { border-color:var(--os-brand); color:var(--os-ink); }
    </style>

    <div class="os-shell">
        <div class="os-hero d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="os-kicker">{{ __('ISP OS') }}</div>
                <h1 class="os-title">{{ __($group) }}</h1>
            </div>
            <a href="{{ route('isp-os') }}" class="os-back"><i class="bi bi-arrow-left"></i>{{ __('Back to console') }}</a>
        </div>
        <div class="os-panel">
            @foreach($modules as $mod)
                <a href="{{ \App\Support\FeatureModuleRegistry::url($mod) }}" class="os-row" wire:key="gh-{{ $mod['slug'] }}">
                    <div class="os-ico"><i class="bi {{ $mod['icon'] }}"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="color:#1e3a5f;">{{ __($mod['label']) }}</div>
                        <div class="small text-muted">{{ __($mod['description']) }}</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            @endforeach
        </div>
    </div>
</div>
