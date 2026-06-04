<?php

namespace App\Console\Commands;

/**
 * @deprecated Use isp:sync-prices-from-legacy-portal (includes packages + monthly bills).
 */
class SyncLegacyPortalCustomerPackagesCommand extends SyncLegacyPortalPricesCommand
{
    protected $signature = 'isp:sync-customer-packages-from-legacy-portal
                            {--query=alloverclients : legacy portal list filter}
                            {--with-billing : Also sync current-month due/balance from legacy portal}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = '[Deprecated] Use isp:sync-prices-from-legacy-portal — syncs packages, bills, and package prices';

    public function handle(\App\Services\Import\LegacyPortalPriceSyncService $sync): int
    {
        $this->warn('Tip: prefer php artisan isp:sync-prices-from-legacy-portal');

        return parent::handle($sync);
    }
}
