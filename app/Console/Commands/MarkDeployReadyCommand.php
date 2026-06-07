<?php

namespace App\Console\Commands;

use App\Support\DeployReady;
use Illuminate\Console\Command;

final class MarkDeployReadyCommand extends Command
{
    protected $signature = 'isp:mark-deploy-ready';

    protected $description = 'Mark the application ready to serve HTTP traffic after deploy bootstrap';

    public function handle(): int
    {
        DeployReady::markReady();
        $this->info('Deploy ready flag written: '.DeployReady::flagPath());

        return self::SUCCESS;
    }
}
