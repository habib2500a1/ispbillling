<?php

namespace App\Console\Commands;

use App\Support\DeployInstanceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

final class DeployAllInstancesCommand extends Command
{
    protected $signature = 'isp:deploy-all-instances
                            {--config= : Path to deploy/instances.json}
                            {--branch=main : Git branch to pull}
                            {--skip-pull : Skip git pull step}
                            {--id= : Deploy only one instance id}';

    protected $description = 'Deploy all configured ISP instances (one app + DB per domain)';

    public function handle(): int
    {
        $configPath = (string) ($this->option('config') ?: base_path('deploy/instances.json'));

        if (! is_file($configPath)) {
            $this->error("Missing {$configPath} — copy deploy/instances.example.json and edit.");

            return self::FAILURE;
        }

        $config = DeployInstanceRegistry::load($configPath);
        $repoRoot = $config['repo_root'] ?? base_path();
        $onlyId = $this->option('id');
        $instances = DeployInstanceRegistry::enabledInstances($configPath);

        if ($onlyId) {
            $instances = array_values(array_filter(
                $instances,
                static fn (array $instance): bool => (string) ($instance['id'] ?? '') === (string) $onlyId,
            ));
        }

        if ($instances === []) {
            $this->warn('No enabled instances matched.');

            return self::FAILURE;
        }

        $script = base_path('scripts/deploy-instance.sh');
        if (! is_file($script)) {
            $this->error('Missing scripts/deploy-instance.sh');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($instances as $instance) {
            $id = (string) ($instance['id'] ?? 'unknown');
            $path = DeployInstanceRegistry::resolvePath($instance, $repoRoot);

            $this->newLine();
            $this->info("==> Deploying instance [{$id}] at {$path}");

            $command = [
                'bash',
                $script,
                '--id='.$id,
                '--path='.$path,
                '--url='.rtrim((string) $instance['app_url'], '/'),
                '--landing='.(string) ($instance['landing_domain'] ?? parse_url((string) $instance['app_url'], PHP_URL_HOST)),
            ];

            if ($this->option('skip-pull')) {
                $command[] = '--skip-pull';
            } else {
                $command[] = '--branch='.(string) $this->option('branch');
            }

            if (filled($instance['previous_urls'] ?? null) && is_array($instance['previous_urls'])) {
                $command[] = '--previous='.implode(',', $instance['previous_urls']);
            }

            $result = Process::path(base_path())->timeout(900)->run($command);

            $this->output->write($result->output());

            if (! $result->successful()) {
                $failed++;
                $this->error("Instance [{$id}] deploy failed.");
                $this->output->write($result->errorOutput());
            }
        }

        if ($failed > 0) {
            $this->error("{$failed} instance(s) failed.");

            return self::FAILURE;
        }

        $this->info('All instances deployed successfully.');

        return self::SUCCESS;
    }
}
