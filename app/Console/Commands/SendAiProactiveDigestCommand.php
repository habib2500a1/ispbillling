<?php

namespace App\Console\Commands;

use App\Services\Ai\AiProactiveDigestService;
use Illuminate\Console\Command;

class SendAiProactiveDigestCommand extends Command
{
    protected $signature = 'isp:ai-proactive-digest
                            {--tenant= : Limit to one tenant id}
                            {--dry-run : Build digest only, do not send}';

    protected $description = 'Send daily AI operations digest to Telegram ops channel.';

    public function handle(AiProactiveDigestService $digest): int
    {
        if ($this->option('dry-run')) {
            $tenantId = (int) ($this->option('tenant') ?: 1);
            $this->line($digest->buildDigest($tenantId));

            return self::SUCCESS;
        }

        $tenant = $this->option('tenant');
        if ($tenant !== null) {
            $sent = $digest->sendForTenant((int) $tenant) ? 1 : 0;
            $this->info("Sent {$sent} digest(s) for tenant {$tenant}.");

            return self::SUCCESS;
        }

        $sent = $digest->sendAllTenants();
        $this->info("Sent {$sent} AI proactive digest(s).");

        return self::SUCCESS;
    }
}
