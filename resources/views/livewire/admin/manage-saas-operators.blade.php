<div>
    <x-slot name="header">
        {{ __('Sell ISP Admin') }}
    </x-slot>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h5 class="fw-bold mb-1">{{ __('SaaS operator desk') }}</h5>
                    <p class="text-muted mb-0 small">
                        {{ __('Open a full ISP admin for a buyer. They get customers, billing, MikroTik, portal, and reports — they cannot sell another admin.') }}
                    </p>
                </div>
                <button type="button" class="btn btn-sm btn-success" wire:click="openForm">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('Sell / Open admin') }}
                </button>
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-white border-0 py-3">
                <strong>{{ __('New operator') }}</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Company') }}</label>
                        <input type="text" class="form-control" wire:model="company">
                        @error('company') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Admin name') }}</label>
                        <input type="text" class="form-control" wire:model="contact_name">
                        @error('contact_name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Login email') }}</label>
                        <input type="email" class="form-control" wire:model="email">
                        @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Phone') }}</label>
                        <input type="text" class="form-control" wire:model="phone">
                        @error('phone') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Plan') }}</label>
                        <select class="form-select" wire:model="plan">
                            <option value="standard">{{ __('Standard') }}</option>
                            <option value="pro">{{ __('Pro') }}</option>
                            <option value="enterprise">{{ __('Enterprise') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Password') }}</label>
                        <input type="password" class="form-control" wire:model="password" autocomplete="new-password">
                        @error('password') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Confirm password') }}</label>
                        <input type="password" class="form-control" wire:model="password_confirmation" autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">{{ __('Notes') }}</label>
                        <textarea class="form-control" rows="2" wire:model="notes"></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-success" wire:click="sell" wire:loading.attr="disabled">{{ __('Create admin') }}</button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="cancel">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Company') }}</th>
                            <th>{{ __('Admin') }}</th>
                            <th>{{ __('Plan') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Resell') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($operators as $op)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $op->company }}</div>
                                    <div class="small text-muted">{{ optional($op->sold_at)->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <div>{{ $op->contact_name }}</div>
                                    <div class="small text-muted">{{ $op->email }}</div>
                                </td>
                                <td class="text-capitalize">{{ $op->plan }}</td>
                                <td>
                                    <span class="badge {{ $op->status === 'active' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                        {{ $op->status }}
                                    </span>
                                </td>
                                <td><span class="text-muted small">{{ __('Blocked') }}</span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleStatus({{ $op->id }})">
                                        {{ $op->status === 'active' ? __('Suspend') : __('Activate') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('No operators sold yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($operators->hasPages())
            <div class="card-footer bg-white">{{ $operators->links() }}</div>
        @endif
    </div>
</div>
