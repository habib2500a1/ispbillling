<div class="zoom-in pb-4">
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h4 fw-bold text-dark mb-0">
                <i class="bi bi-megaphone me-2 text-warning"></i>{{ __('Outage broadcast') }}
            </h2>
            <a href="{{ route('noc-overview') }}" class="btn btn-sm btn-outline-secondary">{{ __('NOC overview') }}</a>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 fw-semibold">
                    {{ $editingId ? __('Edit notice') : __('New notice') }}
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Title') }}</label>
                        <input type="text" class="form-control form-control-sm" wire:model="title">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Message') }}</label>
                        <textarea class="form-control form-control-sm" rows="4" wire:model="message"></textarea>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">{{ __('Severity') }}</label>
                            <select class="form-select form-select-sm" wire:model="severity">
                                <option value="info">{{ __('Info') }}</option>
                                <option value="warning">{{ __('Warning') }}</option>
                                <option value="critical">{{ __('Critical') }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">{{ __('Scope') }}</label>
                            <select class="form-select form-select-sm" wire:model="scope">
                                <option value="network">{{ __('Network') }}</option>
                                <option value="olt">{{ __('OLT') }}</option>
                                <option value="router">{{ __('Router') }}</option>
                                <option value="area">{{ __('Area') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Area label (optional)') }}</label>
                        <input type="text" class="form-control form-control-sm" wire:model="area_label" placeholder="{{ __('e.g. Mirpur Zone 10') }}">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" wire:model="is_active" id="outageActive">
                        <label class="form-check-label small" for="outageActive">{{ __('Active (visible on portal)') }}</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="save">{{ __('Save') }}</button>
                        @if($editingId)
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="resetForm">{{ __('Cancel') }}</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 fw-semibold">{{ __('Live on portal') }}</div>
                <div class="card-body pt-0">
                    @forelse($activeNotices as $n)
                        <div class="alert alert-{{ $n->severity === 'critical' ? 'danger' : ($n->severity === 'warning' ? 'warning' : 'info') }} py-2 mb-2">
                            <div class="fw-semibold">{{ $n->title }}</div>
                            <div class="small">{{ $n->message }}</div>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('No active outage notices.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 fw-semibold">{{ __('All notices') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Severity') }}</th>
                                <th>{{ __('Active') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notices as $n)
                                <tr>
                                    <td>{{ $n->title }}</td>
                                    <td><span class="badge bg-secondary">{{ $n->severity }}</span></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-{{ $n->is_active ? 'success' : 'secondary' }}"
                                            wire:click="toggleActive({{ $n->id }})">
                                            {{ $n->is_active ? __('Yes') : __('No') }}
                                        </button>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $n->id }})"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $n->id }})" wire:confirm="{{ __('Delete?') }}"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-3">{{ __('No notices yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
