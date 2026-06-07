<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class RebuildMobileApksCommand extends Command
{
    protected $signature = 'isp:rebuild-mobile-apks
                            {--sync : Only sync from GitHub when Flutter is unavailable}
                            {--foreground : Run in foreground (default: background)}';

    protected $description = 'Build or sync mobile APKs for the current APP_URL domain';

    public function handle(): int
    {
        $script = base_path('scripts/auto-mobile-after-deploy.sh');
        if (! is_file($script)) {
            $this->error('Missing scripts/auto-mobile-after-deploy.sh');

            return self::FAILURE;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '') {
            $this->error('APP_URL is empty in .env');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $script = base_path('scripts/sync-mobile-apks-from-github.sh');
        }

        $log = storage_path('logs/rebuild-mobile-apks.log');
        $cmd = 'bash '.escapeshellarg($script).' >> '.escapeshellarg($log).' 2>&1';

        if ($this->option('foreground')) {
            $this->line("Running mobile APK deploy for {$appUrl}...");
            passthru('bash '.escapeshellarg($script), $code);

            return $code === 0 ? self::SUCCESS : self::FAILURE;
        }

        passthru('nohup '.$cmd.' &');
        $this->info("Mobile APK rebuild started in background for {$appUrl}");
        $this->line("Log: {$log}");
        $this->line("Download: {$appUrl}/downloads/isp-radiant.apk");

        return self::SUCCESS;
    }
}
