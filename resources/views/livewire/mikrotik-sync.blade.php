<div class="zoom-in" wire:key="mikrotik-list">
    <x-slot name="header">
        {{ __('Mikrotik List') }}
    </x-slot>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="p-1 d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-primary" wire:click="toggleForm">
                        {{ $showForm ? __('Hide This') : __('Add Mikrotik') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        wire:click="allSync"
                        wire:loading.attr="disabled"
                        wire:confirm="{{ __('Refresh online PPP sessions only? This will NOT create customers.') }}">
                        <span wire:loading.remove wire:target="allSync"><i class="bi bi-wifi"></i> {{ __('Refresh online') }}</span>
                        <span wire:loading wire:target="allSync" class="spinner-border spinner-border-sm" role="status"></span>
                    </button>
                    <span class="text-muted small ms-1">{{ trans_choice(':count router|:count routers', $routers->total(), ['count' => $routers->total()]) }} · {{ __('Customers: Import users → pick only who you want') }}</span>
                </div>

                @if ($showForm)
                    <div class="card card-body mt-2" id="mikrotik-router-form" wire:key="router-form-{{ $RouterListId ?: 'new' }}">
                        @if ($RouterListId)
                            <div class="alert alert-info py-2 px-3 mb-2 small d-flex flex-wrap justify-content-between gap-2">
                                <span>{{ __('Editing') }}: <strong>{{ $router_name }}</strong> (#{{ $RouterListId }}). {{ __('Leave password blank to keep current.') }}</span>
                                <a href="{{ route('mikrotik-sync') }}" class="btn btn-link btn-sm p-0">{{ __('Cancel edit') }}</a>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('mikrotik.store') }}" autocomplete="off">
                            @csrf
                            @if ($RouterListId)
                                <input type="hidden" name="router_id" value="{{ $RouterListId }}">
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger py-2 px-3 mb-2" style="font-size: 0.85rem;">
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="row g-2 form-group">
                                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                                    <label class="form-label small mb-0">{{ __('Router Name') }}</label>
                                    <input class="form-control form-control-sm" type="text" name="router_name"
                                        value="{{ old('router_name', $router_name) }}" placeholder="{{ __('Router Name') }}"
                                        required autocomplete="off">
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                                    <label class="form-label small mb-0">{{ __('IP Address') }}</label>
                                    <input class="form-control form-control-sm" type="text" name="ip_address"
                                        value="{{ old('ip_address', $ip_address) }}" placeholder="{{ __('IP Address') }}"
                                        required autocomplete="off">
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                                    <label class="form-label small mb-0">{{ __('Username') }}</label>
                                    <input class="form-control form-control-sm" type="text" name="username"
                                        value="{{ old('username', $username) }}" placeholder="{{ __('MikroTik user') }}"
                                        required autocomplete="off" data-lpignore="true" data-1p-ignore="true">
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                                    <label class="form-label small mb-0">{{ __('Password') }}</label>
                                    <input class="form-control form-control-sm" type="password" name="password"
                                        placeholder="{{ $RouterListId ? __('Leave blank to keep') : __('Password') }}"
                                        @if(!$RouterListId) required @endif
                                        autocomplete="new-password" data-lpignore="true" data-1p-ignore="true">
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                                    <label class="form-label small mb-0">{{ __('SSH Port') }}</label>
                                    <input class="form-control form-control-sm" type="number" name="ssh_port"
                                        value="{{ old('ssh_port', $ssh_port) }}" placeholder="22" autocomplete="off">
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                                    <label class="form-label small mb-0">{{ __('API Port') }}</label>
                                    <input class="form-control form-control-sm" type="number" name="api_port"
                                        value="{{ old('api_port', $api_port ?: 8728) }}" placeholder="8728" autocomplete="off">
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                <button class="btn btn-sm btn-primary" type="submit">
                                    {{ $RouterListId ? __('Update Router') : __('Submit') }}
                                </button>
                                <span class="text-muted small">{{ __('API port default 8728 if empty.') }}</span>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Import moved to full page: /mikrotik/{id}/import --}}
            </div>

            <div class="row g-3 mt-3">
                @forelse ($routers as $router)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3" wire:key="router-card-{{ $router->id }}">
                        <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden d-flex flex-column">
                            <div class="px-3 py-2 text-white" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="overflow-hidden">
                                        <h6 class="fw-bold mb-0 text-truncate text-white">{{ $router->router_name }}</h6>
                                        <small class="text-white-50" style="font-size: 0.75rem;"><i class="bi bi-laptop me-1"></i>{{ $router->ip_address ?: __('Re-enter IP') }}</small>
                                    </div>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-white bg-opacity-20" style="width: 32px; height: 32px;">
                                        <i class="bi bi-router text-info"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body bg-light p-3 d-flex flex-column justify-content-between">
                                <div class="mb-3" style="font-size: 0.8rem;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">{{ __('Username') }}:</span>
                                        <span class="fw-bold">{{ $router->username ?: '••••' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">{{ __('SSH Port') }}:</span>
                                        <span class="fw-bold">{{ $router->ssh_port ?: '-' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">{{ __('API Port') }}:</span>
                                        <span class="fw-bold">{{ $router->api_port ?: '-' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <span class="text-muted">{{ __('Status') }}:</span>
                                        <span class="badge bg-{{ $router->action === 'connected' ? 'success' : 'secondary' }}">
                                            {{ $router->action === 'connected' ? __('Online') : __('Offline') }}
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">{{ __('PPP Online') }}:</span>
                                        <span class="badge bg-{{ ($router->online_count ?? 0) > 0 ? 'success' : 'secondary' }}">
                                            <i class="bi bi-broadcast me-1"></i>{{ $router->online_count ?? 0 }}
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">{{ __('PPP users') }}:</span>
                                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                            <i class="bi bi-hdd-network me-1"></i>{{ $router->ppp_user_count ?? 0 }}
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">{{ __('Customers') }}:</span>
                                        <a href="{{ route('customers.index') }}" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 text-decoration-none">
                                            <i class="bi bi-people-fill me-1"></i>{{ $router->customer_count ?? 0 }}
                                        </a>
                                    </div>
                                </div>

                                <div class="pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @if($router->action === 'connected')
                                            <button type="button" class="btn btn-success" wire:click="setConnected({{ $router->id }}, false)"
                                                wire:loading.attr="disabled" wire:target="setConnected({{ $router->id }}, false)">
                                                <span wire:loading.remove wire:target="setConnected({{ $router->id }}, false)">{{ __('Online') }}</span>
                                                <span wire:loading wire:target="setConnected({{ $router->id }}, false)" class="spinner-border spinner-border-sm"></span>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-outline-secondary" wire:click="setConnected({{ $router->id }}, true)"
                                                wire:loading.attr="disabled" wire:target="setConnected({{ $router->id }}, true)">
                                                <span wire:loading.remove wire:target="setConnected({{ $router->id }}, true)">{{ __('Connect') }}</span>
                                                <span wire:loading wire:target="setConnected({{ $router->id }}, true)" class="spinner-border spinner-border-sm"></span>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <a href="{{ route('mikrotik.import', $router->id) }}"
                                            class="btn btn-sm btn-primary"
                                            title="{{ __('Import PPP users') }}">
                                            <i class="bi bi-cloud-download me-1"></i>{{ __('Import users') }}
                                        </a>
                                        <form method="POST" action="{{ route('mikrotik.destroy', $router->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this MikroTik router?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger p-1" title="{{ __('Delete') }}" type="submit">
                                                <i class="bi bi-trash" style="font-size: 0.85rem;"></i>
                                            </button>
                                        </form>
                                        <a class="btn btn-sm btn-outline-info p-1" title="{{ __('Edit') }}"
                                            href="{{ route('mikrotik-sync', ['edit' => $router->id]) }}">
                                            <i class="bi bi-pencil-square" style="font-size: 0.85rem;"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <p class="text-muted mb-0">{{ __('No MikroTik routers yet. Click Add Mikrotik to create one.') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="pagination">
                {{ $routers->links() }}
            </div>
        </div>
    </div>
</div>
