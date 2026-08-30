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
                        {{ __('Each row is a separate ISP that rented this billing software — not Anetbd staff. They manage their own customers, packages, OLT, and collection. They cannot open Sell ISP Admin.') }}
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="openPlans">
                        <i class="bi bi-sliders me-1"></i>{{ __('Modify packages') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-success" wire:click="openForm">
                        <i class="bi bi-plus-lg me-1"></i>{{ __('Open new ISP') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($plans->isNotEmpty() && ! $detail && ! $showPlans)
        <div class="row g-3 mb-3">
            @foreach ($plans as $p)
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="fw-bold">{{ $p->name }}</div>
                                @if($p->is_lifetime)
                                    <span class="badge bg-success-subtle text-success">{{ __('Lifetime free') }}</span>
                                @endif
                            </div>
                            @if($p->is_lifetime)
                                <div class="small text-success mb-2">{{ __('No rent · never locks for unpaid') }}</div>
                            @else
                                <div class="small text-muted mb-2">৳{{ number_format($p->monthly_price) }}/mo · ৳{{ number_format($p->yearly_price) }}/yr</div>
                                <div class="small">+ ৳{{ number_format($p->per_user_rate) }} {{ __('per user') }}</div>
                            @endif
                            <div class="small text-muted">
                                {{ $p->max_customers ?: __('Unlimited') }} {{ __('users') }} ·
                                {{ $p->max_olts ?: __('Unlimited') }} OLT ·
                                {{ $p->max_staff ?: __('Unlimited') }} {{ __('staff') }}
                            </div>
                            <button type="button" class="btn btn-link btn-sm px-0 mt-1" wire:click="editPlan({{ $p->id }})">{{ __('Edit') }}</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($showPlans)
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <strong>{{ $editingPlanId ? __('Edit package') : __('New / edit rent package') }}</strong>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancel">{{ __('Close') }}</button>
            </div>
            <div class="card-body">
                <p class="small text-muted">{{ __('Change prices, limits, or mark a package lifetime free. Saving a package does not rewrite an ISP until you apply it on their Manage page.') }}</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Package name') }}</label>
                        <input type="text" class="form-control" wire:model="plan_name">
                        @error('plan_name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('Monthly ৳') }}</label>
                        <input type="number" min="0" class="form-control" wire:model="plan_monthly" @disabled($plan_lifetime)>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('Yearly ৳') }}</label>
                        <input type="number" min="0" class="form-control" wire:model="plan_yearly" @disabled($plan_lifetime)>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('Per user ৳') }}</label>
                        <input type="number" min="0" class="form-control" wire:model="plan_per_user" @disabled($plan_lifetime)>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('Max users') }}</label>
                        <input type="number" min="0" class="form-control" wire:model="plan_max_customers">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('Max OLT') }}</label>
                        <input type="number" min="0" class="form-control" wire:model="plan_max_olts">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('Max routers') }}</label>
                        <input type="number" min="0" class="form-control" wire:model="plan_max_routers">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">{{ __('Max staff') }}</label>
                        <input type="number" min="0" class="form-control" wire:model="plan_max_staff">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model.live="plan_lifetime" id="planLifetime">
                            <label class="form-check-label" for="planLifetime">{{ __('Lifetime free') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="plan_active" id="planActive">
                            <label class="form-check-label" for="planActive">{{ __('Active') }}</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-success" wire:click="savePlan">{{ $editingPlanId ? __('Save package') : __('Create package') }}</button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="openPlans">{{ __('Reset form') }}</button>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Package') }}</th>
                                <th>{{ __('Rent') }}</th>
                                <th>{{ __('Limits') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allPlans as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row->name }}</div>
                                        <div class="small text-muted">{{ $row->slug }} @if(! $row->is_active)· {{ __('hidden') }}@endif</div>
                                    </td>
                                    <td>
                                        @if($row->is_lifetime)
                                            <span class="badge bg-success-subtle text-success">{{ __('Lifetime free') }}</span>
                                        @else
                                            ৳{{ number_format($row->monthly_price) }}/mo · ৳{{ number_format($row->yearly_price) }}/yr
                                            <div class="small text-muted">+ ৳{{ number_format($row->per_user_rate) }} / user</div>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        {{ $row->max_customers ?: '∞' }} users · {{ $row->max_olts ?: '∞' }} OLT · {{ $row->max_staff ?: '∞' }} staff
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="editPlan({{ $row->id }})">{{ __('Edit') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($showForm)
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-white border-0 py-3">
                <strong>{{ $editingOperatorId ? __('Edit ISP admin') : __('New ISP (separate tenant)') }}</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    @if($editingOperatorId)
                        {{ __('Change company, login email, phone, or password. Plan upgrade / downgrade is in the panel below.') }}
                    @else
                        {{ __('This login is a new ISP admin for their own data. They are not Anetbd staff and cannot sell another ISP admin.') }}
                    @endif
                </p>
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
                    @unless($editingOperatorId)
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">{{ __('Their domain') }}</label>
                        <input type="text" class="form-control" wire:model="domain" placeholder="billing.theirisp.com">
                        <div class="form-text">{{ __('Optional. They point this domain to :ip', ['ip' => $serverIp]) }}</div>
                        @error('domain') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Plan') }}</label>
                        <select class="form-select" wire:model.live="plan">
                            @foreach ($plans as $p)
                                <option value="{{ $p->slug }}">{{ $p->name }}@if($p->is_lifetime) ({{ __('Lifetime free') }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ __('Billing') }}</label>
                        <select class="form-select" wire:model="billing_cycle">
                            <option value="monthly">{{ __('Monthly rent') }}</option>
                            <option value="yearly">{{ __('Yearly rent') }}</option>
                            <option value="lifetime">{{ __('Lifetime free') }}</option>
                        </select>
                    </div>
                    @endunless
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">{{ $editingOperatorId ? __('New password (optional)') : __('Password') }}</label>
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
                    @if($editingOperatorId)
                        <button type="button" class="btn btn-success" wire:click="updateOperator" wire:loading.attr="disabled">{{ __('Save ISP admin') }}</button>
                    @else
                        <button type="button" class="btn btn-success" wire:click="sell" wire:loading.attr="disabled">{{ __('Create ISP admin') }}</button>
                    @endif
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
                        <div class="small text-muted">{{ __('Separate ISP tenant — they manage their own customers. Not Anetbd staff.') }}</div>
                        @if($detail->domain)
                            <div class="small mt-1">
                                <a href="https://{{ $detail->domain }}" target="_blank" rel="noopener">https://{{ $detail->domain }}</a>
                            </div>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-success" wire:click="openEdit({{ $detail->id }})">{{ __('Edit admin') }}</button>
                        @unless($detail->isLifetime())
                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="grantLifetime({{ $detail->id }})">{{ __('Give lifetime free') }}</button>
                        @endunless
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancel">{{ __('Back') }}</button>
                    </div>
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
                    <div class="col-md-6">
                        <label class="form-label small mb-0">{{ __('ISP domain') }}</label>
                        <input type="text" class="form-control form-control-sm" wire:model="domain" placeholder="billing.theirisp.com">
                        @error('domain') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-sm btn-success" wire:click="saveDomain({{ $detail->id }})">{{ __('Save domain') }}</button>
                        <span class="small text-muted ms-1">{{ __('A record → :ip  ·  HTTPS starts after DNS.', ['ip' => $serverIp]) }}</span>
                    </div>
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
                @php
                    $selectedPlan = $allPlans->firstWhere('slug', $apply_plan);
                    $currentMonthly = (int) ($detail->planCatalog?->monthly_price ?? $detail->base_amount);
                    $nextMonthly = $apply_cycle === 'lifetime' || $selectedPlan?->is_lifetime
                        ? 0
                        : (int) ($selectedPlan?->monthly_price ?? 0);
                    $planAction = $nextMonthly === $currentMonthly && $apply_plan === $detail->plan && $apply_cycle === $detail->billing_cycle
                        ? __('Keep plan')
                        : ($nextMonthly > $currentMonthly ? __('Upgrade') : ($nextMonthly < $currentMonthly ? __('Downgrade') : __('Change plan')));
                @endphp
                <div class="border rounded-3 p-3 mb-3" style="background: #f8fafc;">
                    <div class="fw-semibold mb-1">{{ __('Upgrade / downgrade') }}</div>
                    <p class="small text-muted mb-2">
                        {{ __('Current') }}: <strong>{{ $detail->planCatalog?->name ?? $detail->plan }}</strong>
                        · {{ $detail->billing_cycle }}
                        @if(! $detail->isLifetime())
                            · ৳{{ number_format($detail->amount) }}
                        @endif
                    </p>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-0">{{ __('New package') }}</label>
                            <select class="form-select form-select-sm" wire:model.live="apply_plan">
                                @foreach ($allPlans as $p)
                                    <option value="{{ $p->slug }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-0">{{ __('Billing') }}</label>
                            <select class="form-select form-select-sm" wire:model.live="apply_cycle">
                                <option value="monthly">{{ __('Monthly') }}</option>
                                <option value="yearly">{{ __('Yearly') }}</option>
                                <option value="lifetime">{{ __('Lifetime free') }}</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <button type="button" class="btn btn-sm btn-success" wire:click="applySelectedPlan({{ $detail->id }})">{{ $planAction }}</button>
                            <span class="small text-muted ms-1">{{ __('Limits and rent update immediately.') }}</span>
                        </div>
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
                                <tr><td colspan="6" class="text-muted">{{ $detail->isLifetime() ? __('Lifetime free — no invoices.') : __('No invoices yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <h6 class="fw-semibold">{{ __('This ISP’s collectors this month') }}</h6>
                <p class="small text-muted">{{ __('Their staff, not Anetbd staff.') }}</p>
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
                                <tr><td colspan="4" class="text-muted">{{ __('No collections in this period.') }}</td></tr>
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
                            <th>{{ __('ISP / Company') }}</th>
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
                                    @if($op->domain)
                                        <div class="small"><a href="https://{{ $op->domain }}" target="_blank" rel="noopener">{{ $op->domain }}</a></div>
                                    @endif
                                </td>
                                <td class="text-capitalize">
                                    {{ $op->plan }} / {{ $op->billing_cycle }}
                                    @if($op->isLifetime())
                                        <div class="small text-success">{{ __('Lifetime free') }}</div>
                                    @else
                                        <div class="small text-muted">৳{{ number_format($op->amount) }}</div>
                                    @endif
                                </td>
                                <td>{{ $op->isLifetime() ? '—' : (optional($op->next_due_at)->format('d M Y') ?: '—') }}</td>
                                <td>
                                    <span class="badge {{ $op->status === 'active' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">
                                        {{ $op->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-success" wire:click="openEdit({{ $op->id }})">{{ __('Edit') }}</button>
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
                                <td colspan="6" class="text-center text-muted py-4">{{ __('No ISPs opened yet.') }}</td>
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
