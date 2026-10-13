<?php

namespace App\Livewire\Reseller;

use App\Models\ResellerWalletApproval;
use App\Models\ResellerWalletTransaction;
use App\Services\Saas\SaasContext;
use Livewire\Component;
use Livewire\WithPagination;

class ResellerWalletManagement extends Component
{
    use WithPagination;

    public $type = 'all';

    public string $topUpAmount = '';

    public string $paymentMethod = 'bkash';

    public string $paymentReference = '';

    public string $topUpNote = '';

    protected $paginationTheme = 'bootstrap';

    public function submitTopUpRequest(): void
    {
        $validated = $this->validate([
            'topUpAmount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'paymentMethod' => ['required', 'in:bkash,nagad,rocket,bank,cash,other'],
            'paymentReference' => ['nullable', 'string', 'max:120'],
            'topUpNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $reseller = auth()->user()->reseller;
        if (! $reseller || ! $reseller->isActive()) {
            abort(403);
        }

        ResellerWalletApproval::create([
            'saas_operator_id' => SaasContext::tenantId(),
            'reseller_id' => $reseller->id,
            'amount' => round((float) $validated['topUpAmount'], 2),
            'payment_method' => $validated['paymentMethod'],
            'payment_reference' => trim($validated['paymentReference'] ?? '') ?: null,
            'note' => trim($validated['topUpNote'] ?? '') ?: null,
            'status' => 'pending',
        ]);

        $this->reset(['topUpAmount', 'paymentReference', 'topUpNote']);
        $this->paymentMethod = 'bkash';
        flash()->success(__('Wallet top-up request sent for approval.'));
    }

    public function updatingType()
    {
        $this->resetPage();
    }

    public function render()
    {
        $reseller = auth()->user()->reseller;
        if (! $reseller || ! $reseller->isActive()) {
            abort(403);
        }

        $transactions = ResellerWalletTransaction::where('reseller_id', $reseller->id)
            ->when($this->type !== 'all', function ($q) {
                $q->where('type', $this->type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        $walletRequests = ResellerWalletApproval::where('reseller_id', $reseller->id)
            ->latest()
            ->limit(10)
            ->get();

        // Calculate accounting stats for reseller
        $totalCommissionEarned = \App\Models\ResellerCommission::where('reseller_id', $reseller->id)->sum('amount');
        $totalCommissionPaid = \App\Models\ResellerWalletTransaction::where('reseller_id', $reseller->id)
            ->where('description', 'like', 'Commission Payout:%')
            ->sum('amount');
        $totalUpfrontCommission = \App\Models\Voucher::where('reseller_id', $reseller->id)
            ->where('commission_paid_upfront', true)
            ->get()
            ->sum(function($v) use ($reseller) {
                return (float)$v->value * ((float)$reseller->commission_percentage / 100);
            });

        return view('livewire.reseller.wallet-management', [
            'reseller' => $reseller,
            'transactions' => $transactions,
            'walletRequests' => $walletRequests,
            'totalCommissionEarned' => $totalCommissionEarned,
            'totalCommissionPaid' => $totalCommissionPaid,
            'totalUpfrontCommission' => $totalUpfrontCommission,
        ])->layout('layouts.app');
    }
}
