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

        $this->applySubscriberSelection($customerId, notify: false);

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
        $query = $this->searchTermsForQuery(trim($this->subscriberSearch));

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
        if (! $this->applySubscriberSelection($customerId, notify: true)) {
            return;
        }

        $row = $this->selectedSubscriber;
        Notification::make()
            ->title('Subscriber linked')
            ->body($row['name'].' (#'.($row['customer_code'] ?? $customerId).')')
            ->success()
            ->send();
    }

    public function selectSubscriberFromTypedQuery(?string $query = null): bool
    {
        $query = trim($query ?? $this->subscriberSearch);
        if (mb_strlen($query) < 2) {
            return false;
        }

        $this->subscriberSearch = $query;
        $results = app(BillCollectionSearchService::class)->search($this->searchTermsForQuery($query), 25);

        if ($results->isEmpty()) {
            return false;
        }

        $match = $this->matchSubscriberRow($query, $results);

        if ($match === null) {
            return false;
        }

        return $this->applySubscriberSelection((int) $match['id'], notify: false);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>|list<array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    protected function matchSubscriberRow(string $query, $results): ?array
    {
        $normalizedQuery = mb_strtolower(trim($query));

        foreach ($results as $row) {
            if (mb_strtolower($this->subscriberLabel($row)) === $normalizedQuery) {
                return $row;
            }
        }

        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/u', $query, $m)) {
            $userPart = mb_strtolower(trim($m[1]));
            $codePart = mb_strtolower(trim($m[2]));

            foreach ($results as $row) {
                $code = mb_strtolower((string) ($row['customer_code'] ?? ''));
                $user = mb_strtolower((string) ($row['username'] ?? ''));
                $name = mb_strtolower((string) ($row['name'] ?? ''));

                if ($code === $codePart && ($user === $userPart || $name === $userPart || str_contains($user, $userPart))) {
                    return $row;
                }
            }

            foreach ($results as $row) {
                if (mb_strtolower((string) ($row['customer_code'] ?? '')) === $codePart) {
                    return $row;
                }
            }
        }

        foreach ($results as $row) {
            $candidates = array_filter([
                mb_strtolower((string) ($row['username'] ?? '')),
                mb_strtolower((string) ($row['customer_code'] ?? '')),
                mb_strtolower((string) ($row['phone'] ?? '')),
            ]);

            if (in_array($normalizedQuery, $candidates, true)) {
                return $row;
            }
        }

        if ($results->count() === 1) {
            return $results->first();
        }

        return null;
    }

    protected function searchTermsForQuery(string $query): string
    {
        if (preg_match('/\(([^)]+)\)\s*$/u', $query, $m)) {
            $code = trim($m[1]);
            if (mb_strlen($code) >= 2) {
                return $code;
            }
        }

        return $query;
    }

    protected function subscriberLabel(array $row): string
    {
        $user = (string) (($row['username'] ?? '') !== '' ? $row['username'] : ($row['name'] ?? 'Subscriber'));

        return $user.' ('.($row['customer_code'] ?? $row['id'] ?? '').')';
    }

    protected function applySubscriberSelection(int $customerId, bool $notify = false): bool
    {
        $row = app(BillCollectionSearchService::class)->find($customerId);
        if ($row === null) {
            $this->clearSubscriberSelection();
            if ($notify) {
                Notification::make()
                    ->title('Subscriber not found')
                    ->danger()
                    ->send();
            }

            return false;
        }

        $this->selectedSubscriberId = $customerId;
        $this->selectedSubscriber = $row;
        $this->subscriberSearch = $this->subscriberLabel($row);
        $this->data['customer_id'] = $customerId;
        $this->applySmartTicketDefaults($row);
        $this->form->fill($this->data);

        return true;
    }

    public function clearSubscriberSelection(): void
    {
        $this->selectedSubscriberId = null;
        $this->selectedSubscriber = null;
        $this->data['customer_id'] = null;
        $this->form->fill($this->data);
        $this->dispatch('subscriber-search-reset');
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
