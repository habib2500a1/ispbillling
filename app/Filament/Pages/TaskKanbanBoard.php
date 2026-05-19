<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InternalTaskResource;
use App\Models\User;
use App\Services\Support\InternalTaskKanbanService;
use App\Support\InternalTaskStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TaskKanbanBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static string $view = 'filament.pages.task-kanban-board';

    protected static ?string $navigationLabel = 'Task board';

    protected static ?string $title = 'Task board';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 14;

    protected static bool $shouldRegisterNavigation = false;

    public ?int $filterAssignee = null;

    public ?string $filterPriority = null;

    public string $newTitle = '';

    public string $newPriority = 'normal';

    public ?int $newAssignee = null;

    /**
     * @return array<string, array{label: string, color: string, tasks: \Illuminate\Support\Collection}>
     */
    public function getColumnsProperty(): array
    {
        return app(InternalTaskKanbanService::class)->board($this->filterAssignee, $this->filterPriority);
    }

    /**
     * @return array<int, string>
     */
    public function getStaffOptionsProperty(): array
    {
        return User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function moveTask(int $taskId, string $status): void
    {
        app(InternalTaskKanbanService::class)->move($taskId, $status);

        Notification::make()
            ->title('Task moved to '.(InternalTaskStatus::labels()[InternalTaskStatus::normalize($status)] ?? $status))
            ->success()
            ->send();
    }

    public function createTask(): void
    {
        $this->validate([
            'newTitle' => ['required', 'string', 'max:255'],
            'newPriority' => ['required', 'in:low,normal,high,urgent'],
            'newAssignee' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        app(InternalTaskKanbanService::class)->createQuick(
            trim($this->newTitle),
            $this->newPriority,
            $this->newAssignee,
        );

        $this->reset(['newTitle', 'newPriority', 'newAssignee']);
        $this->newPriority = 'normal';

        Notification::make()->title('Task created')->success()->send();
    }

    public function updatedFilterAssignee(): void
    {
        //
    }

    public function updatedFilterPriority(): void
    {
        //
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('Table view')
                ->icon('heroicon-o-table-cells')
                ->url(InternalTaskResource::getUrl('index')),
            Action::make('create')
                ->label('New task')
                ->icon('heroicon-o-plus')
                ->url(InternalTaskResource::getUrl('create')),
        ];
    }

    public static function canAccess(): bool
    {
        return InternalTaskResource::canViewAny();
    }
}
