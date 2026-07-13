<?php

namespace App\Livewire;

use App\Support\FeatureModuleRegistry;
use Livewire\Component;

class IspOsHub extends Component
{
    public string $search = '';

    public ?string $groupFilter = null;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['dashboard'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function setGroup(?string $group): void
    {
        $this->groupFilter = $group;
    }

    public function render()
    {
        $modules = FeatureModuleRegistry::all();
        $groups = FeatureModuleRegistry::groups();

        if ($this->groupFilter) {
            $modules = array_values(array_filter($modules, fn (array $m): bool => $m['group'] === $this->groupFilter));
        }

        if ($this->search !== '') {
            $q = strtolower($this->search);
            $modules = array_values(array_filter($modules, function (array $m) use ($q): bool {
                return str_contains(strtolower($m['label']), $q)
                    || str_contains(strtolower($m['description']), $q)
                    || str_contains(strtolower($m['group']), $q);
            }));
        }

        return view('livewire.isp-os-hub', [
            'modules' => $modules,
            'groups' => $groups,
            'total' => count(FeatureModuleRegistry::all()),
        ])->layout('layouts.app');
    }
}
