<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateWebhookSecretsCommand extends Command
{
    protected $signature = 'isp:generate-webhook-secrets {--write : Write/update secrets in the local .env file}';

    protected $description = 'Generate secure webhook and device secrets for production deployment.';

    /**
     * @var array<string, string>
     */
    private array $keys = [
        'ISP_SUPPORT_WEBHOOK_SECRET' => 'support ticket webhook',
        'NETFLOW_WEBHOOK_SECRET' => 'NetFlow ingest webhook',
        'OPTICAL_WEBHOOK_SECRET' => 'optical ingest webhook',
        'ROCKET_WEBHOOK_SECRET' => 'Rocket payment webhook',
        'MFS_SMS_DEVICE_API_KEY' => 'mobile MFS SMS forwarder',
        'WHATSAPP_WEBHOOK_VERIFY_TOKEN' => 'WhatsApp verify token',
    ];

    public function handle(): int
    {
        $secrets = [];

        foreach ($this->keys as $key => $label) {
            $length = $key === 'WHATSAPP_WEBHOOK_VERIFY_TOKEN' ? 40 : 64;
            $secrets[$key] = Str::random($length);
            $this->line(str_pad($key, 32).' '.$secrets[$key]."  ({$label})");
        }

        if (! $this->option('write')) {
            $this->newLine();
            $this->comment('Run with --write to update the local .env file automatically.');

            return self::SUCCESS;
        }

        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            $this->error('.env file not found. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        $contents = File::get($envPath);

        foreach ($secrets as $key => $value) {
            $pattern = "/^".preg_quote($key, '/')."=.*/m";
            $line = $key.'='.$value;

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        File::put($envPath, $contents);

        $this->info('Secrets generated and written to .env');
        $this->warn('Clear config cache after deployment: php artisan config:clear');

        return self::SUCCESS;
    }
}
