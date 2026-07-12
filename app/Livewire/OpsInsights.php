<?php

namespace App\Livewire;

use App\Services\Ai\OpsInsightsService;
use Livewire\Component;

class OpsInsights extends Component
{
    public string $severityFilter = 'all';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-sync', 'olt-management', 'manage-tickets', 'payment-collection'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function setSeverity(string $severity): void
    {
        $this->severityFilter = $severity;
    }

    public function refresh(): void
    {
        flash()->success(__('Insights refreshed.'));
    }

    public function publishDigest(): void
    {
        try {
            $log = app(OpsInsightsService::class)->publishDigest();
            flash()->success(__('Digest saved to notifications: :title', ['title' => $log->title]));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function render()
    {
        $payload = app(OpsInsightsService::class)->payload();
        $insights = $payload['insights'];

        if ($this->severityFilter !== 'all') {
            $insights = array_values(array_filter(
                $insights,
                fn (array $row): bool => ($row['severity'] ?? '') === $this->severityFilter
            ));
        }

        return view('livewire.ops-insights', [
            'digest' => $payload['digest'],
            'summary' => $payload['summary'],
            'counts' => $payload['counts'],
            'insights' => $insights,
            'updatedAt' => $payload['updated_at'],
        ])->layout('layouts.app');
    }
}
