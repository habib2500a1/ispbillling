<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\InternalTask;
use App\Models\User;
use App\Support\InternalTaskStatus;
use App\Support\StaffTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffTasksController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = StaffTenantScope::tenantIdFor($user);

        $tasks = InternalTask::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($tasks->items())->map(fn (InternalTask $t) => $this->row($t)),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function update(Request $request, int $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $model = InternalTask::withoutGlobalScopes()
            ->where('tenant_id', StaffTenantScope::tenantIdFor($user))
            ->whereKey($task)
            ->firstOrFail();

        $data = $request->validate([
            'status' => ['required', Rule::in(InternalTaskStatus::kanbanColumns())],
        ]);

        $status = InternalTaskStatus::normalize($data['status']);
        $model->update([
            'status' => $status,
            'completed_at' => $status === InternalTaskStatus::DONE ? now() : null,
        ]);

        return response()->json([
            'task' => $this->row($model->fresh()),
            'message' => $status === InternalTaskStatus::DONE ? 'Task marked complete.' : 'Task updated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(InternalTask $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description,
            'status' => (string) $t->status,
            'priority' => $t->priority,
            'due_at' => $t->due_at?->toIso8601String(),
            'completed_at' => $t->completed_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}
