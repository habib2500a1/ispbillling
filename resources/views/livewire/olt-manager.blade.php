<div>
    <x-slot name="header">
        {{ __('OLT Management') }}
    </x-slot>

    @if(! $snmpAvailable)
        <div class="alert alert-warning">
            {{ __('PHP ext-snmp is not loaded. Install php-snmp in the container to run SNMP checks.') }}
        </div>
    @endif

    @if($lastCheckMessage)
        <div class="alert {{ $lastCheckOk ? 'alert-success' : 'alert-danger' }} white-space-pre-wrap" style="white-space: pre-wrap;">
            {{ $lastCheckMessage }}
        </div>
    @endif

    @if($mode === 'list')
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="d-flex gap-2 align-items-center flex-grow-1">
                    <input type="search" class="form-control" style="max-width: 280px;" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search OLT...') }}">
                </div>
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i class="bi bi-plus-lg"></i> {{ __('Add OLT') }}
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('IP / SNMP') }}</th>
                            <th>{{ __('Vendor') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Health') }}</th>
                            <th>{{ __('Last SNMP') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($olts as $olt)
                            <tr wire:key="olt-{{ $olt->id }}">
                                <td class="fw-semibold">{{ $olt->name }}</td>
                                <td>
                                    <div>{{ $olt->management_ip ?: '—' }}</div>
                                    <small class="text-muted">{{ $olt->snmp_host ?: $olt->management_ip }}:{{ $olt->snmp_port }}</small>
                                </td>
                                <td>{{ $olt->vendor ?: '—' }}<br><small class="text-muted">{{ $olt->olt_driver }}</small></td>
                                <td><span class="badge bg-{{ $olt->status === 'active' ? 'success' : ($olt->status === 'maintenance' ? 'warning' : 'secondary') }}">{{ $olt->status }}</span></td>
                                <td>
                                    @php $h = is_array($olt->olt_health) ? $olt->olt_health : []; @endphp
                                    {{ $h['health_score'] ?? '—' }}
                                    @if(!empty($h['cpu_percent']))
                                        <small class="text-muted d-block">CPU {{ $h['cpu_percent'] }}%</small>
                                    @endif
                                </td>
                                <td>{{ optional($olt->last_snmp_poll_at)->diffForHumans() ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-success" wire:click="testSnmp({{ $olt->id }})" wire:loading.attr="disabled">
                                        {{ __('Check SNMP') }}
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" wire:click="pollHealth({{ $olt->id }})" wire:loading.attr="disabled">
                                        {{ __('Health') }}
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" wire:click="edit({{ $olt->id }})">{{ __('Edit') }}</button>
                                    <button class="btn btn-sm btn-outline-danger" wire:click="delete({{ $olt->id }})" wire:confirm="{{ __('Delete this OLT?') }}">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ __('No OLTs yet. Add one to run SNMP checks.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>{{ $editingId ? __('Edit OLT') : __('Add OLT') }}</strong>
                <button type="button" class="btn btn-sm btn-light" wire:click="cancel">{{ __('Back') }}</button>
            </div>
            <div class="card-body">
                <form wire:submit="save" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Name') }} *</label>
                        <input type="text" class="form-control" wire:model="name">
                        @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Vendor') }}</label>
                        <select class="form-select" wire:model="vendor">
                            <option value="">—</option>
                            @foreach($vendors as $key => $meta)
                                <option value="{{ $key }}">{{ is_array($meta) ? ($meta['label'] ?? $key) : $key }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Driver') }}</label>
                        <select class="form-select" wire:model="olt_driver">
                            <option value="">—</option>
                            @foreach($drivers as $key => $meta)
                                <option value="{{ $key }}">{{ is_array($meta) ? ($meta['label'] ?? $key) : $key }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Model') }}</label>
                        <input type="text" class="form-control" wire:model="model">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Location') }}</label>
                        <input type="text" class="form-control" wire:model="location">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Status') }}</label>
                        <select class="form-select" wire:model="status">
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                            <option value="maintenance">maintenance</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Management IP') }}</label>
                        <input type="text" class="form-control" wire:model="management_ip" placeholder="192.168.1.1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('SNMP Host') }}</label>
                        <input type="text" class="form-control" wire:model="snmp_host" placeholder="{{ __('optional if same as IP') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('SNMP Port') }}</label>
                        <input type="number" class="form-control" wire:model="snmp_port">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Version') }}</label>
                        <select class="form-select" wire:model="snmp_version">
                            <option value="v2c">v2c</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('SNMP Community') }}</label>
                        <input type="text" class="form-control" wire:model="snmp_community" placeholder="public">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Notes') }}</label>
                        <textarea class="form-control" rows="2" wire:model="notes"></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                        <button type="button" class="btn btn-light" wire:click="cancel">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
