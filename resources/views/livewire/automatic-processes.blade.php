<div>
    <x-slot name="header">
        {{ __('Automatic Processes') }}
    </x-slot>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #1e3a5f, #2d4a6f); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Total') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #059669, #047857); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Enabled') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['enabled'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #64748b, #475569); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Disabled') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['disabled'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Last failed') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['failed'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
        <div class="px-3 py-2 d-flex align-items-center justify-content-between" style="background:#1e3a5f;color:#fff;">
            <span class="fw-bold text-uppercase small">{{ __('Quick setting') }}</span>
            <i class="bi bi-sliders"></i>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold mb-1">{{ __('Bill generate period as?') }}</div>
                        <div class="small text-muted mb-3">{{ __('Start of the month bills every client on the 1st after midnight. Date to date uses each client’s own day.') }}</div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" id="apBillStart" value="global" wire:model.live="bill_generate_mode">
                            <label class="form-check-label fw-semibold" for="apBillStart">{{ __('Start of the month') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="apBillDate" value="customer" wire:model.live="bill_generate_mode">
                            <label class="form-check-label fw-semibold" for="apBillDate">{{ __('Date to date') }}</label>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold mb-1">{{ __('Allow inactive process at the last day of the month?') }}</div>
                        <div class="small text-muted mb-3">{{ __('If No, unpaid clients stay on through the last calendar day.') }}</div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" id="apEomYes" @checked($eom_inactive_process) wire:click="$set('eom_inactive_process', true)">
                            <label class="form-check-label fw-semibold" for="apEomYes">{{ __('Yes, I want.') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="apEomNo" @checked(! $eom_inactive_process) wire:click="$set('eom_inactive_process', false)">
                            <label class="form-check-label fw-semibold" for="apEomNo">{{ __('No, I don’t.') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-sm btn-primary" wire:click="saveRules" wire:loading.attr="disabled">
                    <i class="bi bi-check2 me-1"></i>{{ __('Save') }}
                </button>
                <a href="{{ route('site-settings', ['tab' => 'billing']) }}" class="btn btn-sm btn-outline-secondary">{{ __('More clocks on Billing & Invoice') }}</a>
            </div>
        </div>
    </div>

    <div class="d-none">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <h6 class="fw-bold mb-1">{{ __('Billing automation') }}</h6>
                    <p class="small text-muted mb-0">{{ __('Change these anytime. Save — next run uses the new time and rules. Bills still generate only for customers whose billing day is today.') }}</p>
                </div>
                <button type="button" class="btn btn-sm btn-primary" wire:click="saveRules" wire:loading.attr="disabled">
                    <i class="bi bi-check2 me-1"></i>{{ __('Save billing automation') }}
                </button>
            </div>

            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold mb-2">{{ __('1. Generate monthly bills') }}</div>
                        <label class="form-label small mb-1">{{ __('Every day at') }}</label>
                        <input type="time" class="form-control rounded-3 mb-2" wire:model="bill_generate_at">
                        @error('bill_generate_at') <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" wire:model="bill_generate_on" id="billGenOn">
                            <label class="form-check-label small" for="billGenOn">{{ __('On') }}</label>
                        </div>
                        <div class="small text-muted mt-2">{{ __('Creates a bill for each customer whose billing day = today’s date (1–28).') }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold mb-2">{{ __('2. Bill SMS') }}</div>
                        <label class="form-label small mb-1">{{ __('Clock') }}</label>
                        <input type="time" class="form-control rounded-3 mb-2" wire:model="sms_send_at">
                        @error('sms_send_at') <div class="text-danger small">{{ $message }}</div> @enderror
                        <label class="form-label small mb-1">{{ __('Day of month') }}</label>
                        <input type="number" min="1" max="28" class="form-control rounded-3 mb-2" wire:model="monthly_bill_sms_day">
                        @error('monthly_bill_sms_day') <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" wire:model="sms_send_on" id="smsOn">
                            <label class="form-check-label small" for="smsOn">{{ __('On') }}</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold mb-2">{{ __('3. Disable unpaid') }}</div>
                        <label class="form-label small mb-1">{{ __('Every day at') }}</label>
                        <input type="time" class="form-control rounded-3 mb-2" wire:model="disable_at">
                        @error('disable_at') <div class="text-danger small">{{ $message }}</div> @enderror
                        <label class="form-label small mb-1">{{ __('Grace days after expire') }}</label>
                        <input type="number" min="0" max="90" class="form-control rounded-3 mb-2" wire:model="disable_check_days">
                        @error('disable_check_days') <div class="text-danger small">{{ $message }}</div> @enderror
                        <label class="form-label small mb-1">{{ __('Grace amount') }}</label>
                        <input type="number" min="0" class="form-control rounded-3 mb-2" wire:model="disable_check_no">
                        @error('disable_check_no') <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" wire:model="disable_on" id="disableOn">
                            <label class="form-check-label small" for="disableOn">{{ __('On') }}</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold mb-2">{{ __('4. Payment reminder SMS') }}</div>
                        <label class="form-label small mb-1">{{ __('Every day at') }}</label>
                        <input type="time" class="form-control rounded-3 mb-2" wire:model="reminder_at">
                        @error('reminder_at') <div class="text-danger small">{{ $message }}</div> @enderror
                        <label class="form-label small mb-1">{{ __('Days before expire') }}</label>
                        <input type="number" min="0" max="30" class="form-control rounded-3 mb-2" wire:model="payment_reminder_days">
                        @error('payment_reminder_days') <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" wire:model="reminder_on" id="reminderOn">
                            <label class="form-check-label small" for="reminderOn">{{ __('On') }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">{{ __('Expired MikroTik profile') }}</label>
                    <input type="text" class="form-control rounded-3" wire:model="expired_profile_name">
                    @error('expired_profile_name') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">{{ __('Late fee per day') }}</label>
                    <input type="number" min="0" step="0.01" class="form-control rounded-3" wire:model="late_fee_per_day">
                    @error('late_fee_per_day') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">{{ __('Late fee grace days') }}</label>
                    <input type="number" min="0" max="90" class="form-control rounded-3" wire:model="late_fee_grace_days">
                    @error('late_fee_grace_days') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">{{ __('Router log retention (days)') }}</label>
                    <input type="number" min="1" max="3650" class="form-control rounded-3" wire:model="log_retention_days">
                    @error('log_retention_days') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
                <h6 class="fw-bold mb-1">{{ __('Jobs — edit time, interval, on/off') }}</h6>
                <p class="small text-muted mb-0">{{ __('Click Edit to change when a job runs. Sync defaults only adds missing jobs — it does not overwrite your times.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="syncDefaults" wire:loading.attr="disabled">
                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('Load missing jobs') }}
                </button>
                <a href="{{ route('sms-setup') }}" class="btn btn-sm btn-outline-success">{{ __('SMS Templates') }}</a>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Process') }}</th>
                        <th>{{ __('Schedule') }}</th>
                        <th>{{ __('Command') }}</th>
                        <th>{{ __('Last / next') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($processes as $process)
                        <tr wire:key="proc-{{ $process->id }}">
                            <td>
                                <div class="fw-semibold">{{ $process->name }}</div>
                                @if($process->description)
                                    <div class="small text-muted">{{ $process->description }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $process->intervalLabel() }}</span>
                                @if($process->executeAtLabel() !== '—')
                                    <div class="small fw-semibold mt-1">{{ __('At') }} {{ $process->executeAtLabel() }}</div>
                                @endif
                            </td>
                            <td><code class="small">{{ $process->artisan_command }}</code></td>
                            <td>
                                @if($process->last_run_at)
                                    <div class="small">{{ $process->last_run_at->format('d M Y H:i') }}</div>
                                @else
                                    <div class="small text-muted">{{ __('Never ran') }}</div>
                                @endif
                                @if($process->next_run_at)
                                    <div class="small text-muted">{{ __('Next') }}: {{ $process->next_run_at->format('d M H:i') }}</div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $status = $process->last_status ?: 'pending';
                                    $badge = match($status) {
                                        'success' => 'success',
                                        'failed' => 'danger',
                                        'running' => 'primary',
                                        'skipped' => 'secondary',
                                        default => 'light text-dark border',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ ucfirst($status) }}</span>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        @checked($process->enabled)
                                        wire:click="toggleEnabled({{ $process->id }})">
                                    <label class="form-check-label small">{{ __('On') }}</label>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" wire:click="openEdit({{ $process->id }})">
                                    <i class="bi bi-pencil me-1"></i>{{ __('Edit') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-primary me-1" wire:click="runNow({{ $process->id }})" wire:loading.attr="disabled" title="{{ __('Run now') }}">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="viewRuns({{ $process->id }})" title="{{ __('History') }}">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                {{ __('No automatic processes yet.') }}
                                <button type="button" class="btn btn-sm btn-primary ms-2" wire:click="syncDefaults">{{ __('Load defaults') }}</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showEdit)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);">
            <div class="modal-dialog">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">{{ __('Edit schedule') }}</h5>
                        <button type="button" class="btn-close" wire:click="closeEdit"></button>
                    </div>
                    <div class="modal-body pt-0">
                        @if($edit_slug === 'generate-monthly-bills')
                            <div class="alert alert-info border-0 small">
                                {{ __('This job creates bills every day at the time you set, only for customers whose billing day equals today’s date (1–28). Last calendar day also sweeps leftover accounts.') }}
                            </div>
                        @elseif($edit_slug === 'monthly-bill-sms')
                            <div class="alert alert-info border-0 small">
                                {{ __('Runs every day at this clock, but SMS is sent only on the Bill SMS day saved under Billing rules.') }}
                            </div>
                        @elseif($edit_slug === 'payment-reminder-alerts')
                            <div class="alert alert-info border-0 small">
                                {{ __('Sends reminder SMS when expire is N days away. N is “Payment reminder days” under Billing rules.') }}
                            </div>
                        @elseif($edit_slug === 'disable-unpaid-users')
                            <div class="alert alert-info border-0 small">
                                {{ __('Uses grace days, grace amount, and expired MikroTik profile from Billing rules.') }}
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Name') }}</label>
                            <input type="text" class="form-control rounded-3" wire:model="edit_name">
                            @error('edit_name') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Description') }}</label>
                            <textarea class="form-control rounded-3" rows="2" wire:model="edit_description"></textarea>
                            @error('edit_description') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('How often') }}</label>
                                <select class="form-select rounded-3" wire:model.live="edit_interval">
                                    @foreach($intervals as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('edit_interval') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('Clock time') }}</label>
                                <input type="time" class="form-control rounded-3" wire:model="edit_execute_at"
                                    @disabled($edit_interval !== 'daily')>
                                @if($edit_interval !== 'daily')
                                    <small class="text-muted">{{ __('Used only for Daily jobs.') }}</small>
                                @endif
                                @error('edit_execute_at') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" role="switch" wire:model="edit_enabled" id="editEnabled">
                            <label class="form-check-label" for="editEnabled">{{ __('Enabled') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeEdit">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-primary" wire:click="saveProcess" wire:loading.attr="disabled">
                            {{ __('Save schedule') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showRuns && $selected)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">{{ $selected->name }} — {{ __('Run history') }}</h5>
                        <button type="button" class="btn-close" wire:click="closeRuns"></button>
                    </div>
                    <div class="modal-body pt-0">
                        @if($selected->last_output)
                            <div class="alert alert-light border small mb-3">
                                <strong>{{ __('Last output') }}:</strong><br>
                                <pre class="mb-0 small" style="white-space: pre-wrap;">{{ $selected->last_output }}</pre>
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('When') }}</th>
                                        <th>{{ __('By') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Output') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($selected->runs as $run)
                                        <tr>
                                            <td class="small">{{ $run->started_at?->format('d M H:i:s') }}</td>
                                            <td class="small">{{ $run->triggered_by }}</td>
                                            <td><span class="badge bg-{{ $run->status === 'success' ? 'success' : ($run->status === 'failed' ? 'danger' : 'secondary') }}">{{ $run->status }}</span></td>
                                            <td class="small text-muted" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($run->output, 120) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted text-center">{{ __('No runs yet') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
