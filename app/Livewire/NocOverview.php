<?php

namespace App\Livewire;

use App\Services\Noc\NocOverviewService;
use Livewire\Component;

class NocOverview extends Component
{
    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['manage-tickets', 'view-tickets', 'olt-management', 'mikrotik-sync'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function refresh(): void
    {
        // Livewire re-render picks fresh payload
        flash()->success(__('NOC view refreshed.'));
    }

    public function render()
    {
        $data = app(NocOverviewService::class)->payload();

        return view('livewire.noc-overview', $data)->layout('layouts.app');
    }
}
