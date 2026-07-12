<div>
    <x-slot name="header">
        {{ __('SMS Notices') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Billing → SMS / WhatsApp') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updatedAt)->diffForHumans() }}
            @if($gateway['ok'])
                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">{{ __('Gateway OK') }}</span>
            @else
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1">{{ __('Gateway issue') }}</span>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('billing-notices') }}" class="btn btn-sm btn-outline-secondary">{{ __('Billing Notices') }}</a>
            <a href="{{ route('sms-setup') }}" class="btn btn-sm btn-primary">{{ __('SMS Setup') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#991b1b,#7f1d1d);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Overdue') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['overdue'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#b45309,#92400e);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Due soon') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['due_soon'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#1e3a5f,#0f172a);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('High due') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['high_due'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Selected') }}</div>
                    <div class="fs-3 fw-bold">{{ count($selected) }}</div>
                    <div class="small text-muted">
                        @if($gateway['ok'] && $gateway['balance'] !== null)
                            {{ __('Balance') }}: {{ is_scalar($gateway['balance']) ? $gateway['balance'] : json_encode($gateway['balance']) }}
                        @elseif($gateway['error'])
                            {{ \Illuminate\Support\Str::limit($gateway['error'], 40) }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <label class="small mb-0">{{ __('Due soon days') }}</label>
                <input type="number" min="1" max="14" class="form-control form-control-sm" style="width:70px"
                    wire:model.live="dueSoonDays">
                @foreach([
                    'overdue' => __('Overdue'),
                    'due_soon' => __('Due soon'),
                    'high_due' => __('High due'),
                    'all' => __('All'),
                ] as $key => $label)
                    <button type="button"
                        class="btn btn-sm {{ $activeSection === $key ? 'btn-dark' : 'btn-outline-dark' }}"
                        wire:click="setSection('{{ $key }}')">{{ $label }}</button>
                @endforeach
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="selectVisibleSection">{{ __('Select visible') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="clearSelection">{{ __('Clear') }}</button>
            </div>

            @forelse($sections as $section)
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-0">{{ $section['title'] }}</h6>
                            <div class="small text-muted">{{ $section['hint'] }}</div>
                        </div>
                        <span class="badge bg-{{ $section['severity'] }}">{{ count($section['items']) }}</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:36px;"></th>
                                        <th>{{ __('Customer') }}</th>
                                        <th>{{ __('Mobile') }}</th>
                                        <th class="text-end">{{ __('Due') }}</th>
                                        <th>{{ __('Disable') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($section['items'] as $item)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input"
                                                    value="{{ $item['customer_unique_id'] }}"
                                                    wire:model.live="selected">
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width:160px;">{{ $item['customer_name'] }}</div>
                                                <div class="small text-muted">{{ $item['customer_unique_id'] }} · {{ $item['status'] }}</div>
                                            </td>
                                            <td>{{ $item['mobile'] ?: '—' }}</td>
                                            <td class="text-end fw-semibold">{{ number_format($item['due_amount'], 2) }}</td>
                                            <td>{{ $item['auto_disable_date'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body text-muted">{{ __('No billing notices in this filter.') }}</div>
                </div>
            @endforelse
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Compose & send') }}</h6>
                </div>
                <div class="card-body pt-0">
                    <label class="form-label small">{{ __('Template') }}</label>
                    <select class="form-select form-select-sm mb-2" wire:model.live="templateId">
                        <option value="">{{ __('Custom message') }}</option>
                        @foreach($templates as $tpl)
                            <option value="{{ $tpl['id'] }}">
                                {{ $tpl['name'] }} {{ $tpl['is_active'] ? '' : '('.__('inactive').')' }}
                            </option>
                        @endforeach
                    </select>

                    <label class="form-label small">{{ __('Message') }}</label>
                    <textarea class="form-control form-control-sm mb-2" rows="6" wire:model="message"
                        placeholder="{{ __('Use placeholders…') }}"></textarea>
                    <div class="small text-muted mb-3">
                        @foreach($placeholders as $ph)
                            <code class="me-1">{{ $ph }}</code>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-primary" wire:click="queueSms" wire:loading.attr="disabled">
                            <i class="bi bi-send"></i> {{ __('Queue SMS') }} ({{ count($selected) }})
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" wire:click="prepareWhatsApp">
                            <i class="bi bi-whatsapp"></i> {{ __('WhatsApp links') }}
                        </button>
                    </div>
                    <div class="small text-muted mt-2">{{ __('SMS uses your existing gateway job. WhatsApp opens wa.me (manual).') }}</div>
                </div>
            </div>

            @if(count($waLinks))
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">{{ __('WhatsApp ready') }}</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="list-group list-group-flush">
                            @foreach($waLinks as $link)
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                    <span>
                                        <span class="fw-semibold">{{ $link['name'] }}</span>
                                        <span class="small text-muted d-block">{{ $link['mobile'] }}</span>
                                    </span>
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Recent bulk SMS logs') }}</h6>
                </div>
                <div class="card-body pt-0">
                    @forelse($recentLogs as $log)
                        <div class="py-2 border-bottom">
                            <div class="fw-semibold small">{{ $log['title'] }}</div>
                            <div class="text-muted small">{{ $log['status'] }} · {{ $log['created_at'] }}</div>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('No bulk SMS campaigns logged yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
