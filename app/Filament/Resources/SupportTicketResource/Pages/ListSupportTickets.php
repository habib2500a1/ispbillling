<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\Concerns\UsesSupportTicketLayout;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketSearchService;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSupportTickets extends ListRecords
{
    use UsesSupportTicketLayout;

    protected static string $resource = SupportTicketResource::class;

    protected static string $view = 'filament.resources.support-ticket-resource.pages.list-support-tickets';

    public function mount(): void
    {
        parent::mount();
        $this->mountSupportTicketLayout();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $term = trim((string) request()->query('search', ''));
        if ($term !== '') {
            app(SupportTicketSearchService::class)->apply($query, $term);
        }

        return $query;
    }

    public function getTableSearchTerm(): ?string
    {
        $term = trim((string) request()->query('search', ''));

        return $term !== '' ? $term : null;
    }

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
            Actions\Action::make('create')
                ->label('New ticket')
                ->icon('heroicon-o-plus')
                ->url(SupportTicketResource::getUrl('create'))
                ->extraAttributes(['data-navigate' => 'false']),
        ];
    }
}
