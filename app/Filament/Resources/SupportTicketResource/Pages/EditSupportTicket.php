<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->record->loadMissing(['customer.area']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markResolved')
                ->label('Mark resolved')
                ->color('success')
                ->visible(fn (): bool => ! in_array($this->record->status, ['resolved', 'closed'], true))
                ->action(function (): void {
                    /** @var SupportTicket $record */
                    $record = $this->record;
                    $record->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                    ]);
                    Notification::make()->title('Marked resolved')->success()->send();
                    $this->redirect(SupportTicketResource::getUrl('edit', ['record' => $record]));
                }),
            Actions\Action::make('markClosed')
                ->label('Close')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status !== 'closed')
                ->action(function (): void {
                    /** @var SupportTicket $record */
                    $record = $this->record;
                    $record->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);
                    Notification::make()->title('Ticket closed')->success()->send();
                    $this->redirect(SupportTicketResource::getUrl('edit', ['record' => $record]));
                }),
            Actions\Action::make('reopen')
                ->label('Reopen')
                ->visible(fn (): bool => in_array($this->record->status, ['resolved', 'closed'], true))
                ->action(function (): void {
                    /** @var SupportTicket $record */
                    $record = $this->record;
                    $record->update([
                        'status' => 'open',
                        'resolved_at' => null,
                        'closed_at' => null,
                    ]);
                    Notification::make()->title('Ticket reopened')->success()->send();
                    $this->redirect(SupportTicketResource::getUrl('edit', ['record' => $record]));
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
