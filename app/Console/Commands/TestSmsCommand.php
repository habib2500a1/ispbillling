<?php

namespace App\Console\Commands;

use App\Services\Notifications\Channels\SmsNotificationChannel;
use App\Services\Notifications\Gateways\KhudeBartaSmsGateway;
use Illuminate\Console\Command;

class TestSmsCommand extends Command
{
    protected $signature = 'isp:test-sms
                            {phone : Recipient phone (01XXXXXXXXX or 8801...)}
                            {--message= : SMS body (default test message)}
                            {--dry-run : Show payload only (KhudeBarta)}';

    protected $description = 'Send a test SMS through the configured gateway (KhudeBarta, BulkSMSBD, etc.).';

    public function handle(): int
    {
        if (! config('notifications.sms.enabled', false)) {
            $this->warn('SMS is disabled. Set NOTIFICATIONS_SMS_ENABLED=true');

            return self::FAILURE;
        }

        $phone = (string) $this->argument('phone');
        $message = (string) ($this->option('message') ?: 'ISP Platform test SMS — '.now()->format('Y-m-d H:i'));

        $provider = (string) config('notifications.sms.provider', 'bulksmsbd');
        $this->info("Provider: {$provider}");

        if ($this->option('dry-run') && $provider === 'khudebarta') {
            $gw = app(KhudeBartaSmsGateway::class);
            $to = $gw->normalizePhone($phone);
            $apiKey = (string) config('notifications.sms.api_key');
            $secret = (string) config('notifications.sms.secret_key');
            $caller = (string) config('notifications.sms.sender_id');
            $hash = $gw->buildHash($apiKey, $secret, $caller, $to, $message);
            $this->line('URL: '.config('notifications.sms.api_url'));
            $this->line(json_encode([
                'apikey' => substr($apiKey, 0, 4).'…',
                'secretkey' => '***',
                'callerID' => $caller,
                'toUser' => $to,
                'messageContent' => $message,
                'hash' => $hash,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        try {
            app(SmsNotificationChannel::class)->send($phone, $message);
            $this->info('SMS sent (check phone and Notification logs in admin).');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
