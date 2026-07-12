<div>
    <x-slot name="header">
        {{ __('HR Hub') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Workforce lite') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('admin-users') }}" class="btn btn-sm btn-outline-secondary">{{ __('Users') }}</a>
            <a href="{{ route('admin.expenses') }}" class="btn btn-sm btn-primary">{{ __('Salary expenses') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Staff') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['staff'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Present') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['present_today'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('On leave') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['on_leave_today'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Pending leave') }}</div>
                    <div class="fs-3 fw-bold text-warning">{{ $stats['pending_leaves'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Roles') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['roles'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Salary (month)') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['salary_month'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">{{ __('Clock for') }}</label>
                    <select class="form-select form-select-sm" wire:model="clockUserId">
                        @foreach($staff_options as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-success" wire:click="clockIn">
                        <i class="bi bi-box-arrow-in-right"></i> {{ __('Clock in') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-dark" wire:click="clockOut">
                        <i class="bi bi-box-arrow-right"></i> {{ __('Clock out') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach([
            'roster' => __('Roster'),
            'attendance' => __('Attendance today'),
            'leaves' => __('Leaves'),
            'salary' => __('Salary'),
        ] as $key => $label)
            <button type="button" class="btn btn-sm {{ $tab === $key ? 'btn-dark' : 'btn-outline-dark' }}"
                wire:click="setTab('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    @if($tab === 'roster')
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Staff') }}</th>
                                        <th>{{ __('Roles') }}</th>
                                        <th>{{ __('Mobile') }}</th>
                                        <th>{{ __('Today') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($staff as $s)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $s['name'] }}</div>
                                                <div class="small text-muted">{{ $s['email'] }}</div>
                                            </td>
                                            <td>
                                                @forelse($s['roles'] as $role)
                                                    <span class="badge bg-light text-dark border">{{ $role }}</span>
                                                @empty
                                                    <span class="text-muted small">—</span>
                                                @endforelse
                                            </td>
                                            <td>{{ $s['mobile'] ?: '—' }}</td>
                                            <td>
                                                @if($s['on_leave_today'])
                                                    <span class="badge bg-info">{{ __('Leave') }}</span>
                                                @elseif($s['present_today'])
                                                    <span class="badge bg-success">{{ __('Present') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ __('—') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted small">{{ __('No staff users.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">{{ __('By role') }}</h6>
                    </div>
                    <div class="card-body pt-0">
                        @forelse($role_counts as $role => $count)
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span>{{ $role }}</span>
                                <strong>{{ $count }}</strong>
                            </div>
                        @empty
                            <div class="text-muted small">{{ __('No roles defined.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @elseif($tab === 'attendance')
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Staff') }}</th>
                                <th>{{ __('In') }}</th>
                                <th>{{ __('Out') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attendance_today as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['name'] }}</td>
                                    <td>{{ $row['clock_in'] ?: '—' }}</td>
                                    <td>{{ $row['clock_out'] ?: '—' }}</td>
                                    <td><span class="badge bg-{{ $row['status'] === 'late' ? 'warning' : 'success' }}">{{ $row['status_label'] }}</span></td>
                                    <td class="small text-muted">{{ $row['notes'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted small">{{ __('No attendance logged today.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @elseif($tab === 'leaves')
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">{{ __('Request leave') }}</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="mb-2">
                            <label class="form-label small">{{ __('Staff') }}</label>
                            <select class="form-select form-select-sm" wire:model="leaveUserId">
                                @foreach($staff_options as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small">{{ __('From') }}</label>
                                <input type="date" class="form-control form-control-sm" wire:model="leaveFrom">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">{{ __('To') }}</label>
                                <input type="date" class="form-control form-control-sm" wire:model="leaveTo">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('Type') }}</label>
                            <select class="form-select form-select-sm" wire:model="leaveType">
                                @foreach($leave_types as $key => $label)
                                    <option value="{{ $key }}">{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">{{ __('Reason') }}</label>
                            <textarea class="form-control form-control-sm" rows="2" wire:model="leaveReason"></textarea>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="submitLeave">{{ __('Submit') }}</button>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">{{ __('Pending approval') }}</h6>
                    </div>
                    <div class="card-body pt-0">
                        @forelse($pending_leaves as $leave)
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                                <div>
                                    <div class="fw-semibold">{{ $leave['name'] }} · {{ $leave['type_label'] }}</div>
                                    <div class="small text-muted">{{ $leave['from'] }} → {{ $leave['to'] }} ({{ $leave['days'] }}d) · {{ $leave['reason'] ?: '—' }}</div>
                                </div>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-success" wire:click="approveLeave({{ $leave['id'] }})">{{ __('Approve') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="rejectLeave({{ $leave['id'] }})">{{ __('Reject') }}</button>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted small">{{ __('No pending leave requests.') }}</div>
                        @endforelse
                    </div>
                </div>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">{{ __('Recent leaves') }}</h6>
                    </div>
                    <div class="card-body pt-0">
                        @foreach($recent_leaves as $leave)
                            <div class="d-flex justify-content-between py-2 border-bottom small">
                                <div>
                                    <span class="fw-semibold">{{ $leave['name'] }}</span>
                                    {{ $leave['from'] }} → {{ $leave['to'] }}
                                </div>
                                <span class="badge bg-{{ $leave['status'] === 'approved' ? 'success' : ($leave['status'] === 'pending' ? 'warning' : 'secondary') }}">
                                    {{ $leave['status_label'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between">
                <h6 class="fw-bold mb-0">{{ __('Recent salary expenses') }}</h6>
                <a href="{{ route('admin.expenses') }}" class="small">{{ __('Manage') }}</a>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Staff') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recent_salary as $row)
                                <tr>
                                    <td>{{ $row['date'] }}</td>
                                    <td>{{ $row['title'] }}</td>
                                    <td>{{ $row['user'] ?: '—' }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted small">{{ __('No employee_salary expenses yet. Add via Expense Management.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
