<?php

namespace App\Filament\Resources\SupportTicketResource\Pages\Concerns;

use App\Services\Billing\BillCollectionSearchService;
use App\Services\Support\SupportTicketWorkspaceService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Bill-collection-style subscriber search for support ticket create (reliable vs Filament Select AJAX).
 */
trait ProvidesSupportTicketCustomerSearch
{
    public string $subscriberSearch = '';

    public bool $subscriberSearching = false;

    /** @var Collection<int, array<string, mixed>> */
    public Collection $subscriberResults;

    public ?int $selectedSubscriberId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedSubscriber = null;

    public function mountSubscriberSearch(): void
    {
        $this->subscriberResults = collect();
    }

    public function mountSubscriberFromRequest(): void
    {
        $customerId = (int) request()->query('customer_id', 0);
        if ($customerId <= 0) {
            return;
        }

        $this->selectSubscriber($customerId);
        if ($this->selectedSubscriber !== null) {
            $this->subscriberSearch = (string) ($this->selectedSubscriber['customer_code'] ?? $this->selectedSubscriber['name'] ?? '');
            $this->runSubscriberSearch();
        }
    }

    public function updatedSubscriberSearch(): void
    {
        $this->runSubscriberSearch();
    }

    public function runSubscriberSearch(): void
    {
        $this->subscriberSearching = true;

        try {
            $this->subscriberResults = app(BillCollectionSearchService::class)->search($this->subscriberSearch);

            if ($this->selectedSubscriberId !== null && $this->subscriberResults->where('id', $this->selectedSubscriberId)->isEmpty()) {
                $row = app(BillCollectionSearchService::class)->find($this->selectedSubscriberId);
                if ($row === null) {
                    $this->clearSubscriberSelection();
                }
            }
        } finally {
            $this->subscriberSearching = false;
        }
    }

    public function selectSubscriber(int $customerId): void
    {
        $row = app(BillCollectionSearchService::class)->find($customerId);
        if ($row === null) {
            $this->clearSubscriberSelection();

            return;
        }

        $this->selectedSubscriberId = $customerId;
        $this->selectedSubscriber = $row;
        $this->data['customer_id'] = $customerId;
        $this->applySmartTicketDefaults($row);
    }

    public function clearSubscriberSelection(): void
    {
        $this->selectedSubscriberId = null;
        $this->selectedSubscriber = null;
        $this->data['customer_id'] = null;
    }

    public function assignTicketToMe(): void
    {
        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        $this->data['assigned_to'] = (int) $userId;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function customerPreview(): array
    {
        if ($this->selectedSubscriberId === null) {
            return ['linked' => false];
        }

        return app(SupportTicketWorkspaceService::class)->previewCustomer($this->selectedSubscriberId);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function applySmartTicketDefaults(array $row): void
    {
        $due = (float) ($row['balance_due'] ?? 0);
        $offline = ! (bool) (($row['connection']['online'] ?? false));
        $suspended = strtolower((string) ($row['status'] ?? '')) === 'suspended';

        if ($due > 0.009) {
            $this->data['issue_type'] ??= 'billing';
            $this->data['department'] ??= 'billing';
        } elseif ($offline || $suspended) {
            $this->data['issue_type'] ??= 'connection';
            $this->data['department'] ??= 'technical_support';
            if (($this->data['priority'] ?? 'medium') === 'medium') {
                $this->data['priority'] = 'high';
            }
        }

        if (blank($this->data['subject'] ?? null)) {
            $name = (string) ($row['name'] ?? 'Subscriber');
            $code = (string) ($row['customer_code'] ?? '');
            $this->data['subject'] = $code !== ''
                ? "Support — {$name} (#{$code})"
                : "Support — {$name}";
        }
    }

    protected function syncSubscriberSelectionFromForm(): void
    {
        $customerId = $this->data['customer_id'] ?? null;
        if ($customerId === null || $customerId === '') {
            $this->selectedSubscriberId = null;
            $this->selectedSubscriber = null;

            return;
        }

        $customerId = (int) $customerId;
        if ($this->selectedSubscriberId === $customerId && $this->selectedSubscriber !== null) {
            return;
        }

        $this->selectSubscriber($customerId);
    }

}
