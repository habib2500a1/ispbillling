<?php

namespace App\Livewire;

use App\Models\CustomerOnu;
use App\Models\CustomersInfo;
use App\Models\Olt;
use App\Services\Olt\CustomerOpticalPresenter;
use App\Services\Olt\IspbillingOpticalBridge;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class OnuManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $tab = 'local';

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $customer_id = null;

    public ?int $olt_id = null;

    public string $pon_port = '';

    public string $mac_address = '';

    public string $serial_number = '';

    public string $rx_power_dbm = '';

    public string $tx_power_dbm = '';

    public string $oper_status = 'online';

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
        $this->showForm = false;
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->statusMessage = null;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function saveOnu(CustomerOpticalPresenter $optical): void
    {
        $data = $this->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers_infos', 'id')],
            'olt_id' => ['nullable', 'integer', Rule::exists('olts', 'id')],
            'pon_port' => ['nullable', 'string', 'max:64'],
            'mac_address' => ['nullable', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:64'],
            'rx_power_dbm' => ['nullable', 'numeric'],
            'tx_power_dbm' => ['nullable', 'numeric'],
            'oper_status' => ['nullable', 'string', 'max:32'],
        ]);

        $customer = CustomersInfo::query()->findOrFail((int) $data['customer_id']);
        $olt = ! empty($data['olt_id']) ? Olt::query()->find((int) $data['olt_id']) : null;

        $optical->saveManual($customer, [
            'olt_name' => $olt?->name,
            'pon_port' => $data['pon_port'] ?: null,
            'mac_address' => $data['mac_address'] ?: null,
            'serial_number' => $data['serial_number'] ?: null,
            'rx_power_dbm' => $data['rx_power_dbm'] !== '' ? $data['rx_power_dbm'] : null,
            'tx_power_dbm' => $data['tx_power_dbm'] !== '' ? $data['tx_power_dbm'] : null,
            'oper_status' => $data['oper_status'] ?: 'online',
        ]);

        if ($olt) {
            $onu = $customer->primaryOnu();
            if ($onu) {
                $onu->olt_id = $olt->id;
                $onu->save();
            }
        }

        $this->resetForm();
        $this->showForm = false;
        flash()->success(__('ONU linked to :name.', ['name' => $customer->customer_name]));
    }

    public function syncMatched(): void
    {
        $bridge = app(IspbillingOpticalBridge::class);
        if (! $bridge->enabled()) {
            $this->statusOk = false;
            $this->statusMessage = __('This panel is the billing system. Add ONU here or on the customer page — no second ispbilling server is required.');

            return;
        }

        $result = $bridge->syncMatchedCustomers(500);
        $this->statusOk = true;
        $this->statusMessage = __('Synced :synced ONU(s).', [
            'synced' => $result['synced'],
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

        $bridge = app(IspbillingOpticalBridge::class);
        if ($bridge->enabled() && $bridge->syncForCustomer($onu->customer)) {
            flash()->success(__('Optical refreshed for :name', ['name' => $onu->customer->customer_name]));

            return;
        }

        flash()->warning(__('Refresh needs a live OLT poll or an updated manual RX/TX on the customer page.'));
    }

    public function deleteLocal(int $id): void
    {
        CustomerOnu::whereKey($id)->delete();
        flash()->success(__('ONU record deleted.'));
    }

    public function render()
    {
        $bridge = app(IspbillingOpticalBridge::class);
        $remote = collect();
        $local = CustomerOnu::query()->with(['customer.pppUser', 'olt'])->orderByDesc('last_polled_at')->orderByDesc('id');

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

        if ($this->tab === 'remote' && $bridge->enabled()) {
            $remote = $bridge->listRemoteOnus(150, $this->search !== '' ? $this->search : null);
        }

        return view('livewire.onu-manager', [
            'onus' => $local->paginate(20),
            'remoteOnus' => $remote,
            'bridgeEnabled' => $bridge->enabled(),
            'olts' => Olt::query()->orderBy('name')->get(['id', 'name', 'management_ip']),
            'customers' => CustomersInfo::query()->orderBy('customer_name')->limit(400)->get(['id', 'customer_name', 'customer_unique_id']),
            'oltCount' => Olt::query()->count(),
            'customerCount' => CustomersInfo::query()->count(),
        ])->layout('layouts.app');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->customer_id = null;
        $this->olt_id = null;
        $this->pon_port = '';
        $this->mac_address = '';
        $this->serial_number = '';
        $this->rx_power_dbm = '';
        $this->tx_power_dbm = '';
        $this->oper_status = 'online';
        $this->resetValidation();
    }
}
