<?php

namespace App\Livewire;

use App\Services\Features\FeatureModuleService;
use App\Support\FeatureModuleRegistry;
use Livewire\Component;

class FeatureModulePage extends Component
{
    public string $module = '';

    public function mount(string $module): void
    {
        $def = FeatureModuleRegistry::find($module);
        if ($def === null) {
            abort(404);
        }

        if (($def['slug'] ?? '') === 'saas-sell' && ! canSellSaas()) {
            abort(403, 'Only the platform owner can sell ISP admin access.');
        }

        if (! empty($def['route'])) {
            $this->redirect(FeatureModuleRegistry::url($def));

            return;
        }

        if (! hasAccess(['Super Admin'], ['dashboard'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->module = $module;
    }

    public function render()
    {
        $payload = app(FeatureModuleService::class)->payload($this->module);

        return view('livewire.feature-module-page', $payload)->layout('layouts.app');
    }
}
