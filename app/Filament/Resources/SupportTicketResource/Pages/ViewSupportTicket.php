<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->record->loadMissing(['customer.area', 'assignee', 'messages.user', 'messages.customer']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ticket')
                    ->schema([
                        Infolists\Components\TextEntry::make('ticket_number')->label('ID')->copyable(),
                        Infolists\Components\TextEntry::make('customer.name')->label('Subscriber'),
                        Infolists\Components\TextEntry::make('channel')
                            ->formatStateUsing(fn (SupportTicket $record): string => $record->channelLabel()),
                        Infolists\Components\TextEntry::make('department')
                            ->formatStateUsing(fn (?string $state): string => SupportTicket::DEPARTMENTS[$state] ?? (string) $state)
                            ->badge(),
                        Infolists\Components\TextEntry::make('priority')
                            ->formatStateUsing(fn (?string $state): string => SupportTicket::PRIORITIES[$state] ?? (string) $state)
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'critical' => 'danger',
                                'high' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->formatStateUsing(fn (?string $state): string => SupportTicket::STATUSES[$state] ?? (string) $state)
                            ->badge(),
                        Infolists\Components\TextEntry::make('assignee.name')->label('Technician')->placeholder('Unassigned'),
                        Infolists\Components\TextEntry::make('issue_type')->placeholder('—'),
                        Infolists\Components\TextEntry::make('subject')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('SLA & resolution')
                    ->schema([
                        Infolists\Components\TextEntry::make('sla_resolve_due_at')
                            ->label('SLA due')
                            ->dateTime()
                            ->color(fn (SupportTicket $record): ?string => $record->isSlaBreached() ? 'danger' : null),
                        Infolists\Components\TextEntry::make('sla_remaining')
                            ->label('SLA status')
                            ->state(fn (SupportTicket $record): string => $record->slaRemainingLabel()),
                        Infolists\Components\TextEntry::make('escalation_level')
                            ->label('Escalation')
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? 'Level '.$state : 'None'),
                        Infolists\Components\TextEntry::make('resolved_at')->dateTime()->placeholder('—'),
                        Infolists\Components\TextEntry::make('closed_at')->dateTime()->placeholder('—'),
                        Infolists\Components\TextEntry::make('customer_rating')
                            ->label('Customer rating')
                            ->formatStateUsing(fn (?int $state): string => $state ? $state.'/5' : '—'),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('markResolved')
                ->label('Mark resolved')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (): bool => $this->record->isOpen())
                ->action(function (): void {
                    $this->record->update(['status' => 'resolved', 'resolved_at' => now()]);
                    Notification::make()->title('Marked resolved')->success()->send();
                }),
            Actions\Action::make('assignToMe')
                ->label('Assign to me')
                ->icon('heroicon-o-user')
                ->visible(fn (): bool => auth()->id() && (int) $this->record->assigned_to !== (int) auth()->id())
                ->action(function (): void {
                    $this->record->update(['assigned_to' => auth()->id()]);
                    Notification::make()->title('Assigned to you')->success()->send();
                }),
        ];
    }
}
