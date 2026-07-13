<div>
    <x-slot name="header">
        {{ __('Subscriber Lists') }}
    </x-slot>

    <div class="row g-2 mb-3">
        @foreach($lists as $key => $label)
            <div class="col-auto">
                <button type="button" wire:click="setList('{{ $key }}')" class="btn btn-sm {{ $list === $key ? 'btn-primary' : 'btn-outline-primary' }}">
                    {{ $label }}
                    <span class="badge bg-light text-dark ms-1">{{ $counts[$key] ?? 0 }}</span>
                </button>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <input type="search" wire:model.live.debounce.300ms="search" class="form-control" placeholder="{{ __('Search name, ID, mobile...') }}">
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Mobile') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Due') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $c)
                        <tr wire:key="sub-{{ $c->id }}">
                            <td class="fw-semibold">{{ $c->customer_name }}</td>
                            <td>{{ $c->customer_unique_id }}</td>
                            <td>{{ $c->mobile }}</td>
                            <td><span class="badge bg-secondary">{{ $c->status }}</span></td>
                            <td class="text-danger fw-bold">৳{{ number_format((float) ($c->billing?->due_amount ?? 0), 2) }}</td>
                            <td><a href="{{ route('customers.show', $c->id) }}" class="btn btn-sm btn-outline-primary" wire:navigate.hover>{{ __('Open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0">{{ $customers->links() }}</div>
    </div>
</div>
