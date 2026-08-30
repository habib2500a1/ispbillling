<?php

namespace Tests\Feature;

use App\Livewire\AutomaticProcesses;
use App\Models\AutomaticProcess;
use App\Models\MainSiteData;
use App\Models\User;
use Database\Seeders\AutomaticProcessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomaticProcessesDeskTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_owner_can_edit_bill_time_and_rules_from_one_desk(): void
    {
        $this->actingAs($this->admin());

        (new AutomaticProcessSeeder)->syncOnDeploy();

        $job = AutomaticProcess::query()->where('slug', 'generate-monthly-bills')->firstOrFail();
        $this->assertSame('23:45', $job->execute_at);

        Livewire::test(AutomaticProcesses::class)
            ->call('openEdit', $job->id)
            ->set('edit_execute_at', '21:15')
            ->set('edit_interval', 'daily')
            ->call('saveProcess')
            ->assertHasNoErrors();

        $this->assertSame('21:15', $job->fresh()->execute_at);

        Livewire::test(AutomaticProcesses::class)
            ->set('bill_generate_at', '22:10')
            ->set('sms_send_at', '11:05')
            ->set('disable_at', '09:00')
            ->set('reminder_at', '07:30')
            ->set('monthly_bill_sms_day', 5)
            ->set('payment_reminder_days', 3)
            ->set('disable_check_days', 2)
            ->set('disable_check_no', 100)
            ->set('expired_profile_name', 'Expired')
            ->set('late_fee_per_day', 15)
            ->set('late_fee_grace_days', 1)
            ->set('log_retention_days', 14)
            ->call('saveRules')
            ->assertHasNoErrors();

        $this->assertSame('22:10', AutomaticProcess::query()->where('slug', 'generate-monthly-bills')->value('execute_at'));
        $this->assertSame('11:05', AutomaticProcess::query()->where('slug', 'monthly-bill-sms')->value('execute_at'));
        $this->assertEquals(5, (int) MainSiteData::getValue('monthly_bill_sms_day'));
        $this->assertEquals(3, (int) MainSiteData::getValue('payment_reminder_days'));
        $this->assertEquals(2, (int) MainSiteData::getValue('disable_check_days'));
        $this->assertEquals(14, (int) MainSiteData::getValue('log_retention_days'));
    }

    public function test_sync_on_deploy_does_not_overwrite_owner_clock(): void
    {
        (new AutomaticProcessSeeder)->syncOnDeploy();

        $job = AutomaticProcess::query()->where('slug', 'generate-monthly-bills')->firstOrFail();
        $job->update(['execute_at' => '18:00', 'name' => 'My bill time']);

        (new AutomaticProcessSeeder)->syncOnDeploy();

        $job->refresh();
        $this->assertSame('18:00', $job->execute_at);
        $this->assertSame('My bill time', $job->name);
    }

    public function test_monthly_bill_sms_skips_when_not_configured_day(): void
    {
        MainSiteData::setValue('monthly_bill_sms_day', 28);

        if ((int) now()->format('j') === 28) {
            $this->markTestSkipped('Today is the 28th — skip-day path cannot be asserted.');
        }

        $this->artisan('cpagol:send-monthly-bill-sms')
            ->expectsOutputToContain('Skipped')
            ->assertSuccessful();
    }
}
