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

    public function test_staff_can_assign_and_close_ticket_via_api(): void
    {
        Role::findOrCreate('isp-support');
        Role::findOrCreate('isp-admin');
        $admin = User::factory()->create(['tenant_id' => 1, 'name' => 'Admin One']);
        $admin->assignRole('isp-admin');
        $support = User::factory()->create(['tenant_id' => 1, 'name' => 'Tech Two']);
        $support->assignRole('isp-support');
        $token = $admin->createToken('test', ['staff'])->plainTextToken;

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Client',
            'phone' => '01700000002',
            'status' => 'active',
        ]);

        $ticket = SupportTicket::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'channel' => 'app',
            'department' => 'technical_support',
            'priority' => 'high',
            'subject' => 'Wrong trx',
            'description' => 'Need help',
            'status' => 'open',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/staff/tickets/assignees')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tech Two']);

        $this->withToken($token)
            ->patchJson("/api/v1/staff/tickets/{$ticket->id}", ['assigned_to' => $support->id])
            ->assertOk()
            ->assertJsonPath('ticket.assignee_name', 'Tech Two');

        $this->withToken($token)
            ->patchJson("/api/v1/staff/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'closed');

        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_super_admin_can_create_ticket_for_customer(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super-admin');
        $token = $user->createToken('test', ['staff'])->plainTextToken;

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Sabbir Islam',
            'phone' => '01700000099',
            'status' => 'active',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/staff/tickets', [
                'customer_id' => $customer->id,
                'subject' => 'Line down',
                'description' => 'No internet since morning',
                'department' => 'technical_support',
                'priority' => 'medium',
            ])
            ->assertCreated()
            ->assertJsonPath('ticket.customer_name', 'Sabbir Islam');
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
