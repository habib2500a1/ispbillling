<?php

namespace App\Livewire;

use App\Services\IspOs\IspOsConsoleService;
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
        $all = array_values(array_filter(
            FeatureModuleRegistry::all(),
            fn (array $m): bool => ($m['slug'] ?? '') !== 'isp-os-center'
        ));
        $groups = FeatureModuleRegistry::groups();
        $modules = $all;

        if ($this->groupFilter) {
            $modules = array_values(array_filter($modules, fn (array $m): bool => $m['group'] === $this->groupFilter));
        }

        if ($this->search !== '') {
            $q = strtolower($this->search);
            $modules = array_values(array_filter($modules, function (array $m) use ($q): bool {
                return str_contains(strtolower($m['label']), $q)
                    || str_contains(strtolower($m['description']), $q)
                    || str_contains(strtolower($m['group']), $q)
                    || str_contains(strtolower((string) ($m['section'] ?? '')), $q);
            }));
        }

        $grouped = [];
        foreach ($modules as $mod) {
            $grouped[$mod['group']][] = $mod;
        }

        return view('livewire.isp-os-hub', [
            'modules' => $modules,
            'grouped' => $grouped,
            'groups' => $groups,
            'total' => count($all),
            'ops' => app(IspOsConsoleService::class)->snapshot(),
            'groupFilter' => $this->groupFilter,
        ])->layout('layouts.app');
    }
}
