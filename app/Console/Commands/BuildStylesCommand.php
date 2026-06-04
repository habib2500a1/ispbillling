<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildStylesCommand extends Command
{
    protected $signature = 'isp:build-styles';

    protected $description = 'Concat modular CSS into bundle files for fast production loading';

    public function handle(): int
    {
        $scripts = [
            'split-admin-saas-css.sh',
            'concat-admin-saas-css.sh',
            'split-clients-directory-css.sh',
            'concat-clients-directory-css.sh',
            'split-subscriber-view-css.sh',
            'concat-subscriber-view-css.sh',
        ];

        foreach ($scripts as $script) {
            $path = base_path('scripts/'.$script);
            if (! is_file($path)) {
                $this->warn("Skip missing script: {$script}");

                continue;
            }

            $this->line("Running {$script}…");
            $process = Process::fromShellCommandline('bash '.escapeshellarg($path), base_path());
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error($process->getErrorOutput());

                return self::FAILURE;
            }
        }

        $this->info('CSS bundles rebuilt. Set ISP_BUNDLE_CSS=true on production.');

        return self::SUCCESS;
    }

}
