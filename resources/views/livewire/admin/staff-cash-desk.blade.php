<div>
    <x-slot name="header">{{ __('Staff collection & cash') }}</x-slot>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <p class="small text-muted mb-3">{{ __('Who collected how much, how much they deposited to the office, and how much they still owe.') }}</p>
            <div class="row g-3">
                <div class="col-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">{{ __('Collected') }}</div>
                        <div class="fs-4 fw-bold">৳{{ number_format($totals['collected']) }}</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">{{ __('Deposited') }}</div>
                        <div class="fs-4 fw-bold">৳{{ number_format($totals['deposited']) }}</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="small text-muted">{{ __('Staff owe office') }}</div>
                        <div class="fs-4 fw-bold {{ $totals['due'] > 0 ? 'text-danger' : 'text-success' }}">৳{{ number_format($totals['due']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <div>
                    <label class="form-label small mb-0">{{ __('From') }}</label>
                    <input type="date" class="form-control form-control-sm" wire:model.live="from">
                </div>
                <div>
                    <label class="form-label small mb-0">{{ __('To') }}</label>
                    <input type="date" class="form-control form-control-sm" wire:model.live="to">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Staff') }}</th>
                            <th>{{ __('Receipts') }}</th>
                            <th>{{ __('Collected') }}</th>
                            <th>{{ __('Deposited') }}</th>
                            <th>{{ __('Owes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['user']->name }}<div class="small text-muted">{{ $row['user']->email }}</div></td>
                                <td>{{ $row['receipts'] }}</td>
                                <td>৳{{ number_format($row['collected']) }}</td>
                                <td>৳{{ number_format($row['deposited']) }}</td>
                                <td class="{{ $row['due'] > 0 ? 'text-danger fw-semibold' : 'text-success' }}">৳{{ number_format($row['due']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No staff rows in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 py-3"><strong>{{ __('Record office deposit') }}</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">{{ __('Staff') }}</label>
                    <select class="form-select" wire:model="staffId">
                        <option value="">{{ __('Select') }}</option>
                        @foreach ($staff as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    @error('staffId') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('Amount') }}</label>
                    <input type="number" min="1" class="form-control" wire:model="amount">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">{{ __('Date') }}</label>
                    <input type="date" class="form-control" wire:model="entry_date">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">{{ __('Type') }}</label>
                    <select class="form-select" wire:model="type">
                        <option value="deposit">{{ __('Deposit') }}</option>
                        <option value="adjustment">{{ __('Adjustment') }}</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label small">{{ __('Note') }}</label>
                    <input type="text" class="form-control" wire:model="note">
                </div>
            </div>
            <button type="button" class="btn btn-success mt-3" wire:click="record">{{ __('Save') }}</button>
        </div>
    </div>
</div>
