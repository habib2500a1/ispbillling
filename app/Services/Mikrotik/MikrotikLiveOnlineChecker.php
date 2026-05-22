<?php

namespace App\Services\Mikrotik;

use App\Models\Customer;
use App\Models\MikrotikServer;
use Illuminate\Support\Facades\Cache;
use RouterOS\Query;

/**
 * Optional live RouterOS session check (PHPNuxBill-style), separate from polled is_ppp_online flags.
 */
final class MikrotikLiveOnlineChecker
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('bandwidth.live_online_check', false);
    }

    /**
     * @return bool|null true=online, false=offline, null=API unreachable / skipped
     */
    public function checkCustomer(Customer $customer): ?bool
    {
        if (! $this->enabled()) {
            return null;
        }

        $server = $customer->mikrotikServer;
        if ($server === null || ! $server->is_enabled) {
            return false;
        }

        if ($server->last_api_status !== 'online') {
            return false;
        }

        $login = $customer->pppLoginName();
        if ($login === '') {
            return false;
        }

        $cacheKey = 'mikrotik_live_online:'.(int) $customer->id.':'.md5($login);
        $ttl = max(5, (int) config('bandwidth.live_online_cache_seconds', 30));

        return Cache::remember($cacheKey, $ttl, function () use ($server, $login): ?bool {
            return $this->probePppActive($server, $login);
        });
    }

    /**
     * @return bool|null
     */
    private function probePppActive(MikrotikServer $server, string $login): ?bool
    {
        try {
            $client = $this->mikrotik->makeClient($server);
            $query = new Query('/ppp/active/print');
            $query->where('name', $login);
            $rows = $client->query($query)->read();

            if (! is_array($rows)) {
                return false;
            }

            foreach ($rows as $row) {
                if (is_array($row) && isset($row['.id'])) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return null;
        }
    }
}
