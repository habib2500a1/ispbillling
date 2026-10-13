<?php

namespace App\Livewire\Admin;

use App\Models\Reseller;
use App\Models\ResellerWalletApproval;
use App\Services\ResellerWalletService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class WalletApprovals extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'pending';

    public string $adminNote = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $this->authorizeAccess();
        $this->adminNote = trim($this->adminNote);
        $validated = $this->validate([
            'adminNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $processed = DB::transaction(function () use ($id, $validated): bool {
            $request = $this->requestsQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                $this->addError('adminNote', __('This wallet request has already been processed.'));

                return false;
            }

            $reseller = Reseller::query()->whereKey($request->reseller_id)->lockForUpdate()->firstOrFail();
            $transaction = app(ResellerWalletService::class)->credit(
                $reseller,
                (float) $request->amount,
                __('Wallet top-up approved #:id', ['id' => $request->id]),
                'wallet_approval',
                $request->id,
            );

            $request->update([
                'status' => 'approved',
                'admin_note' => trim($validated['adminNote'] ?? '') ?: null,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'wallet_transaction_id' => $transaction->id,
            ]);

            return true;
        });

        if (! $processed) {
            return;
        }

        $this->reset('adminNote');
        flash()->success(__('Wallet request approved and balance credited.'));
    }

    public function reject(int $id): void
    {
        $this->authorizeAccess();
        $this->adminNote = trim($this->adminNote);
        $validated = $this->validate([
            'adminNote' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $processed = DB::transaction(function () use ($id, $validated): bool {
            $request = $this->requestsQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                $this->addError('adminNote', __('This wallet request has already been processed.'));

                return false;
            }

            $request->update([
                'status' => 'rejected',
                'admin_note' => trim($validated['adminNote']),
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);

            return true;
        });

        if (! $processed) {
            return;
        }

        $this->reset('adminNote');
        flash()->success(__('Wallet request rejected.'));
    }

    public function render()
    {
        $this->authorizeAccess();

        $requests = $this->requestsQuery()
            ->with(['reseller.user', 'processor'])
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($searchQuery) use ($term) {
                    $searchQuery->whereHas('reseller', function ($reseller) use ($term) {
                        $reseller->where('company', 'like', '%'.$term.'%')
                            ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
                    })->orWhere('payment_reference', 'like', '%'.$term.'%');
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(20);

        $counts = [
            'pending' => $this->requestsQuery()->where('status', 'pending')->count(),
            'approved' => $this->requestsQuery()->where('status', 'approved')->count(),
            'rejected' => $this->requestsQuery()->where('status', 'rejected')->count(),
        ];

        return view('livewire.admin.wallet-approvals', compact('requests', 'counts'))
            ->layout('layouts.app');
    }

    private function requestsQuery()
    {
        return ResellerWalletApproval::query();
    }

    private function authorizeAccess(): void
    {
        if (! hasAccess(['Super Admin'], ['all-customer'])) {
            abort(403, 'Unauthorized action.');
        }
    }
}
