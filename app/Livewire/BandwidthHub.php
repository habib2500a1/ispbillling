<?php

namespace App\Livewire;

use App\Services\Bandwidth\BandwidthHubService;
use Livewire\Component;

class BandwidthHub extends Component
{
    public string $selectedRouter = '';

    public string $selectedInterface = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup', 'mikrotik-sync', 'olt-management'])) {
            abort(403, 'Unauthorized action.');
        }

        $payload = app(BandwidthHubService::class)->payload();
        $this->selectedRouter = (string) ($payload['selected_router'] ?? '');
        $this->selectedInterface = (string) ($payload['selected_interface'] ?? '');
    }

    public function updatedSelectedRouter(): void
    {
        $this->selectedInterface = '';
    }

    public function poll(): void
    {
        // re-render pulls fresh live tick via render()
    }

    public function refresh(): void
    {
        flash()->success(__('Bandwidth hub refreshed.'));
    }

    public function render()
    {
        $data = app(BandwidthHubService::class)->payload(
            $this->selectedRouter ?: null,
            $this->selectedInterface ?: null
        );

        // Keep selection in sync when service auto-picks interface
        if (! empty($data['selected_router'])) {
            $this->selectedRouter = (string) $data['selected_router'];
        }
        if (! empty($data['selected_interface'])) {
            $this->selectedInterface = (string) $data['selected_interface'];
        }

        return view('livewire.bandwidth-hub', $data)->layout('layouts.app');
    }
}
