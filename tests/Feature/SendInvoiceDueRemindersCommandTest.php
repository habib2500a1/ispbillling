<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendInvoiceDueRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_command_exits_when_disabled(): void
    {
        config(['sms.reminders_enabled' => false]);

        $this->artisan('isp:send-invoice-due-reminders')
            ->assertSuccessful();
    }
}
