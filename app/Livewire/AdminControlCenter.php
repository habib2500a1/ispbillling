<?php

namespace App\Livewire;

use App\Services\Admin\AdminControlService;
use Livewire\Component;

class AdminControlCenter extends Component
{
    public ?string $maintenanceOutput = null;

    public bool $maintenanceOk = false;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['site-settings'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function runMaintenance(): void
    {
        $result = app(AdminControlService::class)->runFullMaintenance();
        $this->maintenanceOutput = $result['output'];
        $this->maintenanceOk = $result['ok'];

        flash()->{$result['ok'] ? 'success' : 'warning'}(
            $result['ok']
                ? __('System maintenance completed successfully.')
                : __('Maintenance finished with some warnings — see details below.'),
        );
    }

    public function render()
    {
        $payload = app(AdminControlService::class)->payload();

        return view('livewire.admin-control-center', $payload)
            ->layout('layouts.app');
    }
}
