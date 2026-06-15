<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckOpsNotificationsCommand extends Command
{
    protected $signature = 'isp:check-ops-notifications {--send-test : Send a test message to the ops Telegram chat}';

    protected $description = 'Verify Telegram, email, SMS, and FCM ops notification configuration.';

    public function handle(): int
    {
        $issues = 0;

        if (! config('notifications.telegram.enabled', false) || blank(config('notifications.telegram.ops_chat_id'))) {
            $this->warn('[!] Telegram ops alerts not configured (NOTIFICATIONS_TELEGRAM_ENABLED + OPS_CHAT_ID).');
            $issues++;
        } else {
            $this->line('[ok] Telegram ops chat configured.');
            if ($this->option('send-test')) {
                try {
                    app(\App\Services\Notifications\Channels\TelegramNotificationChannel::class)->send(
                        (string) config('notifications.telegram.ops_chat_id'),
                        '✅ ISP Billing Telegram test — '.now()->format('d M Y, h:i A'),
                    );
                    $this->line('[ok] Test Telegram sent.');
                } catch (\Throwable $e) {
                    $this->error('[!] Telegram test failed: '.$e->getMessage());
                    $issues++;
                }
            }
        }

        if (blank(config('alerts.ops_email'))) {
            $this->warn('[!] ALERTS_OPS_EMAIL not set — queue/SMS health emails disabled.');
            $issues++;
        } else {
            $this->line('[ok] Ops email: '.config('alerts.ops_email'));
        }

        if (! config('notifications.sms.enabled', false)) {
            $this->warn('[!] SMS disabled — ALERTS_OPS_SMS_PHONE will not send.');
        } elseif (blank(config('alerts.ops_sms_phone'))) {
            $this->warn('[!] ALERTS_OPS_SMS_PHONE not set.');
        } else {
            $this->line('[ok] Ops SMS phone configured.');
        }

        if (! config('mobile.fcm_enabled', false)) {
            $this->warn('[!] FCM_ENABLED=false — mobile push notifications off.');
            $issues++;
        } elseif (blank(config('mobile.fcm_server_key'))) {
            $this->warn('[!] FCM_SERVER_KEY missing.');
            $issues++;
        } else {
            $this->line('[ok] FCM push configured.');
        }

        return $issues === 0 ? self::SUCCESS : self::FAILURE;
    }
}
