<?php

namespace Tests\Feature;

use App\Models\CollectorExpenseCategory;
use App\Models\Customer;
use App\Models\InternalTask;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileStaffExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_ticket_show_reply_and_task_done(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');
        $token = $user->createToken('test', ['staff'])->plainTextToken;

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Test Client',
            'phone' => '01700000001',
            'status' => 'active',
        ]);

        $ticket = SupportTicket::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'channel' => 'app',
            'department' => 'billing',
            'priority' => 'medium',
            'subject' => 'Test ticket',
            'description' => 'Help',
            'status' => 'open',
        ]);

        $this->withToken($token)
            ->getJson("/api/v1/staff/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('ticket.id', $ticket->id);

        $this->withToken($token)
            ->postJson("/api/v1/staff/tickets/{$ticket->id}/reply", ['body' => 'We are checking'])
            ->assertOk();

        $task = InternalTask::query()->create([
            'tenant_id' => 1,
            'title' => 'Follow up',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $this->withToken($token)
            ->patchJson("/api/v1/staff/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('task.status', 'done');
    }

    public function test_collector_expense_categories_endpoint(): void
    {
        Role::findOrCreate('cashier');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('cashier');
        $token = $user->createToken('test', ['collector', 'staff'])->plainTextToken;

        CollectorExpenseCategory::query()->create([
            'tenant_id' => 1,
            'name' => 'Fuel',
            'code' => 'fuel',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/collector/expense-categories')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'code']]]);
    }
}
