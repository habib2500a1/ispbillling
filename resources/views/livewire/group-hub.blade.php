<div>
    <x-slot name="header">
        {{ __($group) }}
    </x-slot>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('isp-os') }}" class="btn btn-sm btn-outline-dark" wire:navigate.hover>{{ __('ISP OS Center') }}</a>
    </div>

    <div class="row g-3">
        @foreach($modules as $mod)
            <div class="col-md-6 col-xl-4" wire:key="gh-{{ $mod['slug'] }}">
                <a href="{{ \App\Support\FeatureModuleRegistry::url($mod) }}" class="text-decoration-none" wire:navigate.hover>
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body d-flex gap-3">
                            <i class="bi {{ $mod['icon'] }} fs-4 text-primary"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">{{ __($mod['label']) }}</h6>
                                <p class="small text-muted mb-0">{{ __($mod['description']) }}</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
