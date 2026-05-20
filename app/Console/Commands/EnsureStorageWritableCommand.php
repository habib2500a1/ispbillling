<?php

namespace App\Console\Commands;

use App\Support\EnsureStorageWritable;
use Illuminate\Console\Command;

class EnsureStorageWritableCommand extends Command
{
    protected $signature = 'isp:ensure-storage {--fix : chown storage to www-data when run as root}';

    protected $description = 'Verify storage/bootstrap paths are writable (prevents 500 after artisan as root).';

    public function handle(): int
    {
        if ($this->option('fix') && EnsureStorageWritable::fixOwnership()) {
            $this->info('Storage ownership fixed for www-data.');
        } elseif ($this->option('fix')) {
            $this->warn('Could not auto-fix (run as root: sudo php artisan isp:ensure-storage --fix).');
        }

        $issues = EnsureStorageWritable::findIssues();

        if ($issues === []) {
            $this->info('All storage paths are writable.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->error($issue);
        }

        $this->line('');
        $this->line('Run: sudo chown -R www-data:www-data storage bootstrap/cache');
        $this->line('Or:  sudo php artisan isp:ensure-storage --fix');

        return self::FAILURE;
    }
}
