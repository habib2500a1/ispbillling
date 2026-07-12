<div>
    <x-slot name="header">
        {{ __('Purchases / Warehouse') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Inventory PO lite') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('inventory-hub') }}" class="btn btn-sm btn-outline-secondary">{{ __('Inventory Hub') }}</a>
            <button type="button" class="btn btn-sm btn-outline-dark" wire:click="openWhModal">{{ __('Add warehouse') }}</button>
            <button type="button" class="btn btn-sm btn-primary" wire:click="openPoModal">
                <i class="bi bi-plus-lg"></i> {{ __('New PO') }}
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Warehouses') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['warehouses'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#b45309,#92400e);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Open POs') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['open_pos'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Received (month)') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['received_month'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Open value') }}</div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['open_value'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm {{ $tab === 'orders' ? 'btn-dark' : 'btn-outline-dark' }}" wire:click="setTab('orders')">{{ __('Purchase orders') }}</button>
        <button type="button" class="btn btn-sm {{ $tab === 'warehouses' ? 'btn-dark' : 'btn-outline-dark' }}" wire:click="setTab('warehouses')">{{ __('Warehouses') }}</button>
        @if($tab === 'orders')
            @foreach(['open' => __('Open'), 'received' => __('Received'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $key => $label)
                <button type="button" class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-primary' }}"
                    wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
            @endforeach
        @endif
    </div>

    @if($tab === 'warehouses')
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Address') }}</th>
                                <th>{{ __('Default') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($warehouses as $w)
                                <tr>
                                    <td>{{ $w['code'] ?: '—' }}</td>
                                    <td class="fw-semibold">{{ $w['name'] }}</td>
                                    <td>{{ $w['address'] ?: '—' }}</td>
                                    <td>@if($w['is_default']) <span class="badge bg-success">{{ __('Yes') }}</span> @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted small">{{ __('No warehouses.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                @forelse($orders as $po)
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div>
                                <div class="fw-bold">{{ $po['po_number'] }}
                                    <span class="badge bg-{{ $po['status'] === 'received' ? 'success' : ($po['status'] === 'cancelled' ? 'secondary' : 'warning') }}">{{ $po['status_label'] }}</span>
                                </div>
                                <div class="small text-muted">
                                    {{ $po['vendor_name'] ?: __('No vendor') }} · {{ $po['warehouse'] ?: '—' }}
                                    · {{ number_format($po['total'], 2) }}
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                @if($po['status'] === 'draft')
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="markOrdered({{ $po['id'] }})">{{ __('Mark ordered') }}</button>
                                @endif
                                @if($po['can_receive'])
                                    <button type="button" class="btn btn-sm btn-success" wire:click="receive({{ $po['id'] }})" wire:confirm="{{ __('Receive stock for this PO?') }}">{{ __('Receive') }}</button>
                                @endif
                                @if(in_array($po['status'], ['draft', 'ordered'], true))
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancel({{ $po['id'] }})">{{ __('Cancel') }}</button>
                                @endif
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Product') }}</th>
                                        <th class="text-end">{{ __('Qty') }}</th>
                                        <th class="text-end">{{ __('Unit cost') }}</th>
                                        <th class="text-end">{{ __('Line') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($po['items'] as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item['product'] }}</div>
                                                <div class="small text-muted">{{ $item['sku'] ?: '' }}</div>
                                            </td>
                                            <td class="text-end">{{ $item['quantity'] }}</td>
                                            <td class="text-end">{{ number_format($item['unit_cost'], 2) }}</td>
                                            <td class="text-end">{{ number_format($item['line_total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="text-muted small">{{ __('No purchase orders in this filter. Create a product first, then a PO.') }}</div>
                @endforelse
            </div>
        </div>
    @endif

    @if($showPoModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(15,23,42,.45);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('New purchase order') }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showPoModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Vendor') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="vendor_name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Warehouse') }}</label>
                                <select class="form-select form-select-sm" wire:model="warehouse_id">
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w['id'] }}">{{ $w['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Status') }}</label>
                                <select class="form-select form-select-sm" wire:model="po_status">
                                    <option value="draft">{{ __('Draft') }}</option>
                                    <option value="ordered">{{ __('Ordered') }}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">{{ __('Notes') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="notes">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">{{ __('Lines') }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addLine">{{ __('Add line') }}</button>
                        </div>
                        @foreach($lines as $i => $line)
                            <div class="row g-2 mb-2 align-items-end" wire:key="line-{{ $i }}">
                                <div class="col-md-6">
                                    <label class="form-label small">{{ __('Product') }}</label>
                                    <select class="form-select form-select-sm" wire:model="lines.{{ $i }}.product_id">
                                        <option value="">{{ __('Select…') }}</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p['id'] }}">{{ $p['label'] }} (stock {{ $p['stock_qty'] }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">{{ __('Qty') }}</label>
                                    <input type="number" min="1" class="form-control form-control-sm" wire:model="lines.{{ $i }}.quantity">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">{{ __('Unit cost') }}</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="lines.{{ $i }}.unit_cost">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeLine({{ $i }})">&times;</button>
                                </div>
                            </div>
                        @endforeach
                        @if(count($products) === 0)
                            <div class="alert alert-warning small mb-0">{{ __('No active products. Add products in Inventory Hub first.') }}</div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('showPoModal', false)">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="savePo" wire:loading.attr="disabled">{{ __('Create PO') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showWhModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(15,23,42,.45);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Add warehouse') }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showWhModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small">{{ __('Code') }}</label>
                            <input type="text" class="form-control form-control-sm" wire:model="wh_code">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('Name') }}</label>
                            <input type="text" class="form-control form-control-sm" wire:model="wh_name">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('Address') }}</label>
                            <input type="text" class="form-control form-control-sm" wire:model="wh_address">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="whDef" wire:model="wh_default">
                            <label class="form-check-label small" for="whDef">{{ __('Default warehouse') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('showWhModal', false)">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="saveWarehouse">{{ __('Save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
