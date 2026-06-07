<?php

namespace App\Console\Commands;

use App\Support\EnvFile;
use App\Support\TrustedAppUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class SyncInstanceUrlCommand extends Command
{
    protected $signature = 'isp:sync-instance-url
                            {--path= : Instance app root (defaults to current project)}
                            {--url= : Canonical APP_URL for this instance}
                            {--landing= : ISP_LANDING_DOMAIN (defaults to URL host)}
                            {--previous= : Comma-separated previous APP URLs to keep working}
                            {--remember-old : Append current APP_URL to APP_PREVIOUS_URLS when URL changes}';

    protected $description = 'Sync APP_URL, landing domain, and previous URLs for one deploy instance';

    public function handle(): int
    {
        $root = rtrim((string) ($this->option('path') ?: base_path()), '/');
        $envPath = $root.'/.env';

        if (! is_file($envPath)) {
            $this->error(".env not found at {$envPath}");

            return self::FAILURE;
        }

        $env = EnvFile::at($envPath);
        $newUrl = rtrim((string) ($this->option('url') ?: $env->get('APP_URL', '')), '/');

        if ($newUrl === '') {
            $this->error('APP_URL is required (--url or existing .env value).');

            return self::FAILURE;
        }

        $host = parse_url($newUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $this->error("Invalid APP_URL: {$newUrl}");

            return self::FAILURE;
        }

        $landing = (string) (
            $this->option('landing')
            ?: $env->get('ISP_LANDING_DOMAIN')
            ?: $host
        );
        $currentUrl = rtrim((string) $env->get('APP_URL', ''), '/');
        $previous = (string) ($this->option('previous') ?: $env->get('APP_PREVIOUS_URLS', ''));

        if ($this->option('remember-old')) {
            $previous = TrustedAppUrl::mergePreviousUrls($currentUrl, $newUrl, $previous);
        }

        $env->set('APP_URL', $newUrl);
        $env->set('ISP_LANDING_DOMAIN', $landing);
        $env->set('SESSION_DOMAIN', $landing);
        $env->set('SANCTUM_STATEFUL_DOMAINS', $landing);
        $env->set('APP_PREVIOUS_URLS', $previous);

        $productionUrlFile = $root.'/deploy/production.url';
        File::ensureDirectoryExists(dirname($productionUrlFile));
        file_put_contents($productionUrlFile, $newUrl.PHP_EOL);

        $this->info("Synced instance URL at {$root}");
        $this->line("  APP_URL={$newUrl}");
        $this->line("  ISP_LANDING_DOMAIN={$landing}");

        if ($previous !== '') {
            $this->line("  APP_PREVIOUS_URLS={$previous}");
        }

        return self::SUCCESS;
    }
}
