<div>
    <x-slot name="header">
        {{ __('Automatic Processes') }}
    </x-slot>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #1e293b, #334155); color: #fff;">
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

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
                <h6 class="fw-bold mb-1">{{ __('Scheduled billing, SMS & network jobs') }}</h6>
                <p class="small text-muted mb-0">{{ __('anetbd-style automatic processes — enable, run now, or review history.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="syncDefaults" wire:loading.attr="disabled">
                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('Sync defaults') }}
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
                        <th>{{ __('Last run') }}</th>
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
                                    <div class="small text-muted mt-1">{{ $process->executeAtLabel() }}</div>
                                @endif
                            </td>
                            <td><code class="small">{{ $process->artisan_command }}</code></td>
                            <td>
                                @if($process->last_run_at)
                                    <div class="small">{{ $process->last_run_at->format('d M Y H:i') }}</div>
                                    @if($process->next_run_at)
                                        <div class="small text-muted">{{ __('Next') }}: {{ $process->next_run_at->format('d M H:i') }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
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
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-primary me-1" wire:click="runNow({{ $process->id }})" wire:loading.attr="disabled">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="viewRuns({{ $process->id }})">
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
