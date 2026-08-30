<div class="px-md-4 zoom-in">
    <x-slot name="header">
        {{ $type === 'advance' ? __('Advance') : __('Discount') }}
    </x-slot>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1 fw-bold">{{ $type === 'advance' ? __('Advance list') : __('Discount list') }}</h4>
            <p class="text-muted small mb-0">
                {{ $type === 'advance'
                    ? __('Customers who currently have advance on their bill.')
                    : __('Customers who currently have discount on their bill.') }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('billing.discounts') }}" class="btn btn-sm {{ $type === 'discount' ? 'btn-warning' : 'btn-outline-warning' }}">
                <i class="bi bi-percent me-1"></i>{{ __('Discount') }}
            </a>
            <a href="{{ route('billing.advances') }}" class="btn btn-sm {{ $type === 'advance' ? 'btn-info' : 'btn-outline-info' }}">
                <i class="bi bi-wallet2 me-1"></i>{{ __('Advance') }}
            </a>
            <a href="{{ route('payment-collection') }}" class="btn btn-sm btn-success">
                <i class="bi bi-cash-coin me-1"></i>{{ __('Collect Payment') }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;background:{{ $type === 'advance' ? 'linear-gradient(135deg,#0284c7,#0369a1)' : 'linear-gradient(135deg,#d97706,#b45309)' }};color:#fff;">
                <div class="card-body">
                    <div class="text-uppercase small fw-bold opacity-75">{{ __('Total') }}</div>
                    <div class="fs-3 fw-bold">{{ number_format($total, 2) }} {{ siteUrlSettings('site_currency') }}</div>
                    <div class="small opacity-75">{{ $rows->total() }} {{ __('customers') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-8">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body">
                    <label class="form-label fw-semibold mb-1">{{ __('Search customer') }}</label>
                    <input type="search" class="form-control" wire:model.live.debounce.300ms="q"
                           placeholder="{{ __('ID, name, or mobile') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Customer ID') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('PPPoE') }}</th>
                        <th class="text-end">{{ __('Due') }}</th>
                        <th class="text-end">{{ $type === 'advance' ? __('Advance') : __('Discount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $customer)
                        <tr>
                            <td class="fw-semibold">{{ $customer->customer_unique_id }}</td>
                            <td>
                                <div>{{ $customer->customer_name }}</div>
                                <div class="small text-muted">{{ $customer->mobile }}</div>
                            </td>
                            <td>{{ $customer->pppUser->username ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) ($customer->billing->due_amount ?? 0), 2) }}</td>
                            <td class="text-end fw-bold {{ $type === 'advance' ? 'text-info' : 'text-warning' }}">
                                {{ number_format((float) ($customer->billing->{$column} ?? 0), 2) }}
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-success" href="{{ route('payment-collection', ['customer' => encrypt($customer->customer_unique_id)]) }}">
                                    {{ __('Collect') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ $type === 'advance' ? __('No customer has advance right now.') : __('No customer has discount right now.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-white">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
