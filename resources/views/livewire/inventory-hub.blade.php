<div>
    <x-slot name="header">
        {{ __('Inventory Hub') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Stock & devices (lite)') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('inventory-purchases') }}" class="btn btn-sm btn-outline-secondary">{{ __('Purchases') }}</a>
            <a href="{{ route('inventory-sales') }}" class="btn btn-sm btn-outline-secondary">{{ __('Sales') }}</a>
            <button type="button" class="btn btn-sm btn-primary" wire:click="openCreate">
                <i class="bi bi-plus-lg"></i> {{ __('Add product') }}
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Products') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['products'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Stock units') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($stats['stock_units']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Stock value') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($stats['stock_value'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#b45309,#92400e);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Low stock') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['low_stock'] }}</div>
                    <div class="small opacity-75">{{ __('Moves today') }}: {{ $stats['movements_today'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <input type="search" class="form-control form-control-sm" style="max-width:240px"
            placeholder="{{ __('Search name / SKU…') }}" wire:model.live.debounce.300ms="search">
        @foreach([
            'all' => __('All'),
            'active' => __('Active'),
            'low' => __('Low stock'),
            'inactive' => __('Inactive'),
        ] as $key => $label)
            <button type="button" class="btn btn-sm {{ $filter === $key ? 'btn-dark' : 'btn-outline-dark' }}"
                wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-end">{{ __('Stock') }}</th>
                                    <th class="text-end">{{ __('Cost') }}</th>
                                    <th class="text-end">{{ __('Value') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $p)
                                    <tr class="{{ $p['is_low'] ? 'table-warning' : '' }}">
                                        <td>
                                            <div class="fw-semibold">{{ $p['name'] }}</div>
                                            <div class="small text-muted">{{ $p['sku'] ?: '—' }} · {{ $p['unit'] }}</div>
                                        </td>
                                        <td>{{ $p['category_label'] }}</td>
                                        <td class="text-end">
                                            <span class="fw-semibold">{{ $p['stock_qty'] }}</span>
                                            @if($p['reorder_level'] > 0)
                                                <div class="small text-muted">min {{ $p['reorder_level'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($p['cost_price'], 2) }}</td>
                                        <td class="text-end">{{ number_format($p['stock_value'], 2) }}</td>
                                        <td>
                                            @if($p['is_low'])
                                                <span class="badge bg-warning text-dark">{{ __('Low') }}</span>
                                            @endif
                                            <span class="badge bg-{{ $p['is_active'] ? 'success' : 'secondary' }}">
                                                {{ $p['is_active'] ? __('Active') : __('Off') }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openMove({{ $p['id'] }})">{{ __('Move') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openEdit({{ $p['id'] }})">{{ __('Edit') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-dark" wire:click="toggleActive({{ $p['id'] }})">{{ __('Toggle') }}</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-muted small">{{ __('No products yet. Add ONU, cable, routers…') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Recent movements') }}</h6>
                </div>
                <div class="card-body pt-0">
                    @forelse($recent_movements as $m)
                        <div class="py-2 border-bottom">
                            <div class="d-flex justify-content-between">
                                <div class="fw-semibold text-truncate" style="max-width:160px;">{{ $m['product'] }}</div>
                                <span class="badge bg-{{ $m['type'] === 'in' ? 'success' : ($m['type'] === 'out' ? 'danger' : 'secondary') }}">
                                    {{ $m['quantity'] > 0 ? '+' : '' }}{{ $m['quantity'] }}
                                </span>
                            </div>
                            <div class="small text-muted">
                                {{ $m['type_label'] }} · {{ __('after') }} {{ $m['stock_after'] }}
                                · {{ $m['moved_human'] }}
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">{{ __('No stock movements yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if($showProductModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(15,23,42,.45);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editId ? __('Edit product') : __('Add product') }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showProductModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small">{{ __('Name') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="name">
                                @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label small">{{ __('SKU') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="sku">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">{{ __('Category') }}</label>
                                <select class="form-select form-select-sm" wire:model="category">
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small">{{ __('Unit') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="unit">
                            </div>
                            <div class="col-4">
                                <label class="form-label small">{{ __('Reorder level') }}</label>
                                <input type="number" min="0" class="form-control form-control-sm" wire:model="reorder_level">
                            </div>
                            @unless($editId)
                                <div class="col-4">
                                    <label class="form-label small">{{ __('Opening stock') }}</label>
                                    <input type="number" min="0" class="form-control form-control-sm" wire:model="stock_qty">
                                </div>
                            @else
                                <div class="col-4">
                                    <label class="form-label small">{{ __('Current stock') }}</label>
                                    <input type="text" class="form-control form-control-sm" value="{{ $stock_qty }}" disabled>
                                </div>
                            @endunless
                            <div class="col-6">
                                <label class="form-label small">{{ __('Cost price') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="cost_price">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">{{ __('Sell price') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="sell_price">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">{{ __('Notes') }}</label>
                                <textarea class="form-control form-control-sm" rows="2" wire:model="notes"></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="invActive" wire:model="is_active">
                                    <label class="form-check-label small" for="invActive">{{ __('Active') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('showProductModal', false)">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="saveProduct" wire:loading.attr="disabled">{{ __('Save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showMoveModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(15,23,42,.45);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Stock movement') }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showMoveModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small">{{ __('Type') }}</label>
                                <select class="form-select form-select-sm" wire:model="move_type">
                                    @foreach($movement_types as $key => $label)
                                        <option value="{{ $key }}">{{ __($label) }}{{ $key === 'adjust' ? ' ('.__('set qty').')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">{{ __('Quantity') }}</label>
                                <input type="number" min="1" class="form-control form-control-sm" wire:model="move_qty">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">{{ __('Reference') }}</label>
                                <input type="text" class="form-control form-control-sm" wire:model="move_reference" placeholder="{{ __('PO / ticket / customer…') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">{{ __('Notes') }}</label>
                                <textarea class="form-control form-control-sm" rows="2" wire:model="move_notes"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('showMoveModal', false)">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="saveMove" wire:loading.attr="disabled">{{ __('Save movement') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
