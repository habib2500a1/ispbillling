<div>
    <x-slot name="header">
        {{ __('Sales / Issue') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Counter sale & issue to customer') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh"><i class="bi bi-arrow-repeat"></i></button>
            <a href="{{ route('inventory-hub') }}" class="btn btn-sm btn-outline-secondary">{{ __('Inventory') }}</a>
            <a href="{{ route('inventory-purchases') }}" class="btn btn-sm btn-outline-secondary">{{ __('Purchases') }}</a>
            <button type="button" class="btn btn-sm btn-outline-dark" wire:click="openCreate('issue')">{{ __('Issue') }}</button>
            <button type="button" class="btn btn-sm btn-primary" wire:click="openCreate('counter')">{{ __('New sale') }}</button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Sales (month)') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['sales_month'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Revenue') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['revenue_month'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Gross profit') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['profit_month'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Issues (month)') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['issues_month'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach(['all'=>__('All'),'counter'=>__('Counter'),'issue'=>__('Issues'),'month'=>__('This month')] as $key=>$label)
            <button type="button" class="btn btn-sm {{ $filter === $key ? 'btn-dark' : 'btn-outline-dark' }}" wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
            @forelse($sales as $sale)
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div>
                            <div class="fw-bold">{{ $sale['sale_number'] }}
                                <span class="badge bg-{{ $sale['channel'] === 'counter' ? 'success' : 'info' }}">{{ $sale['channel_label'] }}</span>
                            </div>
                            <div class="small text-muted">
                                {{ $sale['customer_name'] }}
                                @if($sale['phone']) · {{ $sale['phone'] }} @endif
                                · {{ $sale['sold_at'] }} · {{ $sale['staff'] ?: '—' }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">{{ number_format($sale['total'], 2) }}</div>
                            <div class="small text-muted">{{ __('Profit') }} {{ number_format($sale['profit'], 2) }}</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Line') }}</th></tr></thead>
                            <tbody>
                                @foreach($sale['items'] as $item)
                                    <tr>
                                        <td>{{ $item['product'] }}</td>
                                        <td class="text-end">{{ $item['quantity'] }}</td>
                                        <td class="text-end">{{ number_format($item['unit_price'], 2) }}</td>
                                        <td class="text-end">{{ number_format($item['line_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-muted small">{{ __('No sales yet. Add stock via Purchases, then sell or issue.') }}</div>
            @endforelse
        </div>
    </div>

    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(15,23,42,.45);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $channel === 'counter' ? __('New sale') : __('Issue to customer') }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Channel') }}</label>
                                <select class="form-select form-select-sm" wire:model.live="channel">
                                    @foreach($channels as $key => $label)
                                        <option value="{{ $key }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Payment') }}</label>
                                <select class="form-select form-select-sm" wire:model="payment_method">
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="bkash">bKash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="n/a">{{ __('N/A') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Discount') }}</label>
                                <input type="number" min="0" step="0.01" class="form-control form-control-sm" wire:model="discount">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">{{ __('Customer') }}</label>
                                <input type="search" class="form-control form-control-sm" placeholder="{{ __('Search name / mobile / UID…') }}"
                                    wire:model.live.debounce.300ms="customerSearch">
                                @if(count($customerResults))
                                    <div class="list-group list-group-flush border rounded mt-1">
                                        @foreach($customerResults as $c)
                                            <button type="button" class="list-group-item list-group-item-action py-2"
                                                wire:click="selectCustomer('{{ $c['uid'] }}', '{{ addslashes($c['label']) }}', '{{ addslashes($c['mobile'] ?? '') }}')">
                                                {{ $c['label'] }} · {{ $c['mobile'] ?: '—' }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                @if($customerUid)
                                    <div class="small text-success mt-1">{{ __('Linked') }}: {{ $customerUid }}</div>
                                @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label small">{{ __('Notes') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="notes">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <h6 class="mb-0">{{ __('Lines') }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addLine">{{ __('Add line') }}</button>
                        </div>
                        @foreach($lines as $i => $line)
                            <div class="row g-2 mb-2" wire:key="sale-line-{{ $i }}">
                                <div class="col-md-6">
                                    <select class="form-select form-select-sm" wire:model="lines.{{ $i }}.product_id">
                                        <option value="">{{ __('Product…') }}</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p['id'] }}">{{ $p['label'] }} ({{ $p['stock_qty'] }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" min="1" class="form-control form-control-sm" wire:model="lines.{{ $i }}.quantity" placeholder="Qty">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" min="0" step="0.01" class="form-control form-control-sm" wire:model="lines.{{ $i }}.unit_price" placeholder="{{ __('Price') }}">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeLine({{ $i }})">&times;</button>
                                </div>
                            </div>
                        @endforeach
                        @if(count($products) === 0)
                            <div class="alert alert-warning small mb-0">{{ __('No stocked products. Receive a PO first.') }}</div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('showModal', false)">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="saveSale" wire:loading.attr="disabled">{{ __('Save & stock out') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
