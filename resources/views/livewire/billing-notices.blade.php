<div>
    <x-slot name="header">
        {{ __('Billing Notices') }}
    </x-slot>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Overdue') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['overdue'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #d97706, #b45309); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Due soon') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['due_soon'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #0ea5e9, #0284c7); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('High due rows') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['high_due'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #1e293b, #334155); color: #fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Listed due ৳') }}</div>
                    <div class="fs-3 fw-bold">৳{{ number_format($summary['total_due_amount'] ?? 0, 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <label class="small text-muted mb-0">{{ __('Due-soon window (days)') }}</label>
                <input type="number" min="1" max="14" class="form-control form-control-sm" style="width: 80px;" wire:model.live="dueSoonDays">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm {{ $activeSection === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setSection('all')">{{ __('All') }}</button>
                    <button type="button" class="btn btn-sm {{ $activeSection === 'overdue' ? 'btn-danger' : 'btn-outline-danger' }}" wire:click="setSection('overdue')">{{ __('Overdue') }}</button>
                    <button type="button" class="btn btn-sm {{ $activeSection === 'due_soon' ? 'btn-warning' : 'btn-outline-warning' }}" wire:click="setSection('due_soon')">{{ __('Due soon') }}</button>
                    <button type="button" class="btn btn-sm {{ $activeSection === 'high_due' ? 'btn-info' : 'btn-outline-info' }}" wire:click="setSection('high_due')">{{ __('High due') }}</button>
                </div>
            </div>
            <a href="{{ route('payment-collection') }}" class="btn btn-sm btn-outline-success">{{ __('Payment Collection') }}</a>
        </div>
    </div>

    @forelse($sections as $section)
        <div class="card border-0 shadow-sm rounded-4 mb-3" wire:key="section-{{ $section['key'] }}">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="fw-bold mb-1">
                        <span class="badge bg-{{ $section['severity'] }} me-1">&nbsp;</span>
                        {{ $section['title'] }}
                        <span class="badge bg-light text-dark border ms-1">{{ count($section['items']) }}</span>
                    </h6>
                    <p class="small text-muted mb-0">{{ $section['hint'] }}</p>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Due') }}</th>
                                <th>{{ __('Rent') }}</th>
                                <th>{{ __('Disable date') }}</th>
                                <th class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($section['items'] as $item)
                                <tr wire:key="{{ $item['id'] }}">
                                    <td>
                                        <div class="fw-semibold">{{ $item['customer_name'] }}</div>
                                        <div class="small text-muted">{{ $item['customer_unique_id'] }} @if($item['mobile']) · {{ $item['mobile'] }} @endif</div>
                                    </td>
                                    <td><span class="badge bg-secondary">{{ $item['status'] }}</span></td>
                                    <td class="fw-bold text-danger">৳{{ number_format($item['due_amount'], 2) }}</td>
                                    <td>৳{{ number_format($item['monthly_rent'], 2) }}</td>
                                    <td>{{ $item['auto_disable_date'] }}</td>
                                    <td class="text-end">
                                        @if(!empty($item['edit_url']))
                                            <a href="{{ $item['edit_url'] }}" class="btn btn-sm btn-outline-primary">{{ __('Open') }}</a>
                                        @endif
                                        <a href="{{ route('payment-collection') }}" class="btn btn-sm btn-outline-success">{{ __('Collect') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center text-muted py-5">
                {{ __('No billing notices right now. Overdue / due-soon lists will appear when customers have billing dates and dues.') }}
            </div>
        </div>
    @endforelse
</div>
