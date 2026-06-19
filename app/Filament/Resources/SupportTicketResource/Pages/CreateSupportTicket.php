<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Pages\SupportHub;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\Concerns\ProvidesSupportTicketCustomerSearch;
use App\Models\SupportTicket;
use App\Services\Support\SupportSlaService;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

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
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Ticket created — opening workspace';
    }

    public function slaPreviewLabel(): string
    {
        $priority = (string) ($this->data['priority'] ?? 'medium');
        $customerId = $this->data['customer_id'] ?? null;
        $customer = filled($customerId) ? \App\Models\Customer::query()->find($customerId) : null;

        return app(SupportSlaService::class)->previewLabel($customer, $priority);
    }

    public function canSaveTicket(): bool
    {
        return filled($this->data['customer_id'] ?? null);
    }
}
