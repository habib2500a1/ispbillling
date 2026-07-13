<?php

namespace App\Livewire;

use App\Models\Olt;
use App\Services\Olt\OltHealthProbeService;
use App\Services\Olt\OltSnmpProbeService;
use Livewire\Component;

class OltManager extends Component
{
    public string $mode = 'list'; // list|form

    public ?int $editingId = null;

    public string $name = '';

    public string $vendor = '';

    public string $olt_driver = '';

    public string $model = '';

    public string $location = '';

    public string $management_ip = '';

    public string $snmp_host = '';

    public int $snmp_port = 161;

    public string $snmp_community = '';

    public string $snmp_version = 'v2c';

    public string $status = 'active';

    public string $notes = '';

    public string $search = '';

    public ?string $lastCheckMessage = null;

    public bool $lastCheckOk = false;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['olt-management', 'mikrotik-sync'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->mode = 'form';
        $this->editingId = null;
    }

    public function edit(int $id): void
    {
        $olt = Olt::findOrFail($id);
        $this->editingId = $olt->id;
        $this->name = $olt->name;
        $this->vendor = (string) ($olt->vendor ?? '');
        $this->olt_driver = (string) ($olt->olt_driver ?? '');
        $this->model = (string) ($olt->model ?? '');
        $this->location = (string) ($olt->location ?? '');
        $this->management_ip = (string) ($olt->management_ip ?? '');
        $this->snmp_host = (string) ($olt->snmp_host ?? '');
        $this->snmp_port = (int) ($olt->snmp_port ?: 161);
        $this->snmp_community = (string) ($olt->snmp_community ?? '');
        $this->snmp_version = (string) ($olt->snmp_version ?: 'v2c');
        $this->status = (string) ($olt->status ?: 'active');
        $this->notes = (string) ($olt->notes ?? '');
        $this->mode = 'form';
        $this->lastCheckMessage = null;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->mode = 'list';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'vendor' => 'nullable|string|max:64',
            'olt_driver' => 'nullable|string|max:48',
            'model' => 'nullable|string|max:128',
            'location' => 'nullable|string|max:255',
            'management_ip' => 'nullable|string|max:64',
            'snmp_host' => 'nullable|string|max:64',
            'snmp_port' => 'required|integer|min:1|max:65535',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_version' => 'required|in:v2c',
            'status' => 'required|in:active,inactive,maintenance',
            'notes' => 'nullable|string',
        ]);

        if ($this->editingId) {
            Olt::findOrFail($this->editingId)->update($data);
            flash()->success('OLT updated.');
        } else {
            Olt::create($data);
            flash()->success('OLT created.');
        }

        $this->mode = 'list';
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Olt::findOrFail($id)->delete();
        flash()->success('OLT deleted.');
    }

    public function testSnmp(int $id, OltSnmpProbeService $probe): void
    {
        $olt = Olt::findOrFail($id);
        $result = $probe->runCheck($olt);
        $this->lastCheckOk = $result['ok'];
        $this->lastCheckMessage = $result['message'];

        if ($result['ok']) {
            flash()->success('SNMP check OK.');
        } else {
            flash()->error('SNMP check failed.');
        }
    }

    public function pollHealth(int $id, OltHealthProbeService $health): void
    {
        $olt = Olt::findOrFail($id);
        $result = $health->probeAndPersist($olt);
        $this->lastCheckOk = (bool) ($result['snmp_ok'] ?? false);
        $score = $result['health_score'] ?? 'n/a';
        $err = $result['error'] ?? null;
        $this->lastCheckMessage = $err
            ? "Health poll error: {$err}"
            : "Health score: {$score} | CPU: ".($result['cpu_percent'] ?? '-').'% | RAM: '.($result['memory_percent'] ?? '-').'% | Temp: '.($result['temperature_c'] ?? '-').'°C';

        if ($this->lastCheckOk) {
            flash()->success('Health poll completed.');
        } else {
            flash()->warning($this->lastCheckMessage);
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->vendor = '';
        $this->olt_driver = '';
        $this->model = '';
        $this->location = '';
        $this->management_ip = '';
        $this->snmp_host = '';
        $this->snmp_port = 161;
        $this->snmp_community = '';
        $this->snmp_version = 'v2c';
        $this->status = 'active';
        $this->notes = '';
        $this->lastCheckMessage = null;
        $this->lastCheckOk = false;
    }

    public function render()
    {
        $olts = Olt::query()
            ->when($this->search !== '', function ($q) {
                $s = '%'.$this->search.'%';
                $q->where(function ($q) use ($s) {
                    $q->where('name', 'like', $s)
                        ->orWhere('management_ip', 'like', $s)
                        ->orWhere('snmp_host', 'like', $s)
                        ->orWhere('vendor', 'like', $s);
                });
            })
            ->orderBy('name')
            ->get();

        $vendors = config('olt_vendors.vendors', []);
        $drivers = config('olt_drivers.drivers', []);

        return view('livewire.olt-manager', [
            'olts' => $olts,
            'vendors' => $vendors,
            'drivers' => $drivers,
            'snmpAvailable' => OltSnmpProbeService::isSnmpExtensionAvailable(),
        ])->layout('layouts.app');
    }
}
