<?php

namespace App\Livewire;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Support\FeatureModuleRegistry;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriberListsHub extends Component
{
    use WithPagination;

    public string $list = 'all';

    public string $search = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['customers.index'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->list = request()->query('list', 'all');
    }

    public function setList(string $list): void
    {
        $this->list = $list;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $q = CustomersInfo::query()->with(['billing', 'official', 'pppUser']);

        $q = match ($this->list) {
            'active' => $q->where('status', 'active'),
            'inactive' => $q->where('status', 'inactive'),
            'disable', 'disabled' => $q->whereIn('status', ['disable', 'disabled']),
            'vip' => $q->where(function ($sq) {
                $sq->where('status', 'vip')
                    ->orWhereHas('official', fn ($oq) => $oq->where('customer_type', 'vip'));
            }),
            'due' => $q->whereHas('billing', fn ($bq) => $bq->where('due_amount', '>', 0)),
            'expired' => $q->whereHas('billing', fn ($bq) => $bq->whereDate('auto_disable_date', '<', now())),
            default => $q,
        };

        if ($this->search !== '') {
            $s = $this->search;
            $q->where(function ($sq) use ($s) {
                $sq->where('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_unique_id', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%");
            });
        }

        $counts = [
            'all' => CustomersInfo::count(),
            'active' => CustomersInfo::where('status', 'active')->count(),
            'due' => CustomersInfo::whereHas('billing', fn ($bq) => $bq->where('due_amount', '>', 0))->count(),
            'vip' => CustomersInfo::where('status', 'vip')->count(),
            'inactive' => CustomersInfo::where('status', 'inactive')->count(),
            'disable' => CustomersInfo::whereIn('status', ['disable', 'disabled'])->count(),
        ];

        return view('livewire.subscriber-lists-hub', [
            'customers' => $q->latest('id')->paginate(20),
            'counts' => $counts,
            'lists' => [
                'all' => __('All'),
                'active' => __('Active'),
                'due' => __('Due'),
                'vip' => __('VIP'),
                'inactive' => __('Inactive'),
                'disable' => __('Disabled'),
                'expired' => __('Expired'),
            ],
        ])->layout('layouts.app');
    }
}
