<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Pages\SupportHub;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\Concerns\ProvidesSupportTicketCustomerSearch;
use App\Services\Support\SupportSlaService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

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
            ->before(function (): void {
                $this->selectSubscriberFromTypedQuery();
                $this->syncCustomerIdFromSelection();

                if (! filled($this->data['customer_id'] ?? null)) {
                    Notification::make()
                        ->title('Subscriber not found')
                        ->body('Type username (ID) like habib3.kp (0603) or pick from the list.')
                        ->warning()
                        ->send();

                    throw ValidationException::withMessages([
                        'data.customer_id' => 'Click a subscriber in the dropdown before saving.',
                    ]);
                }
            });
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

        return static::getModel()::create($data);
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

        return app(SupportSlaService::class)->previewLabel($customer, $priority);
    }

    public function canSaveTicket(): bool
    {
        return filled($this->data['customer_id'] ?? null)
            || $this->selectedSubscriberId !== null;
    }
}
