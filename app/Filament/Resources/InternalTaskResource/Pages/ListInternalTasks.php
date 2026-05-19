<?php

namespace App\Filament\Resources\InternalTaskResource\Pages;

use App\Filament\Pages\TaskKanbanBoard;
use App\Filament\Resources\InternalTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInternalTasks extends ListRecords
{
    protected static string $resource = InternalTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kanban')
                ->label('Kanban board')
                ->icon('heroicon-o-view-columns')
                ->url(TaskKanbanBoard::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
