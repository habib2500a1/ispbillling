<?php

namespace App\Livewire;

use App\Support\FeatureModuleRegistry;
use Livewire\Component;

class GroupHub extends Component
{
    public string $group;

    public function mount(string $group): void
    {
        if (! hasAccess(['Super Admin'], ['dashboard'])) {
            abort(403, 'Unauthorized action.');
        }

        $resolved = FeatureModuleRegistry::groupFromSlug($group);
        if ($resolved === null) {
            abort(404);
        }

        $this->group = $resolved;
    }

    public function render()
    {
        return view('livewire.group-hub', [
            'group' => $this->group,
            'modules' => FeatureModuleRegistry::forGroup($this->group),
        ])->layout('layouts.app');
    }
}
