<?php

namespace App\Livewire;

use App\Services\Billing\BillingNoticesService;
use Livewire\Component;

class BillingNotices extends Component
{
    public int $dueSoonDays = 3;

    public string $activeSection = 'all';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['payment-collection', 'amount-collection-report', 'billing-notices'])) {
            abort(403, 'Unauthorized action.');
        }

        $days = (int) request()->query('days', 3);
        $this->dueSoonDays = max(1, min(14, $days));
    }

    public function updatedDueSoonDays(): void
    {
        $this->dueSoonDays = max(1, min(14, (int) $this->dueSoonDays));
    }

    public function setSection(string $key): void
    {
        $this->activeSection = $key;
    }

    public function render()
    {
        $payload = app(BillingNoticesService::class)->payload($this->dueSoonDays, 50);

        $sections = $payload['sections'];
        if ($this->activeSection !== 'all') {
            $sections = array_values(array_filter(
                $sections,
                fn (array $s): bool => ($s['key'] ?? '') === $this->activeSection
            ));
        }

        return view('livewire.billing-notices', [
            'summary' => $payload['summary'],
            'sections' => $sections,
            'updatedAt' => $payload['updated_at'],
        ])->layout('layouts.app');
    }
}
