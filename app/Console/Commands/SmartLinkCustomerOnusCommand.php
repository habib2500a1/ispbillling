<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Optical\CustomerOnuSmartLinkService;
use App\Services\Optical\LegacyPortalOnuAutoLinkService;
use Illuminate\Console\Command;

class SmartLinkCustomerOnusCommand extends Command
{
    protected $signature = 'isp:smart-link-customer-onus
                            {--tenant= : Tenant ID}
                            {--no-reset : Do not remove wrong/placeholder links first}
                            {--basic : Only smart-relink (skip FDB/PPP/MikroTik full pipeline)}';

    protected $description = 'Auto ONU↔subscriber link (default: FDB + PPP + login + VLAN; use --basic for smart-relink only)';

    public function handle(
        CustomerOnuSmartLinkService $linker,
        LegacyPortalOnuAutoLinkService $fullLink,
    ): int {
        $reset = ! $this->option('no-reset');
        $useFull = ! $this->option('basic')
            && config('optical.auto_link_full_pipeline', true);

        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        foreach ($tenantIds as $tenantId) {
            if ($useFull) {
                if ($reset) {
                    $linker->pruneInvalidLinks((int) $tenantId);
                }
                $al = $fullLink->runAfterOltSync((int) $tenantId);
                $this->line(sprintf(
                    'Tenant #%d (full): linked %d · FDB %d · PPP %d · hints %d · smart %d',
                    $tenantId,
                    (int) ($al['linked'] ?? 0),
                    (int) ($al['fdb_linked'] ?? 0),
                    (int) (($al['ppp_customer_linked'] ?? 0) + ($al['ppp_session_linked'] ?? 0)),
                    (int) ($al['hint_linked'] ?? 0),
                    (int) ($al['smart_linked'] ?? 0),
                ));

                continue;
            }

            $result = $linker->smartRelinkTenant((int) $tenantId, $reset);
            $this->line(sprintf(
                'Tenant #%d: pruned %d wrong · linked %d · skipped %d ambiguous · conflicts %d',
                $tenantId,
                $result['pruned'],
                $result['linked'],
                $result['skipped'],
                $result['conflicts'],
            ));
            foreach ($result['by_reason'] as $reason => $count) {
                $this->line("  · {$reason}: {$count}");
            }
        }

        $this->info('Auto link complete.');

        return self::SUCCESS;
    }
}
