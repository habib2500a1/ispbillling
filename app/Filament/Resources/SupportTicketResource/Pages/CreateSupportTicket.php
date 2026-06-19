<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Pages\SupportHub;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\Concerns\ProvidesSupportTicketCustomerSearch;
use App\Services\Support\SupportSlaService;
use App\Services\Support\SupportTicketIntelligenceService;
use App\Support\SupportCategories;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

class CreateSupportTicket extends CreateRecord
{
    use ProvidesSupportTicketCustomerSearch;

    protected static string $resource = SupportTicketResource::class;

    protected static string $view = 'filament.resources.support-ticket-resource.pages.create-support-ticket';

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-support-module isp-support-ticket-create',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', panel: 'admin', parameters: [
            'activeTab' => 'all',
        ]);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create ticket')
            ->submit('createTicket');
    }

    public function createTicket(): void
    {
        $this->selectSubscriberFromTypedQuery(trim($this->subscriberSearch));
        $this->syncCustomerIdFromSelection();

        if (! filled($this->data['customer_id'] ?? null)) {
            Notification::make()
                ->title('Link a subscriber first')
                ->body('Search on the left, then tap a result to link before creating the ticket.')
                ->warning()
                ->send();

            return;
        }

        foreach ([
            'department' => 'technical_support',
            'channel' => 'call_center',
            'priority' => 'medium',
            'status' => 'open',
        ] as $field => $default) {
            if (blank($this->data[$field] ?? null)) {
                $this->data[$field] = $default;
            }
        }

        if (blank($this->data['issue_type'] ?? null)) {
            $this->applySmartDefaultsFromSelection();
        }

        if (blank($this->data['subject'] ?? null) && $this->selectedSubscriber !== null) {
            $name = (string) ($this->selectedSubscriber['name'] ?? 'Subscriber');
            $code = (string) ($this->selectedSubscriber['customer_code'] ?? '');
            $issue = SupportCategories::label($this->data['issue_type'] ?? null);
            $this->data['subject'] = $code !== ''
                ? "{$issue} — {$name} (#{$code})"
                : "{$issue} — {$name}";
        }

        $this->form->fill($this->data);

        try {
            $this->create();
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    protected function applySmartDefaultsFromSelection(): void
    {
        if ($this->selectedSubscriber === null) {
            return;
        }

        $this->applySmartTicketDefaults($this->selectedSubscriber);
    }

    protected function syncCustomerIdFromSelection(): void
    {
        if ($this->selectedSubscriberId === null) {
            return;
        }

        $this->data['customer_id'] = $this->selectedSubscriberId;
        $this->form->fill($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['customer_id']) && $this->selectedSubscriberId !== null) {
            $data['customer_id'] = $this->selectedSubscriberId;
        }

        if (empty($data['customer_id'])) {
            throw ValidationException::withMessages([
                'data.customer_id' => 'Pick a subscriber from the search dropdown before saving.',
            ]);
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        if (empty($data['customer_id'])) {
            $this->selectSubscriberFromTypedQuery();
        }

        if (empty($data['customer_id']) && $this->selectedSubscriberId !== null) {
            $data['customer_id'] = $this->selectedSubscriberId;
        }

        return parent::handleRecordCreation($data);
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function pickCategory(string $issueType): void
    {
        $this->data['issue_type'] = $issueType;
        $customer = filled($this->data['customer_id'] ?? null)
            ? \App\Models\Customer::query()->find($this->data['customer_id'])
            : null;

        $this->data['priority'] = SupportCategories::defaultPriority($issueType, $customer);
        $this->data['department'] = SupportCategories::groupLabel($issueType) === 'Billing'
            ? 'billing'
            : 'technical_support';

        $this->form->fill($this->data);
    }

    /**
     * @return list<array{group: string, group_key: string, key: string, label: string, default_priority: string}>
     */
    public function getCategoryPickerItems(): array
    {
        return SupportCategories::allItems();
    }

    /**
     * @return list<array{label: string, detail: string, tone: string}>
     */
    #[Computed]
    public function createAiSuggestions(): array
    {
        if ($this->selectedSubscriberId === null) {
            return [];
        }

        $customer = \App\Models\Customer::query()->find($this->selectedSubscriberId);

        return app(SupportTicketIntelligenceService::class)->analyze(
            $customer,
            (string) ($this->data['description'] ?? ''),
            null,
        );
    }

    public function updatedDataDescription(): void
    {
        unset($this->createAiSuggestions);
    }

    public function updatedDataIssueType(): void
    {
        $issueType = (string) ($this->data['issue_type'] ?? '');
        if ($issueType === '') {
            return;
        }

        $customer = filled($this->data['customer_id'] ?? null)
            ? \App\Models\Customer::query()->find($this->data['customer_id'])
            : null;

        $this->data['priority'] = SupportCategories::defaultPriority($issueType, $customer);
        $this->form->fill($this->data);
    }

    protected function afterCreate(): void
    {
        session()->flash('support_ticket_created', $this->record->ticket_number);

        $attachment = $this->data['create_attachment'] ?? null;
        if (filled($attachment)) {
            $paths = is_array($attachment) ? $attachment : [$attachment];
            foreach ($paths as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }
                \App\Models\SupportTicketUpload::query()->create([
                    'tenant_id' => $this->record->tenant_id,
                    'support_ticket_id' => $this->record->id,
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => basename($path),
                    'size' => (int) (\Illuminate\Support\Facades\Storage::disk('public')->size($path) ?: 0),
                ]);
            }
        }

        $this->redirect($this->getRedirectUrl(), navigate: false);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Ticket created')
            ->body($this->record->ticket_number.' is now in the queue.');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }

    public function getTitle(): string
    {
        return 'New support ticket';
    }

    public function getSubheading(): ?string
    {
        return 'Search subscriber → review live status → assign technician → save to queue.';
    }

    public function mount(): void
    {
        parent::mount();
        $this->mountSubscriberSearch();
        $this->mountSubscriberFromRequest();
    }

    /**
     * @return array<string, mixed>
     */
    protected function queryString(): array
    {
        return array_merge(
            $this->queryStringWithSubscriberSearch(),
            [],
        );
    }

    public function form(Form $form): Form
    {
        return SupportTicketResource::form($form, useSubscriberSearchPicker: true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assignToMe')
                ->label('Assign to me')
                ->icon('heroicon-o-user-circle')
                ->color('gray')
                ->action(function (): void {
                    $this->assignTicketToMe();
                    Notification::make()
                        ->title('Assigned to you')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('queue')
                ->label('Ticket queue')
                ->icon('heroicon-o-queue-list')
                ->url(SupportTicketResource::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('hub')
                ->label('Support center')
                ->icon('heroicon-o-lifebuoy')
                ->url(SupportHub::getUrl())
                ->color('gray'),
        ];
    }

    public function slaPreviewLabel(): string
    {
        $priority = (string) ($this->data['priority'] ?? 'medium');
        $customerId = $this->data['customer_id'] ?? $this->selectedSubscriberId;
        $customer = filled($customerId) ? \App\Models\Customer::query()->find($customerId) : null;
        $sla = app(SupportSlaService::class);
        $code = match ($priority) {
            'critical' => 'P1',
            'high' => 'P2',
            'medium' => 'P3',
            'low' => 'P4',
            default => 'P3',
        };

        return $code.' · '.$sla->previewLabel($customer, $priority);
    }

    public function priorityCodeLabel(): string
    {
        $priority = (string) ($this->data['priority'] ?? 'medium');

        return match ($priority) {
            'critical' => 'P1 Critical',
            'high' => 'P2 High',
            'medium' => 'P3 Medium',
            'low' => 'P4 Low',
            default => 'P3 Medium',
        };
    }

    public function canSaveTicket(): bool
    {
        return filled($this->data['customer_id'] ?? null)
            || $this->selectedSubscriberId !== null;
    }
}
