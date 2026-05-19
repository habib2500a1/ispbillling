<?php

namespace Tests\Feature;

use App\Filament\Pages\TaskKanbanBoard;
use App\Models\InternalTask;
use App\Models\User;
use App\Services\Support\InternalTaskKanbanService;
use App\Support\InternalTaskStatus;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskKanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    private function admin(): User
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        return $user;
    }

    public function test_kanban_page_loads(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/task-kanban-board')
            ->assertOk()
            ->assertSee('Staff task board');
    }

    public function test_move_task_updates_status(): void
    {
        $task = InternalTask::query()->create([
            'tenant_id' => 1,
            'title' => 'Fiber check',
            'status' => InternalTaskStatus::PENDING,
            'priority' => 'high',
        ]);

        app(InternalTaskKanbanService::class)->move($task->id, InternalTaskStatus::IN_PROGRESS);

        $task->refresh();
        $this->assertSame(InternalTaskStatus::IN_PROGRESS, $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_done_sets_completed_at(): void
    {
        $task = InternalTask::query()->create([
            'tenant_id' => 1,
            'title' => 'Close ticket batch',
            'status' => InternalTaskStatus::IN_PROGRESS,
            'priority' => 'normal',
        ]);

        app(InternalTaskKanbanService::class)->move($task->id, InternalTaskStatus::DONE);

        $task->refresh();
        $this->assertSame(InternalTaskStatus::DONE, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_livewire_move_task(): void
    {
        $task = InternalTask::query()->create([
            'tenant_id' => 1,
            'title' => 'OLT reboot',
            'status' => InternalTaskStatus::PENDING,
            'priority' => 'urgent',
        ]);

        Livewire::actingAs($this->admin())
            ->test(TaskKanbanBoard::class)
            ->call('moveTask', $task->id, InternalTaskStatus::DONE)
            ->assertHasNoErrors();

        $this->assertSame(InternalTaskStatus::DONE, $task->fresh()->status);
    }
}
