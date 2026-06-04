<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Optical\LegacyPortalOnuPipelineService;
use Illuminate\Console\Command;

class LegacyPortalOnuSyncCommand extends Command
{
    protected $signature = 'isp:legacy-portal-onu-sync {--tenant= : Tenant id}';

    protected $description = 'Legacy portal flow: BDCOM OLT SNMP → ONU inventory → PPP login link → dBm snapshots';

    public function handle(LegacyPortalOnuPipelineService $pipeline): int
    {
        if (! config('optical.enabled', true)) {
            $this->warn('Optical monitoring disabled.');

            return self::SUCCESS;
        }

        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        if ($tenantIds === []) {
            $tenantIds = [1];
        }

        foreach ($tenantIds as $tenantId) {
            $this->info("Tenant #{$tenantId}: legacy portal ONU pipeline…");
            $stats = $pipeline->runTenantPipeline((int) $tenantId);
            $al = $stats['auto_link'] ?? [];
            $this->line(sprintf(
                '  OLTs %d · discovered %d · linked %d · PPP fetched %d (online %d) · link steps: customer %d mac %d hints %d smart %d · snapshots %d',
                $stats['olts'],
                $stats['discovered'],
                $stats['linked'],
                $al['ppp_sessions_fetched'] ?? 0,
                $al['ppp_online'] ?? 0,
                $al['ppp_customer_linked'] ?? 0,
                $al['ppp_session_linked'] ?? 0,
                $al['hint_linked'] ?? 0,
                $al['smart_linked'] ?? 0,
                $stats['signals']['logged'] ?? 0,
            ));
        }

        return self::SUCCESS;
    }
}
