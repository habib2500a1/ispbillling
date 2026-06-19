<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\Concerns\ProvidesSupportTicketWorkspace;
use App\Models\SupportTicket;
use App\Services\Support\SupportSlaService;
use App\Services\Support\SupportTicketWorkspaceService;
use App\Support\SupportPanelAccess;
use Filament\Actions;
use Filament\Forms;
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
        $workspace = $this->isFormSelectAjaxRequest()
            ? ($this->ticketWorkspace ?? $this->emptyWorkspace())
            : $this->resolveTicketWorkspace();

        return array_merge(parent::getViewData(), [
            'workspace' => $workspace,
        ]);
    }

    private function isFormSelectAjaxRequest(): bool
    {
        if (! request()->hasHeader('X-Livewire')) {
            return false;
        }

        $calls = request()->input('components.0.calls', []);
        if (! is_array($calls)) {
            return false;
        }

        foreach ($calls as $call) {
            $method = is_array($call) ? ($call['method'] ?? '') : '';
            if (is_string($method) && str_contains($method, 'getFormSelect')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyWorkspace(): array
    {
        return [
            'c360' => ['linked' => false],
            'timeline' => [],
            'hints' => [],
            'ai_suggestions' => [],
            'gis' => ['available' => false],
            'network' => [],
            'live' => ['linked' => false],
            'close_offline_notice' => null,
            'assignment' => ['assigned' => false, 'name' => 'Unassigned'],
        ];
    }

    public function updatedDataCustomerId(): void
    {
        $this->ticketWorkspace = null;
    }

    public function updatedDataAssignedTo(): void
    {
        $this->ticketWorkspace = null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (filled($data['assigned_to'] ?? null)) {
            $data['assigned_to'] = (string) $data['assigned_to'];
        }

        return $data;
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
            'rootIncident',
            'messages.user',
            'messages.customer',
            'fieldVisits.assignee',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('escalate')
                ->label(fn (): string => 'Escalate (L'.(int) $this->record->escalation_level.')')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('danger')
                ->visible(fn (): bool => SupportPanelAccess::escalateTickets(auth()->user())
                    && ! in_array($this->record->status, ['resolved', 'closed'], true)
                    && (int) $this->record->escalation_level < 3)
                ->form(function (): array {
                    $sla = app(SupportSlaService::class);
                    $options = collect($sla->escalationOptions($this->record))
                        ->mapWithKeys(fn (array $o): array => [(string) $o['level'] => $o['label']])
                        ->all();

                    if ($options === []) {
                        $options = ['1' => 'Senior Support', '2' => 'NOC Engineer', '3' => 'Manager'];
                    }

                    return [
                        Forms\Components\Select::make('level')
                            ->label('Escalate to')
                            ->options($options)
                            ->required()
                            ->native(false),
                        Forms\Components\Textarea::make('note')
                            ->label('Internal note (optional)')
                            ->rows(2),
                    ];
                })
                ->action(function (array $data): void {
                    $level = app(SupportSlaService::class)->escalateManually(
                        $this->record,
                        (int) $data['level'],
                        auth()->user(),
                    );

                    if (filled($data['note'] ?? null)) {
                        \App\Models\SupportTicketMessage::query()->create([
                            'tenant_id' => $this->record->tenant_id,
                            'support_ticket_id' => $this->record->id,
                            'user_id' => auth()->id(),
                            'body' => (string) $data['note'],
                            'is_internal' => true,
                        ]);
                    }

                    $this->record->refresh();
                    $this->ticketWorkspace = null;
                    Notification::make()
                        ->title('Escalated to level '.$level)
                        ->success()
                        ->send();
                }),
            Actions\Action::make('assignStaff')
                ->label(fn (): string => $this->record->assignee
                    ? 'Assigned: '.$this->record->assignee->name
                    : 'Assign staff')
                ->icon('heroicon-o-user-plus')
                ->color(fn (): string => $this->record->assignee ? 'gray' : 'warning')
                ->visible(fn (): bool => SupportPanelAccess::manageTickets(auth()->user()))
                ->form([
                    Forms\Components\Select::make('assigned_to')
                        ->label('Technician')
                        ->options(fn (): array => SupportPanelAccess::assignableStaffOptions(
                            $this->record->assigned_to ? (int) $this->record->assigned_to : null,
                        ))
                        ->nullable()
                        ->default(fn (): ?string => $this->record->assigned_to
                            ? (string) $this->record->assigned_to
                            : null)
                        ->placeholder('Unassigned')
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $assignedTo = filled($data['assigned_to'] ?? null) ? (int) $data['assigned_to'] : null;
                    $this->record->update(['assigned_to' => $assignedTo]);
                    $this->record->load('assignee');
                    $this->data['assigned_to'] = $assignedTo;
                    $this->ticketWorkspace = null;
                    Notification::make()
                        ->title($assignedTo ? 'Technician assigned' : 'Ticket unassigned')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('assignToMe')
                ->label('Assign to me')
                ->icon('heroicon-o-user')
                ->visible(fn (): bool => auth()->id()
                    && (int) $this->record->assigned_to !== (int) auth()->id()
                    && SupportPanelAccess::manageTickets(auth()->user()))
                ->action(function (): void {
                    $this->record->update(['assigned_to' => auth()->id()]);
                    $this->record->load('assignee');
                    $this->data['assigned_to'] = auth()->id();
                    $this->ticketWorkspace = null;
                    Notification::make()->title('Assigned to you')->success()->send();
                }),
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
        $assignedTo = $this->data['assigned_to'] ?? $this->record->assigned_to;
        $cacheKey = $this->record->getKey().':'.(string) $customerId.':'.(string) ($assignedTo ?? '');

        if (
            $this->ticketWorkspace !== null
            && ($this->ticketWorkspace['_cache_key'] ?? null) === $cacheKey
        ) {
            return $this->ticketWorkspace;
        }

        $this->ticketWorkspace = array_merge(
            $this->workspaceService()->buildViewBundle($this->record, $customerId, $assignedTo),
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
