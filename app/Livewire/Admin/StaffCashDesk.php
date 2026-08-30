<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Saas\SaasContext;
use App\Services\Saas\StaffCashService;
use Livewire\Component;

class StaffCashDesk extends Component
{
    public string $from;

    public string $to;

    public ?int $staffId = null;

    public int $amount = 0;

    public string $entry_date = '';

    public string $note = '';

    public string $type = 'deposit';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['payment-collection', 'amount-collection', 'staff-cash'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
        $this->entry_date = now()->toDateString();
    }

    public function record(): void
    {
        $this->validate([
            'staffId' => 'required|exists:users,id',
            'amount' => 'required|integer|min:1',
            'entry_date' => 'required|date',
            'type' => 'required|in:deposit,adjustment',
            'note' => 'nullable|string|max:255',
        ]);

        $staff = User::findOrFail($this->staffId);
        if (! canSellSaas()) {
            $mine = SaasContext::operator();
            if ($mine && $staff->saas_operator_id !== $mine->id && $staff->id !== $mine->user_id) {
                abort(403);
            }
        }

        app(StaffCashService::class)->deposit($staff, $this->amount, $this->entry_date, $this->note ?: null, $this->type);
        flash()->success($this->type === 'deposit' ? __('Office deposit recorded.') : __('Adjustment saved.'));
        $this->reset(['amount', 'note']);
        $this->type = 'deposit';
        $this->entry_date = now()->toDateString();
    }

    public function render()
    {
        $operator = canSellSaas() ? null : SaasContext::operator();
        $cash = app(StaffCashService::class);
        $rows = $cash->ledger($operator, $this->from, $this->to);
        $staff = collect($rows)->pluck('user');

        return view('livewire.admin.staff-cash-desk', [
            'rows' => $rows,
            'receipts' => $cash->receipts($operator, $this->from, $this->to),
            'staff' => $staff,
            'totals' => [
                'collected' => collect($rows)->sum('collected'),
                'deposited' => collect($rows)->sum('deposited'),
                'due' => collect($rows)->sum('due'),
            ],
        ])->layout('layouts.app');
    }
}
