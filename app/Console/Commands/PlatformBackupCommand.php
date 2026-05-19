<?php

namespace App\Console\Commands;

use App\Services\System\PlatformBackupService;
use Illuminate\Console\Command;

class PlatformBackupCommand extends Command
{
    protected $signature = 'isp:platform-backup {--no-zip : Keep folder only, skip ZIP packaging}';

    protected $description = 'Create database + storage backup archive';

    public function handle(PlatformBackupService $backups): int
    {
        $result = $backups->create(packageZip: ! $this->option('no-zip'));

        $this->info('Backup created: '.$result['stamp']);
        if ($result['zip'] !== null) {
            $this->line('ZIP: '.$result['zip']);
        }
        $this->line('Directory: '.$result['directory']);

        return self::SUCCESS;
    }
}
