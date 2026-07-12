<div>
    <x-slot name="header">
        {{ __('Accounts Hub') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Period') }}: <strong>{{ $period_meta['label'] }}</strong>
            <span class="ms-2">{{ __('Updated') }}: {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('billing-notices') }}" class="btn btn-sm btn-outline-secondary">{{ __('Billing Notices') }}</a>
            <a href="{{ route('admin.expenses') }}" class="btn btn-sm btn-outline-secondary">{{ __('Expenses') }}</a>
            <a href="{{ route('admin.profit-summary') }}" class="btn btn-sm btn-primary">{{ __('Profit & Loss') }}</a>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach([
            'today' => __('Today'),
            'yesterday' => __('Yesterday'),
            'this_month' => __('This month'),
            'last_month' => __('Last month'),
            'last_30' => __('Last 30 days'),
        ] as $key => $label)
            <button type="button"
                class="btn btn-sm {{ $period === $key ? 'btn-dark' : 'btn-outline-dark' }}"
                wire:click="setPeriod('{{ $key }}')">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="row g-2 mb-3 align-items-end">
        <div class="col-auto">
            <label class="form-label small mb-0">{{ __('From') }}</label>
            <input type="date" class="form-control form-control-sm" wire:model="from">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">{{ __('To') }}</label>
            <input type="date" class="form-control form-control-sm" wire:model="to">
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="applyCustom">{{ __('Apply') }}</button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Income') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($kpis['income'], 2) }}</div>
                    <div class="small">{{ __('Collections') }} {{ number_format($kpis['collections'], 2) }} · {{ __('Hotspot') }} {{ number_format($kpis['hotspot'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#b45309,#92400e);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Expenses') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($kpis['expenses'], 2) }}</div>
                    <div class="small">{{ $kpis['expense_count'] }} {{ __('entries') }} · {{ __('Comm.') }} {{ number_format($kpis['commissions'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,{{ $kpis['net_profit'] >= 0 ? '#0f172a,#1e3a5f' : '#991b1b,#7f1d1d' }});">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Net profit') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($kpis['net_profit'], 2) }}</div>
                    <div class="small">{{ __('Income − expenses − commissions') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Today collected') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($kpis['today_collected'], 2) }}</div>
                    <div class="small">{{ __('Open due') }}: {{ number_format($kpis['due_active'], 2) }} ({{ $kpis['due_customers'] }})</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('Monthly rent (active)') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($kpis['monthly_rent'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('Previous due') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($kpis['previous_due_active'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('Collections (period)') }}</div>
                    <div class="fs-4 fw-bold">{{ $kpis['collection_count'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('Reseller wallets') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($kpis['reseller_balance'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Expenses by category') }}</h6>
                </div>
                <div class="card-body pt-0">
                    @forelse($expenses_by_category as $row)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <span class="badge bg-{{ $row['color'] }}">{{ $row['label'] }}</span>
                                <span class="small text-muted ms-1">{{ $row['count'] }}</span>
                            </div>
                            <div class="fw-semibold">{{ number_format($row['total'], 2) }}</div>
                        </div>
                    @empty
                        <div class="text-muted small py-3">{{ __('No expenses in this period.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('12-month trend') }}</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Month') }}</th>
                                    <th class="text-end">{{ __('Revenue') }}</th>
                                    <th class="text-end">{{ __('Expense') }}</th>
                                    <th class="text-end">{{ __('Net') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trend as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['revenue'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['expense'], 2) }}</td>
                                        <td class="text-end {{ $row['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row['net'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between">
                    <h6 class="fw-bold mb-0">{{ __('Recent collections') }}</h6>
                    <a href="{{ route('payment-collection') }}" class="small">{{ __('Collect') }}</a>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Method') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recent_collections as $row)
                                    <tr>
                                        <td>{{ $row['date'] }}</td>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width:160px;">{{ $row['customer'] }}</div>
                                            <div class="small text-muted">{{ $row['uid'] }}</div>
                                        </td>
                                        <td>{{ $row['method'] }}</td>
                                        <td class="text-end fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted small">{{ __('No collections in this period.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(count($payment_breakdown))
                        <div class="mt-3 small text-muted">{{ __('By payment method') }}:</div>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            @foreach($payment_breakdown as $m)
                                <span class="badge bg-light text-dark border">{{ $m['method'] }}: {{ number_format($m['total'], 2) }} ({{ $m['count'] }})</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between">
                    <h6 class="fw-bold mb-0">{{ __('Recent expenses') }}</h6>
                    <a href="{{ route('admin.expenses') }}" class="small">{{ __('Manage') }}</a>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recent_expenses as $row)
                                    <tr>
                                        <td>{{ $row['date'] }}</td>
                                        <td class="text-truncate" style="max-width:160px;">{{ $row['title'] }}</td>
                                        <td><span class="badge bg-{{ $row['color'] }}">{{ $row['category'] }}</span></td>
                                        <td class="text-end fw-semibold">{{ number_format($row['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted small">{{ __('No expenses in this period.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
