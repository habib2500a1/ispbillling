<?php

namespace App\Console\Commands;

/**
 * @deprecated Use isp:sync-prices-from-isp-digital (includes packages + monthly bills).
 */
class SyncIspDigitalCustomerPackagesCommand extends SyncIspDigitalPricesCommand
{
    protected $signature = 'isp:sync-customer-packages-from-isp-digital
                            {--query=alloverclients : ISP Digital list filter}
                            {--with-billing : Also sync current-month due/balance from ISP Digital}
                            {--url= : Override ISP_DIGITAL_URL}
                            {--user= : Override ISP_DIGITAL_USERNAME}
                            {--password= : Override ISP_DIGITAL_PASSWORD}';

    protected $description = '[Deprecated] Use isp:sync-prices-from-isp-digital — syncs packages, bills, and package prices';

    public function handle(\App\Services\Import\IspDigitalPriceSyncService $sync): int
    {
        $this->warn('Tip: prefer php artisan isp:sync-prices-from-isp-digital');

        return parent::handle($sync);
    }
}
