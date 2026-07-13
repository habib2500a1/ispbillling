<div>
    <x-slot name="header">
        {{ __('ISP OS Center') }}
    </x-slot>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h5 class="fw-bold mb-1">{{ __('All anetbd modules') }}</h5>
                    <p class="text-muted small mb-0">{{ __(':total features — billing, NOC, HR, inventory & more from one hub.', ['total' => $total]) }}</p>
                </div>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-control form-control-sm" style="max-width:280px" placeholder="{{ __('Search modules...') }}">
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-sm {{ $groupFilter === null ? 'btn-dark' : 'btn-outline-dark' }}" wire:click="setGroup(null)">{{ __('All') }}</button>
                @foreach($groups as $g)
                    <button type="button" class="btn btn-sm {{ $groupFilter === $g ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setGroup('{{ $g }}')">{{ __($g) }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach($modules as $mod)
            <div class="col-md-6 col-xl-4" wire:key="mod-{{ $mod['slug'] }}">
                <a href="{{ \App\Support\FeatureModuleRegistry::url($mod) }}" class="text-decoration-none" wire:navigate.hover>
                    <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift" style="transition:transform .2s">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-3 p-2 bg-primary bg-opacity-10 text-primary">
                                    <i class="bi {{ $mod['icon'] }} fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted text-uppercase fw-semibold">{{ __($mod['group']) }}</div>
                                    <h6 class="fw-bold text-dark mb-1">{{ __($mod['label']) }}</h6>
                                    <p class="small text-muted mb-0">{{ __($mod['description']) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if(count($modules) === 0)
        <div class="text-center text-muted py-5">{{ __('No modules match your search.') }}</div>
    @endif
</div>
