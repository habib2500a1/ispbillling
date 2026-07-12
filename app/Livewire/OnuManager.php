<?php

namespace App\Livewire;

use App\Models\CustomerOnu;
use App\Services\Olt\IspbillingOpticalBridge;
use Livewire\Component;
use Livewire\WithPagination;

class OnuManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $tab = 'local'; // local|remote

    public ?string $statusMessage = null;

    public bool $statusOk = false;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['olt-management', 'onu-management', 'mikrotik-sync'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['local', 'remote'], true) ? $tab : 'local';
        $this->resetPage();
        $this->statusMessage = null;
    }

    public function syncMatched(): void
    {
        $bridge = app(IspbillingOpticalBridge::class);
        if (! $bridge->enabled()) {
            $this->statusOk = false;
            $this->statusMessage = __('ispbilling bridge is not enabled. Check ISPBILLING_* env and same-server Docker network.');

            return;
        }

        $result = $bridge->syncMatchedCustomers(500);
        $this->statusOk = true;
        $this->statusMessage = __('Synced :synced ONU(s) from ispbilling (:skipped skipped).', [
            'synced' => $result['synced'],
            'skipped' => $result['skipped'],
        ]);
        $this->tab = 'local';
        flash()->success($this->statusMessage);
    }

    public function refreshCustomer(int $onuId): void
    {
        $onu = CustomerOnu::with('customer.pppUser')->find($onuId);
        if (! $onu?->customer) {
            flash()->error(__('ONU / customer not found.'));

            return;
        }

        $synced = app(IspbillingOpticalBridge::class)->syncForCustomer($onu->customer);
        if ($synced) {
            flash()->success(__('Optical refreshed for :name', ['name' => $onu->customer->customer_name]));
        } else {
            flash()->warning(__('No matching ONU in ispbilling for this PPP user.'));
        }
    }

    public function deleteLocal(int $id): void
    {
        CustomerOnu::whereKey($id)->delete();
        flash()->success(__('Local ONU record deleted.'));
    }

    public function render()
    {
        $bridge = app(IspbillingOpticalBridge::class);
        $remote = collect();
        $local = CustomerOnu::query()->with(['customer.pppUser'])->orderByDesc('last_polled_at')->orderByDesc('id');

        if ($this->search !== '') {
            $q = '%'.$this->search.'%';
            $local->where(function ($query) use ($q) {
                $query->where('olt_name', 'like', $q)
                    ->orWhere('pon_port', 'like', $q)
                    ->orWhere('mac_address', 'like', $q)
                    ->orWhere('serial_number', 'like', $q)
                    ->orWhereHas('customer', function ($c) use ($q) {
                        $c->where('customer_name', 'like', $q)
                            ->orWhere('customer_unique_id', 'like', $q)
                            ->orWhereHas('pppUser', fn ($p) => $p->where('username', 'like', $q));
                    });
            });
        }

        if ($this->tab === 'remote') {
            $remote = $bridge->listRemoteOnus(150, $this->search !== '' ? $this->search : null);
        }

        return view('livewire.onu-manager', [
            'onus' => $local->paginate(20),
            'remoteOnus' => $remote,
            'bridgeEnabled' => $bridge->enabled(),
        ])->layout('layouts.app');
    }
}
