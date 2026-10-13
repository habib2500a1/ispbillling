<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i>{{ __('Bill Waivers') }}</h3>
            <p class="text-muted mb-0">{{ __('Apply a reasoned waiver to an outstanding bill and keep a permanent audit record.') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body">
                <div class="small text-muted text-uppercase fw-semibold">{{ __('Waivers recorded') }}</div>
                <div class="fs-3 fw-bold mt-2">{{ number_format($summary['count']) }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body">
                <div class="small text-muted text-uppercase fw-semibold">{{ __('Total waived') }}</div>
                <div class="fs-3 fw-bold mt-2">৳{{ number_format($summary['amount'], 2) }}</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold mb-0">{{ __('Apply a bill waiver') }}</h5>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3">
                <div class="col-lg-5 position-relative">
                    <label class="form-label fw-semibold">{{ __('Find customer') }}</label>
                    <div class="input-group">
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="customerSearch" placeholder="{{ __('Search by customer name, ID or mobile') }}" @if($customerUniqueId) disabled @endif>
                        @if($customerUniqueId)
                            <button type="button" class="btn btn-outline-secondary" wire:click="clearCustomer">{{ __('Change') }}</button>
                        @endif
                    </div>
                    @error('customerUniqueId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @if($customerMatches->isNotEmpty())
                        <div class="list-group position-absolute start-0 end-0 shadow mt-1" style="z-index:5;">
                            @foreach($customerMatches as $match)
                                <button type="button" class="list-group-item list-group-item-action" wire:key="waiver-customer-{{ $match->customer_unique_id }}" wire:click="selectCustomer($event.currentTarget.dataset.customerId)" data-customer-id="{{ $match->customer_unique_id }}">
                                    <span class="fw-semibold">{{ $match->customer_name }}</span>
                                    <span class="text-muted small ms-2">{{ $match->customer_unique_id }} · {{ $match->mobile }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">{{ __('Waiver amount (৳)') }}</label>
                    <input type="number" min="0.01" step="0.01" class="form-control" wire:model="amount" placeholder="0.00">
                    @error('amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-5">
                    <label class="form-label fw-semibold">{{ __('Reason') }}</label>
                    <input type="text" class="form-control" wire:model="reason" maxlength="1000" placeholder="{{ __('Required explanation for this waiver') }}">
                    @error('reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="button" class="btn btn-primary px-4" wire:click="applyWaiver" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="applyWaiver"><i class="bi bi-check2-circle me-1"></i>{{ __('Apply waiver') }}</span>
                        <span wire:loading wire:target="applyWaiver">{{ __('Saving…') }}</span>
                    </button>
                </div>
            </div>
            <div class="small text-muted mt-3">{{ __('A waiver cannot be greater than the customer’s current outstanding due. Each applied waiver is recorded with the admin and reason.') }}</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h5 class="fw-bold mb-0">{{ __('Waiver history') }}</h5>
            <input type="search" class="form-control" style="max-width:320px" wire:model.live.debounce.350ms="search" placeholder="{{ __('Search customer or reason') }}">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th class="ps-4">{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Applied by') }}</th><th class="text-end pe-4">{{ __('Amount') }}</th>
                </tr></thead>
                <tbody>
                    @forelse($waivers as $waiver)
                        <tr wire:key="billing-waiver-{{ $waiver->id }}">
                            <td class="ps-4 small text-muted">{{ $waiver->created_at?->format('d M Y, h:i A') }}</td>
                            <td><div class="fw-semibold">{{ $waiver->customer?->customer_name ?? __('Deleted customer') }}</div><div class="small text-muted">{{ $waiver->customer_unique_id }}</div></td>
                            <td>{{ $waiver->reason }}</td>
                            <td>{{ $waiver->waivedBy?->name ?? __('System') }}</td>
                            <td class="text-end pe-4 fw-bold text-success">৳{{ number_format((float) $waiver->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">{{ __('No bill waivers have been recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($waivers->hasPages())<div class="card-footer bg-white border-0">{{ $waivers->links() }}</div>@endif
    </div>
</div>
