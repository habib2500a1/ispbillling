<?php

namespace App\Services\Import\ISPTrack;

use App\Models\Customer;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Artisan;

final class ISPTrackNetworkSyncService
{
    /**
     * @return array<string, int|string>
     */
    public function run(ISPTrackImportContext $ctx, bool $skipEvaluate = false): array
    {
        if ($ctx->dryRun) {
            $ctx->bump('network_would_sync');

            return [
                'customers_with_ppp' => Customer::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $ctx->tenantId)
                    ->where('import_source', ISPTrackImportContext::IMPORT_SOURCE)
                    ->whereNotNull('mikrotik_secret_name')
                    ->count(),
                'dry_run' => 'yes',
            ];
        }

        $withPpp = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('import_source', ISPTrackImportContext::IMPORT_SOURCE)
            ->whereNotNull('mikrotik_secret_name')
            ->count();

        $ctx->bump('network_customers_ready', $withPpp);

        if (! $skipEvaluate) {
            TenantResolver::fake($ctx->tenantId);

            Artisan::call('isp:refresh-customer-due-balance');
            $ctx->bump('due_balance_refreshed');

            Artisan::call('isp:network-evaluate-access', [
                '--tenant' => $ctx->tenantId,
            ]);
            $ctx->bump('network_evaluated');
        }

        return [
            'customers_with_ppp' => $withPpp,
            'due_refresh_exit' => Artisan::output() !== '' ? 'ok' : 'ok',
        ];
    }
}
