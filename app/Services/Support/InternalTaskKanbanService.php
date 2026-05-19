<?php

namespace App\Services\Support;

use App\Filament\Resources\InternalTaskResource;
use App\Models\InternalTask;
use App\Support\InternalTaskStatus;
use Illuminate\Support\Collection;

class InternalTaskKanbanService
{
    /**
     * @return array<string, array{label: string, color: string, tasks: Collection<int, InternalTask>}>
     */
    public function board(?int $assigneeId = null, ?string $priority = null): array
    {
        $query = InternalTask::query()
            ->with(['assignee:id,name', 'creator:id,name'])
            ->when($assigneeId, fn ($q) => $q->where('assigned_to', $assigneeId))
            ->when(filled($priority), fn ($q) => $q->where('priority', $priority))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderBy('due_at')
            ->orderByDesc('id');

        $grouped = $query->get()->groupBy(
            fn (InternalTask $task): string => InternalTaskStatus::normalize((string) $task->status)
        );

        $columns = [];
        foreach (InternalTaskStatus::kanbanColumns() as $status) {
            $columns[$status] = [
                'label' => InternalTaskStatus::labels()[$status],
                'color' => InternalTaskStatus::colors()[$status],
                'tasks' => $grouped->get($status, collect()),
            ];
        }

        return $columns;
    }

    public function move(int $taskId, string $status): InternalTask
    {
        $status = InternalTaskStatus::normalize($status);
        $task = InternalTask::query()->findOrFail($taskId);
        $task->status = $status;

        if ($status === InternalTaskStatus::DONE) {
            $task->completed_at = $task->completed_at ?? now();
        } else {
            $task->completed_at = null;
        }

        $task->save();

        return $task->fresh(['assignee', 'creator']);
    }

    public function createQuick(string $title, string $priority = 'normal', ?int $assigneeId = null): InternalTask
    {
        return InternalTask::query()->create([
            'title' => $title,
            'priority' => $priority,
            'status' => InternalTaskStatus::PENDING,
            'assigned_to' => $assigneeId,
            'created_by' => auth()->id(),
        ]);
    }

    public static function editUrl(InternalTask $task): string
    {
        return InternalTaskResource::getUrl('edit', ['record' => $task]);
    }
}
