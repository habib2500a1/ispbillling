<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-wallet2 text-primary me-2"></i>{{ __('Wallet Approvals') }}</h3>
            <p class="text-muted mb-0">{{ __('Review reseller wallet top-up requests. Approval credits the wallet and creates a ledger transaction.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select" wire:model.live="statusFilter" aria-label="{{ __('Filter by status') }}">
                <option value="pending">{{ __('Pending') }}</option><option value="approved">{{ __('Approved') }}</option><option value="rejected">{{ __('Rejected') }}</option><option value="all">{{ __('All requests') }}</option>
            </select>
            <input type="search" class="form-control" style="min-width:220px" wire:model.live.debounce.350ms="search" placeholder="{{ __('Search reseller or reference') }}">
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach(['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'] as $key => $color)
            <div class="col-sm-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted text-capitalize">{{ __($key) }}</span><span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-6">{{ number_format($counts[$key]) }}</span>
            </div></div></div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body">
        <label class="form-label fw-semibold" for="approval-admin-note">{{ __('Decision note') }}</label>
        <input id="approval-admin-note" type="text" class="form-control" wire:model="adminNote" placeholder="{{ __('Optional for approval; required when rejecting') }}" maxlength="1000">
        @error('adminNote') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        <div class="form-text">{{ __('This note will be attached to the request acted on next.') }}</div>
    </div></div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th class="ps-4">{{ __('Submitted') }}</th><th>{{ __('Reseller') }}</th><th>{{ __('Payment details') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Review') }}</th>
                </tr></thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr wire:key="wallet-approval-{{ $request->id }}">
                            <td class="ps-4 small text-muted">{{ $request->created_at?->format('d M Y, h:i A') }}<div class="text-muted">#{{ $request->id }}</div></td>
                            <td><div class="fw-semibold">{{ $request->reseller?->company ?: $request->reseller?->user?->name }}</div><div class="small text-muted">{{ $request->reseller?->user?->email }}</div></td>
                            <td><span class="badge bg-light text-dark text-uppercase">{{ $request->payment_method }}</span>
                                @if($request->payment_reference)<div class="small mt-1">{{ __('Reference') }}: <code>{{ $request->payment_reference }}</code></div>@endif
                                @if($request->note)<div class="small text-muted mt-1">{{ $request->note }}</div>@endif
                            </td>
                            <td class="fw-bold">৳{{ number_format((float) $request->amount, 2) }}</td>
                            <td><span class="badge rounded-pill bg-{{ $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning') }}-subtle text-{{ $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning') }}">{{ __(ucfirst($request->status)) }}</span>
                                @if($request->admin_note)<div class="small text-muted mt-1">{{ $request->admin_note }}</div>@endif
                            </td>
                            <td>
                                @if($request->status === 'pending')
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $request->id }})" wire:loading.attr="disabled" wire:target="approve({{ $request->id }})"><i class="bi bi-check-lg me-1"></i>{{ __('Approve') }}</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="reject({{ $request->id }})" wire:loading.attr="disabled" wire:target="reject({{ $request->id }})">{{ __('Reject') }}</button>
                                    </div>
                                @else
                                    <span class="small text-muted">{{ $request->processor?->name }}<br>{{ $request->processed_at?->format('d M Y, h:i A') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">{{ __('No wallet requests found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())<div class="card-footer bg-white border-0">{{ $requests->links() }}</div>@endif
    </div>
</div>
