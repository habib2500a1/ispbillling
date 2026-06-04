<?php

namespace App\Console\Commands;

use App\Support\AdminStylesheetRegistry;
use Illuminate\Console\Command;

class VerifyStylesheetsCommand extends Command
{
    protected $signature = 'isp:verify-stylesheets';

    protected $description = 'Verify modular CSS files exist under public/css/admin/';

    public function handle(): int
    {
        $missing = AdminStylesheetRegistry::missingModules();

        if ($missing === []) {
            $this->info('All modular stylesheet files are present.');

            return self::SUCCESS;
        }

        $this->error('Missing CSS modules:');
        foreach ($missing as $file) {
            $this->line('  - public/css/'.$file);
        }

        return self::FAILURE;
    }

}
