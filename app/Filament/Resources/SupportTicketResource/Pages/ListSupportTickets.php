<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', ['resolved', 'closed']))
                ->badge(SupportTicket::query()->whereNotIn('status', ['resolved', 'closed'])->count()),
            'sla' => Tab::make('SLA overdue')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->whereNotNull('sla_resolve_due_at')
                    ->where('sla_resolve_due_at', '<', now()))
                ->badgeColor('danger'),
            'unassigned' => Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->whereNull('assigned_to')),
            'mine' => Tab::make('Assigned to me')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('assigned_to', auth()->id())
                    ->whereNotIn('status', ['resolved', 'closed'])),
            'live_chat' => Tab::make('Live chat')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('channel', 'live_chat')
                    ->whereNotIn('status', ['resolved', 'closed'])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
