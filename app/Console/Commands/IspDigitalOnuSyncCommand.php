<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Optical\IspDigitalOnuPipelineService;
use Illuminate\Console\Command;

class IspDigitalOnuSyncCommand extends Command
{
    protected $signature = 'isp:ispdigital-onu-sync {--tenant= : Tenant id}';

    protected $description = 'ISP Digital style: BDCOM OLT SNMP → ONU inventory → PPP login link → dBm snapshots';

    public function handle(IspDigitalOnuPipelineService $pipeline): int
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
            $this->info("Tenant #{$tenantId}: ISP Digital ONU pipeline…");
            $stats = $pipeline->runTenantPipeline((int) $tenantId);
            $this->line(sprintf(
                '  OLTs %d · discovered %d · linked %d · signal snapshots %d',
                $stats['olts'],
                $stats['discovered'],
                $stats['linked'],
                $stats['signals']['logged'] ?? 0,
            ));
        }

        return self::SUCCESS;
    }
}
