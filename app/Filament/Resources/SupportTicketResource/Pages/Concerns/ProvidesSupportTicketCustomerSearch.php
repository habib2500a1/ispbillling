<?php

namespace App\Filament\Resources\SupportTicketResource\Pages\Concerns;

use App\Services\Billing\BillCollectionSearchService;
use App\Services\Support\SupportTicketWorkspaceService;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;

/**
 * Bill-collection-style subscriber search for support ticket create (reliable vs Filament Select AJAX).
 */
trait ProvidesSupportTicketCustomerSearch
{
    public string $subscriberSearch = '';

    public bool $subscriberSearching = false;

    /** @var list<array<string, mixed>> */
    public array $subscriberResults = [];

    public ?int $selectedSubscriberId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedSubscriber = null;

    /**
     * @return array<string, mixed>
     */
    protected function queryStringWithSubscriberSearch(): array
    {
        return [
            'subscriberSearch' => ['except' => '', 'as' => 'q'],
        ];
    }

    public function mountSubscriberSearch(): void
    {
        $this->subscriberResults = [];

        $q = trim((string) request()->query('q', $this->subscriberSearch));
        $this->subscriberSearch = $q;

        if (mb_strlen($q) >= 2) {
            $this->runSubscriberSearch();
        }
    }

    public function mountSubscriberFromRequest(): void
    {
        $customerId = (int) request()->query('customer_id', 0);
        if ($customerId <= 0) {
            return;
        }

        $this->selectSubscriber($customerId);

        $q = trim((string) request()->query('q', ''));
        if ($q !== '') {
            $this->subscriberSearch = $q;
            $this->runSubscriberSearch();
        } elseif ($this->selectedSubscriber !== null) {
            $this->subscriberSearch = (string) ($this->selectedSubscriber['customer_code'] ?? $this->selectedSubscriber['name'] ?? '');
            $this->runSubscriberSearch();
        }
    }

    public function pickSubscriberUrl(int $customerId): string
    {
        $params = array_filter([
            'q' => trim($this->subscriberSearch) !== '' ? trim($this->subscriberSearch) : null,
            'customer_id' => $customerId,
        ]);

        $url = \App\Filament\Resources\SupportTicketResource::getUrl('create');

        return $params === [] ? $url : $url.'?'.http_build_query($params);
    }

    public function createPageUrl(?string $query = null): string
    {
        $q = $query ?? trim($this->subscriberSearch);
        $url = \App\Filament\Resources\SupportTicketResource::getUrl('create');

        return $q !== '' && $q !== null ? $url.'?'.http_build_query(['q' => $q]) : $url;
    }

    public function updatedSubscriberSearch(): void
    {
        $this->runSubscriberSearch();
    }

    public function clearSubscriberSearch(): void
    {
        $this->subscriberSearch = '';
        $this->subscriberResults = [];
    }

    public function runSubscriberSearch(): void
    {
        $query = trim($this->subscriberSearch);

        if (mb_strlen($query) < 2) {
            $this->subscriberResults = [];

            return;
        }

        $this->subscriberSearching = true;

        try {
            $this->subscriberResults = app(BillCollectionSearchService::class)
                ->search($query)
                ->values()
                ->all();

            if ($this->selectedSubscriberId !== null && ! collect($this->subscriberResults)->contains(fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $this->selectedSubscriberId)) {
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
            Notification::make()
                ->title('Subscriber not found')
                ->danger()
                ->send();

            return;
        }

        $this->selectedSubscriberId = $customerId;
        $this->selectedSubscriber = $row;
        $this->data['customer_id'] = $customerId;
        $this->applySmartTicketDefaults($row);
        $this->form->fill($this->data);

        Notification::make()
            ->title('Subscriber linked')
            ->body($row['name'].' (#'.($row['customer_code'] ?? $customerId).')')
            ->success()
            ->send();
    }

    public function clearSubscriberSelection(): void
    {
        $this->selectedSubscriberId = null;
        $this->selectedSubscriber = null;
        $this->data['customer_id'] = null;
        $this->form->fill($this->data);
    }

    public function assignTicketToMe(): void
    {
        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        $this->data['assigned_to'] = (int) $userId;
        $this->form->fill($this->data);
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
