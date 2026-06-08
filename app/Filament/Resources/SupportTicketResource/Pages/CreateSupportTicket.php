<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\Concerns\ProvidesSupportTicketCustomerSearch;
use Filament\Forms\Form;
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

    public function getSubheading(): ?string
    {
        return 'Pick subscriber → check PPP/ONU status → assign technician → save.';
    }

    public function mount(): void
    {
        parent::mount();
        $this->mountSubscriberSearch();
    }

    public function form(Form $form): Form
    {
        return SupportTicketResource::form($form, useSubscriberSearchPicker: true);
    }
}
