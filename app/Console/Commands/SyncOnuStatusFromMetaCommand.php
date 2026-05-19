<?php

namespace App\Console\Commands;

use App\Services\Olt\OnuStatusFromMetaSyncService;
use Illuminate\Console\Command;

class SyncOnuStatusFromMetaCommand extends Command
{
    protected $signature = 'isp:sync-onu-status-from-meta';

    protected $description = 'Copy portal_onu_oper_status / portal_offline_reason from device meta into ONU columns (for NMS / scripts).';

    public function handle(OnuStatusFromMetaSyncService $sync): int
    {
        if (! config('olt_vendors.meta_sync_enabled', true)) {
            $this->info('ONU meta → column sync is disabled (ONU_META_SYNC_ENABLED / config).');

            return self::SUCCESS;
        }

        $n = $sync->syncChunk(150);
        $this->info("Updated {$n} ONU record(s) from meta.");

        return self::SUCCESS;
    }
}
