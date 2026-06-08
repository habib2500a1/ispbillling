<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\Concerns\ProvidesSupportTicketWorkspace;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketWorkspaceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    use ProvidesSupportTicketWorkspace;

    /** @var array<string, mixed>|null */
    public ?array $ticketWorkspace = null;

    protected static string $resource = SupportTicketResource::class;

    protected static string $view = 'filament.resources.support-ticket-resource.pages.edit-support-ticket';

    public function getTitle(): string
    {
        return '';
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-support-module isp-support-ticket-edit',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'workspace' => $this->resolveTicketWorkspace(),
        ]);
    }

    public function updatedDataCustomerId(): void
    {
        $this->ticketWorkspace = null;
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->record->loadMissing([
            'customer.area',
            'customer.zone',
            'customer.package',
            'customer.mikrotikServer',
            'customer.onuDevice.olt',
            'customer.lastEndedPppSession',
            'assignee',
            'messages.user',
            'messages.customer',
            'fieldVisits.assignee',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markResolved')
                ->label('Mark resolved')
                ->color('success')
                ->visible(fn (): bool => ! in_array($this->record->status, ['resolved', 'closed'], true))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->closeConfirmMessage('Mark this ticket as resolved?'))
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
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->closeConfirmMessage('Close this ticket?'))
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

    /**
     * @return array<string, mixed>
     */
    private function resolveTicketWorkspace(): array
    {
        $customerId = $this->data['customer_id'] ?? $this->record->customer_id;
        $cacheKey = $this->record->getKey().':'.(string) $customerId;

        if (
            $this->ticketWorkspace !== null
            && ($this->ticketWorkspace['_cache_key'] ?? null) === $cacheKey
        ) {
            return $this->ticketWorkspace;
        }

        $this->ticketWorkspace = array_merge(
            $this->workspaceService()->buildViewBundle($this->record, $customerId),
            ['_cache_key' => $cacheKey],
        );

        return $this->ticketWorkspace;
    }

    private function workspaceService(): SupportTicketWorkspaceService
    {
        return app(SupportTicketWorkspaceService::class);
    }

    private function closeConfirmMessage(string $default): string
    {
        $live = $this->resolveTicketWorkspace()['live'];

        return $this->workspaceService()->closeOfflineNotice($live) ?? $default;
    }
}
