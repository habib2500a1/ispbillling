<?php

namespace App\Filament\Resources\SupportTicketResource\Pages\Concerns;

use App\Services\Billing\BillCollectionSearchService;
use Illuminate\Support\Collection;

/**
 * Bill-collection-style subscriber search for support ticket create (reliable vs Filament Select AJAX).
 */
trait ProvidesSupportTicketCustomerSearch
{
    public string $subscriberSearch = '';

    /** @var Collection<int, array<string, mixed>> */
    public Collection $subscriberResults;

    public ?int $selectedSubscriberId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedSubscriber = null;

    public function mountSubscriberSearch(): void
    {
        $this->subscriberResults = collect();
    }

    public function updatedSubscriberSearch(): void
    {
        $this->runSubscriberSearch();
    }

    public function runSubscriberSearch(): void
    {
        $this->subscriberResults = app(BillCollectionSearchService::class)->search($this->subscriberSearch);

        if ($this->selectedSubscriberId !== null && $this->subscriberResults->where('id', $this->selectedSubscriberId)->isEmpty()) {
            $row = app(BillCollectionSearchService::class)->find($this->selectedSubscriberId);
            if ($row === null) {
                $this->clearSubscriberSelection();
            }
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
    }

    public function clearSubscriberSelection(): void
    {
        $this->selectedSubscriberId = null;
        $this->selectedSubscriber = null;
        $this->data['customer_id'] = null;
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
