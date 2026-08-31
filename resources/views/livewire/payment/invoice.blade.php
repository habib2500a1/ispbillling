<div class="px-3 px-md-4 zoom-in" x-data @focus-paid-amount.window="document.getElementById('paid_amount')?.focus()">
    <x-slot name="header">
        {{ __('Payment Invoice') }}
    </x-slot>

    <div class="row g-3 justify-content-center">
        <div class="col-12 col-lg-4">
            <x-mikrotik.section-form>
                <x-slot name="title">{{ __('Search') }}</x-slot>
                <x-slot name="aside">
                    <input type="search" name="customer_list" class="form-control w-100"
                        placeholder="{{ siteUrlSettings('customer_id_prefix') ?: 'FCNET' }}-XXX, {{ __('name, mobile') }}"
                        wire:model.live.debounce.400ms="customer_list" autocomplete="off" tabindex="1"
                        wire:keydown.arrow-down="incrementHighlight"
                        wire:keydown.arrow-up="decrementHighlight"
                        wire:keydown.enter="selectHighlightedCustomer"
                        id="customer_list" autofocus>
                    @if (!empty($customers))
                        <ul class="scrollbar-overlay overflow-auto list-group position-absolute w-100 shadow-sm"
                            style="max-height:25rem; z-index: 1000;">
                            @foreach ($customers as $index => $customer)
                                <li wire:click="selectCustomer('{{ encrypt($customer->customer_unique_id) }}')"
                                    class="list-group-item {{ $index === $highlightedIndex ? 'active' : '' }}"
                                    style="cursor: pointer;" wire:key="customer-{{ $customer->id }}">
                                    <div class="fw-semibold">{{ $customer->customer_unique_id }}</div>
                                    <div class="small">{{ $customer->customer_name }} · {{ $customer->mobile }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-slot>
            </x-mikrotik.section-form>
        </div>

        <div class="col-12 col-lg-8">
            @if (!empty($info_data))
                <x-enterprise-invoice
                    :customer="$info_data"
                    :billing="$info_data->billing"
                    :collections="$collectionSummary"
                    :invoice-no="collect($collectionSummary)->pluck('id')->map(fn ($id) => siteUrlSettings('site_invoice_prefix').$id)->implode(', ')"
                    :invoice-date="now()"
                />

                <div class="text-center mt-3 no-print">
                    <button type="button" class="btn btn-sm btn-primary" wire:click="printPage">
                        <i class="bi bi-printer me-1"></i>{{ __('Print Invoice') }}
                    </button>
                </div>
            @else
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
                        {{ __('Search a customer to preview their invoice.') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
    <script>
        Livewire.on('triggerPrint', () => {
            window.print();
        });
        Livewire.on('focusInput', () => {
            document.getElementById('customer_list')?.focus();
        });
    </script>
@endpush
