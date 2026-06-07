<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateWebhookSecretsCommand extends Command
{
    protected $signature = 'isp:generate-webhook-secrets
                            {--write : Write/update secrets in the local .env file}
                            {--only-missing : Only generate keys that are empty or absent in .env}';

    protected $description = 'Generate secure webhook and device secrets for production deployment.';

    /**
     * @var array<string, string>
     */
    private array $keys = [
        'PAYMENT_WEBHOOK_SECRET' => 'payment gateway webhook',
        'ISP_SUPPORT_WEBHOOK_SECRET' => 'support ticket webhook',
        'NETFLOW_WEBHOOK_SECRET' => 'NetFlow ingest webhook',
        'OPTICAL_WEBHOOK_SECRET' => 'optical ingest webhook',
        'CALL_CENTER_WEBHOOK_SECRET' => 'call center webhook',
        'ROCKET_WEBHOOK_SECRET' => 'Rocket payment webhook',
        'MFS_SMS_DEVICE_API_KEY' => 'mobile MFS SMS forwarder',
        'WHATSAPP_WEBHOOK_VERIFY_TOKEN' => 'WhatsApp verify token',
    ];

    public function handle(): int
    {
        $onlyMissing = (bool) $this->option('only-missing');
        $envPath = base_path('.env');
        $contents = File::exists($envPath) ? File::get($envPath) : '';
        $secrets = [];

        foreach ($this->keys as $key => $label) {
            if ($onlyMissing && $this->envKeyHasValue($contents, $key)) {
                continue;
            }

            $length = $key === 'WHATSAPP_WEBHOOK_VERIFY_TOKEN' ? 40 : 64;
            $secrets[$key] = Str::random($length);
            $this->line(str_pad($key, 32).' '.$secrets[$key]."  ({$label})");
        }

        if ($secrets === []) {
            $this->info('All webhook secrets already set in .env.');

            return self::SUCCESS;
        }

        if (! $this->option('write')) {
            $this->newLine();
            $this->comment('Run with --write to update the local .env file automatically.');

            return self::SUCCESS;
        }

        if (! File::exists($envPath)) {
            $this->error('.env file not found. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        foreach ($secrets as $key => $value) {
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            $line = $key.'='.$value;

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        File::put($envPath, $contents);

        $this->info('Secrets generated and written to .env');
        $this->warn('Clear config cache after deployment: php artisan config:cache');

        return self::SUCCESS;
    }

    private function envKeyHasValue(string $contents, string $key): bool
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return false;
        }

        $value = trim((string) ($matches[1] ?? ''), " \t\"'");

        return $value !== '';
    }
}
