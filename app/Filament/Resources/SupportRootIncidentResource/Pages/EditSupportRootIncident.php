<?php

namespace App\Filament\Resources\SupportRootIncidentResource\Pages;

use App\Filament\Resources\SupportRootIncidentResource;
use App\Filament\Resources\SupportTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupportRootIncident extends EditRecord
{
    protected static string $resource = SupportRootIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resolveIncident')
                ->label('Resolve incident')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                    ]);
                    $this->refreshFormData(['status', 'resolved_at']);
                }),
            Actions\Action::make('openPrimary')
                ->label('Open primary ticket')
                ->icon('heroicon-o-ticket')
                ->url(fn (): ?string => $this->record->primary_ticket_id
                    ? SupportTicketResource::getUrl('edit', ['record' => $this->record->primaryTicket?->ticket_number ?? $this->record->primary_ticket_id])
                    : null)
                ->visible(fn (): bool => $this->record->primary_ticket_id !== null),
            Actions\DeleteAction::make(),
        ];
    }
}
