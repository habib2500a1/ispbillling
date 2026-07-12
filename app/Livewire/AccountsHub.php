<?php

namespace App\Livewire;

use App\Services\Accounts\AccountsHubService;
use Carbon\Carbon;
use Livewire\Component;

class AccountsHub extends Component
{
    public string $period = 'this_month';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['payment-collection', 'amount-collection-report', 'admin.expenses', 'admin.profit-summary'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->applyPeriod('this_month');
    }

    public function setPeriod(string $period): void
    {
        $this->applyPeriod($period);
    }

    public function applyCustom(): void
    {
        $this->period = 'custom';
        $this->from = Carbon::parse($this->from ?: now()->startOfMonth())->toDateString();
        $this->to = Carbon::parse($this->to ?: now())->toDateString();
        if ($this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
    }

    public function refresh(): void
    {
        flash()->success(__('Accounts hub refreshed.'));
    }

    private function applyPeriod(string $period): void
    {
        $this->period = $period;

        switch ($period) {
            case 'today':
                $this->from = now()->toDateString();
                $this->to = now()->toDateString();
                break;
            case 'yesterday':
                $this->from = now()->subDay()->toDateString();
                $this->to = now()->subDay()->toDateString();
                break;
            case 'last_month':
                $this->from = now()->subMonth()->startOfMonth()->toDateString();
                $this->to = now()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'last_30':
                $this->from = now()->subDays(29)->toDateString();
                $this->to = now()->toDateString();
                break;
            default:
                $this->period = 'this_month';
                $this->from = now()->startOfMonth()->toDateString();
                $this->to = now()->endOfMonth()->toDateString();
                break;
        }
    }

    public function render()
    {
        $from = Carbon::parse($this->from ?: now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->to ?: now()->endOfMonth())->endOfDay();

        $data = app(AccountsHubService::class)->payload($from, $to);

        return view('livewire.accounts-hub', $data)->layout('layouts.app');
    }
}
