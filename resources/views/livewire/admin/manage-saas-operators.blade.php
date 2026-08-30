<div>
    <x-slot name="header">
        {{ __('Sell ISP Admin') }}
    </x-slot>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h5 class="fw-bold mb-1">{{ __('SaaS control desk') }}</h5>
                    <p class="text-muted mb-0 small">
                        {{ __('Sell monthly or yearly by user base. Set OLT / customer / staff limits. Unpaid bills lock the ISP admin until you record payment.') }}
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.staff-cash') }}" class="btn btn-sm btn-outline-success">{{ __('Staff cash') }}</a>
                    <button type="button" class="btn btn-sm btn-success" wire:click="openForm">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Sell / Open admin') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($plans->isNotEmpty() && ! $detail)
        <div class="row g-3 mb-3">
            @foreach ($plans as $p)
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body">
                            <div class="fw-bold">{{ $p->name }}</div>
                            <div class="small text-muted mb-2">৳{{ number_format($p->monthly_price) }}/mo · ৳{{ number_format($p->yearly_price) }}/yr</div>
                            <div class="small">+ ৳{{ number_format($p->per_user_rate) }} {{ __('per user') }}</div>
                            <div class="small text-muted">
                                {{ $p->max_customers ?: __('Unlimited') }} {{ __('users') }} ·
                                {{ $p->max_olts ?: __('Unlimited') }} OLT ·
                                {{ $p->max_staff ?: __('Unlimited') }} {{ __('staff') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

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
                            @foreach ($plans as $p)
                                <option value="{{ $p->slug }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Billing') }}</label>
                        <select class="form-select" wire:model="billing_cycle">
                            <option value="monthly">{{ __('Monthly') }}</option>
                            <option value="yearly">{{ __('Yearly') }}</option>
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
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-success" wire:click="sell" wire:loading.attr="disabled">{{ __('Create admin') }}</button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="cancel">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($detail)
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">{{ $detail->company }}</h5>
                        <div class="small text-muted">{{ $detail->contact_name }} · {{ $detail->email }} · {{ $detail->plan }} / {{ $detail->billing_cycle }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancel">{{ __('Back') }}</button>
                </div>
                <div class="row g-3 mb-3">
                    @foreach ($usage as $key => $u)
                        <div class="col-6 col-md-4 col-xl">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="small text-muted text-uppercase">{{ $key }}</div>
                                <div class="fw-bold">{{ $u['used'] }} / {{ $u['unlimited'] ? '∞' : $u['max'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">{{ __('Max users') }}</label>
                        <input type="number" min="0" class="form-control form-control-sm" wire:model="max_customers">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">{{ __('Max OLT') }}</label>
                        <input type="number" min="0" class="form-control form-control-sm" wire:model="max_olts">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">{{ __('Max routers') }}</label>
                        <input type="number" min="0" class="form-control form-control-sm" wire:model="max_routers">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-0">{{ __('Max staff') }}</label>
                        <input type="number" min="0" class="form-control form-control-sm" wire:model="max_staff">
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="button" class="btn btn-sm btn-success" wire:click="saveQuotas({{ $detail->id }})">{{ __('Save limits') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="generateInvoice({{ $detail->id }})">{{ __('New invoice') }}</button>
                    </div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Invoice') }}</th>
                                <th>{{ __('Users') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Due') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($detail->invoices as $inv)
                                <tr>
                                    <td>{{ $inv->period_label ?: '#'.$inv->id }}</td>
                                    <td>{{ $inv->user_base }}</td>
                                    <td>৳{{ number_format($inv->amount) }}</td>
                                    <td>{{ optional($inv->due_at)->format('d M Y') }}</td>
                                    <td><span class="badge {{ $inv->status === 'paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $inv->status }}</span></td>
                                    <td class="text-end">
                                        @if($inv->status !== 'paid')
                                            <input type="text" class="form-control form-control-sm d-inline-block mb-1" style="max-width:160px" wire:model="payNote" placeholder="{{ __('Note') }}">
                                            <button type="button" class="btn btn-sm btn-success" wire:click="recordPayment({{ $inv->id }})">{{ __('Mark paid / unlock') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">{{ __('No invoices yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <h6 class="fw-semibold">{{ __('Staff collection this month') }}</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Staff') }}</th>
                                <th>{{ __('Collected') }}</th>
                                <th>{{ __('Deposited') }}</th>
                                <th>{{ __('Owes office') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($staffRows as $row)
                                <tr>
                                    <td>{{ $row['user']->name }}<div class="small text-muted">{{ $row['user']->email }}</div></td>
                                    <td>৳{{ number_format($row['collected']) }}</td>
                                    <td>৳{{ number_format($row['deposited']) }}</td>
                                    <td class="{{ $row['due'] > 0 ? 'text-danger fw-semibold' : 'text-success' }}">৳{{ number_format($row['due']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">{{ __('No staff collections in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
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
                            <th>{{ __('Due') }}</th>
                            <th>{{ __('Status') }}</th>
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
                                <td class="text-capitalize">{{ $op->plan }} / {{ $op->billing_cycle }}<div class="small text-muted">৳{{ number_format($op->amount) }}</div></td>
                                <td>{{ optional($op->next_due_at)->format('d M Y') ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $op->status === 'active' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                        {{ $op->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="view({{ $op->id }})">{{ __('Manage') }}</button>
                                        @if($op->status === 'active')
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="lockNow({{ $op->id }})">{{ __('Lock') }}</button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleStatus({{ $op->id }})">
                                            {{ $op->status === 'active' ? __('Suspend') : __('Unlock') }}
                                        </button>
                                    </div>
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
