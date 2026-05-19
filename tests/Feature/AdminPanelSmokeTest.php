<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        return $user;
    }

    /**
     * @return array<string, string>
     */
    public static function adminPagesProvider(): array
    {
        return [
            'dashboard' => ['/admin', 'Dashboard hub'],
            'support hub' => ['/admin/support-hub', 'Support center'],
            'support tickets' => ['/admin/support-tickets', 'Support'],
            'bandwidth monitor' => ['/admin/bandwidth-monitor', 'Bandwidth'],
            'online clients' => ['/admin/online-clients', 'Online clients'],
            'optical noc' => ['/admin/optical-noc', 'ONU optical'],
            'notifications hub' => ['/admin/notifications-hub', 'notification'],
            'send sms' => ['/admin/send-sms', 'Send SMS'],
            'sms report' => ['/admin/notification-logs/sms-report', 'SMS Report'],
            'notification settings' => ['/admin/manage-notifications', 'templates'],
            'notification logs' => ['/admin/notification-logs', 'notification'],
            'payments hub' => ['/admin/payments-overview', 'Payment'],
            'billing hub' => ['/admin/billing-overview', 'Billing'],
            'customers' => ['/admin/customers/', 'Customer'],
            'invoices' => ['/admin/invoices', 'Invoice'],
            'payments' => ['/admin/payments', 'Payment'],
            'resellers hub' => ['/admin/resellers-hub', 'Reseller'],
            'resellers create' => ['/admin/resellers/create', 'Partner profile'],
            'accounting hub' => ['/admin/accounting-hub', 'Accounting'],
            'financial reports' => ['/admin/financial-reports', 'Profit'],
            'reports hub' => ['/admin/reports-hub', 'Reporting'],
            'analytics reports' => ['/admin/analytics-reports', 'Collection'],
            'cashbook' => ['/admin/cashbook-entries', 'Cashbook'],
            'staff hub' => ['/admin/staff-control-hub', 'Admin & staff'],
            'permission matrix' => ['/admin/permission-matrix', 'Permission matrix'],
            'network intelligence hub' => ['/admin/network-intelligence-hub', 'Network intelligence'],
            'network topology' => ['/admin/network-topology', 'Network topology map'],
            'task kanban' => ['/admin/task-kanban-board', 'Staff task board'],
            'churn zone reports' => ['/admin/churn-zone-reports', 'Zone-wise collection'],
            'netflow analysis' => ['/admin/netflow-analysis', 'NetFlow'],
            'snmp monitor' => ['/admin/snmp-monitor', 'SNMP'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminPagesProvider')]
    public function test_admin_pages_return_ok(string $url, string $see): void
    {
        $response = $this->actingAs($this->admin())->get($url);

        if ($response->status() === 308) {
            $response = $this->followRedirects($response);
        }

        $response->assertOk()->assertSee($see, false);
    }
}
