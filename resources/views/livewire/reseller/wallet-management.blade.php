<div class="container-fluid py-2">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold mb-1"><i class="bi bi-plus-circle text-success me-2"></i>{{ __('Request a wallet top-up') }}</h5>
            <p class="small text-muted mb-0">{{ __('Submit your payment details. Your balance changes only after an administrator approves the request.') }}</p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2"><label class="form-label">{{ __('Amount (৳)') }}</label><input type="number" min="0.01" step="0.01" class="form-control" wire:model="topUpAmount" placeholder="0.00">@error('topUpAmount')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-2"><label class="form-label">{{ __('Payment method') }}</label><select class="form-select" wire:model="paymentMethod"><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="rocket">Rocket</option><option value="bank">{{ __('Bank') }}</option><option value="cash">{{ __('Cash') }}</option><option value="other">{{ __('Other') }}</option></select>@error('paymentMethod')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">{{ __('Transaction reference') }}</label><input type="text" class="form-control" wire:model="paymentReference" maxlength="120" placeholder="{{ __('Optional') }}">@error('paymentReference')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">{{ __('Note') }}</label><input type="text" class="form-control" wire:model="topUpNote" maxlength="1000" placeholder="{{ __('Optional details for the reviewer') }}">@error('topUpNote')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-2 d-grid"><button type="button" class="btn btn-success" wire:click="submitTopUpRequest" wire:loading.attr="disabled"><span wire:loading.remove wire:target="submitTopUpRequest">{{ __('Send for approval') }}</span><span wire:loading wire:target="submitTopUpRequest">{{ __('Sending…') }}</span></button></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">{{ __('Recent top-up requests') }}</h5></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-4">{{ __('Date') }}</th><th>{{ __('Payment method') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th class="pe-4">{{ __('Review note') }}</th></tr></thead>
            <tbody>
                @forelse($walletRequests as $request)
                    <tr wire:key="reseller-wallet-request-{{ $request->id }}"><td class="ps-4 small text-muted">{{ $request->created_at?->format('d M Y, h:i A') }}</td><td class="text-uppercase">{{ $request->payment_method }}</td><td>{{ $request->payment_reference ?: '—' }}</td><td class="fw-semibold">৳{{ number_format((float) $request->amount, 2) }}</td><td><span class="badge bg-{{ $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning') }}-subtle text-{{ $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning') }}">{{ __(ucfirst($request->status)) }}</span></td><td class="pe-4 small text-muted">{{ $request->admin_note ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No top-up requests yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>

    <!-- Accounting Statistics Summary -->
    <div class="row g-3 mb-4">
        <!-- Wallet Balance Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #1e293b, #0f172a);">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <span class="text-uppercase fw-bold text-white-50 small d-block mb-1" style="font-size: 0.65rem; letter-spacing: 0.8px;">{{ __('Wallet Balance (Remaining)') }}</span>
                        <h3 class="fw-bold mb-1">৳{{ number_format($reseller->balance, 2) }}</h3>
                    </div>
                    <div class="mt-2 text-white-50 small" style="font-size: 0.72rem;">
                        {{ __('Reseller ID: #:id', ['id' => $reseller->id]) }}
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Commission Earned Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small d-block mb-1" style="font-size: 0.65rem; letter-spacing: 0.8px;">{{ __('Total Comm. Earned (Redeemed)') }}</span>
                        <h3 class="fw-bold text-dark mb-1">৳{{ number_format($totalCommissionEarned, 2) }}</h3>
                    </div>
                    <div class="mt-2 text-muted small" style="font-size: 0.72rem;">
                        {{ __('Credited to Wallet balance') }}
                    </div>
                </div>
            </div>
        </div>
        <!-- Upfront Commission Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small d-block mb-1" style="font-size: 0.65rem; letter-spacing: 0.8px;">{{ __('Total Comm. Upfront') }}</span>
                        <h3 class="fw-bold text-dark mb-1">৳{{ number_format($totalUpfrontCommission, 2) }}</h3>
                    </div>
                    <div class="mt-2 text-muted small" style="font-size: 0.72rem;">
                        {{ __('Retained as discount upfront') }}
                    </div>
                </div>
            </div>
        </div>
        <!-- Commission Paid Out Card -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small d-block mb-1" style="font-size: 0.65rem; letter-spacing: 0.8px;">{{ __('Total Paid Out (Cash)') }}</span>
                        <h3 class="fw-bold text-dark mb-1">৳{{ number_format($totalCommissionPaid, 2) }}</h3>
                    </div>
                    <div class="mt-2 text-muted small" style="font-size: 0.72rem;">
                        {{ __('Withdrawn in cash from admin') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ledger Column -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-wallet2 text-success me-2"></i>{{ __('Transaction Ledger') }}</h5>
                    
                    <!-- Filter -->
                    <select wire:model.live="type" class="form-select form-select-sm" style="max-width: 180px;">
                        <option value="all">{{ __('All Transactions') }}</option>
                        <option value="credit">{{ __('Credit Only') }}</option>
                        <option value="debit">{{ __('Debit Only') }}</option>
                    </select>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover">
                            <thead class="table-light">
                                <tr class="small text-muted fw-semibold">
                                    <th class="ps-4">{{ __('Date') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th>{{ __('Reference Type') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                    <th class="text-center" style="width: 120px;">{{ __('Type') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $trx)
                                    <tr>
                                        <td class="small text-muted ps-4">{{ $trx->created_at->format('Y-m-d H:i:s') }}</td>
                                        <td class="text-dark small fw-semibold">{{ __($trx->description) }}</td>
                                        <td>
                                            @if($trx->reference_type)
                                                <span class="badge bg-secondary-subtle text-secondary text-xs">{{ __($trx->reference_type) }} (#{{ $trx->reference_id }})</span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold">৳{{ number_format($trx->amount, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $trx->type === 'credit' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} px-2.5 py-1 text-uppercase text-xs" style="font-size: 0.7rem;">
                                                {{ __($trx->type) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">{{ __('No wallet transactions found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($transactions->hasPages())
                        <div class="card-footer bg-white border-0 py-3">
                            {{ $transactions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
