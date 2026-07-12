<?php

namespace App\Services\Olt;

use App\Models\CustomerOnu;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull ONU optical (RX/TX/PON/OLT) from ispbilling on the same server when configured.
 */
final class IspbillingOpticalBridge
{
    public function enabled(): bool
    {
        return (bool) config('services.ispbilling.enabled', false)
            && filled(config('database.connections.ispbilling.database'))
            && filled(config('database.connections.ispbilling.password'));
    }

    /**
     * @return Collection<int, object>
     */
    public function listRemoteOnus(int $limit = 200, ?string $search = null): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        try {
            $sql = <<<'SQL'
                SELECT
                    onu.id AS onu_id,
                    onu.display_name,
                    onu.mac_address,
                    onu.serial_number,
                    onu.rx_power_dbm,
                    onu.tx_power_dbm,
                    onu.onu_oper_status,
                    onu.last_polled_at,
                    c.radius_username,
                    c.name AS customer_name,
                    olt.display_name AS olt_name
                FROM devices onu
                LEFT JOIN customers c ON c.id = onu.customer_id
                LEFT JOIN devices olt ON olt.id = onu.olt_id AND olt.type = 'olt'
                WHERE onu.type = 'onu'
                SQL;

            $bindings = [];
            if (filled($search)) {
                $sql .= ' AND (
                    onu.display_name ILIKE ?
                    OR onu.mac_address ILIKE ?
                    OR onu.serial_number ILIKE ?
                    OR c.radius_username ILIKE ?
                    OR c.name ILIKE ?
                    OR olt.display_name ILIKE ?
                )';
                $like = '%'.$search.'%';
                $bindings = [$like, $like, $like, $like, $like, $like];
            }

            $sql .= ' ORDER BY onu.last_polled_at DESC NULLS LAST, onu.id DESC LIMIT ?';
            $bindings[] = $limit;

            return collect(DB::connection('ispbilling')->select($sql, $bindings));
        } catch (Throwable $e) {
            Log::warning('ispbilling listRemoteOnus failed', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    public function syncForCustomer(CustomersInfo $customer): ?CustomerOnu
    {
        if (! $this->enabled()) {
            return null;
        }

        $username = $customer->pppUser?->username;
        if (! filled($username)) {
            return null;
        }

        $row = $this->fetchByRadiusUsername($username);
        if ($row === null) {
            return null;
        }

        return $this->upsertLocalOnu($customer, $row);
    }

    public function syncByMac(CustomersInfo $customer, string $mac): ?CustomerOnu
    {
        if (! $this->enabled()) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac) ?? '');
        if (strlen($normalized) < 12) {
            return null;
        }

        $colon = implode(':', str_split(substr($normalized, 0, 12), 2));

        try {
            $row = DB::connection('ispbilling')->selectOne(
                <<<'SQL'
                SELECT
                    onu.id AS onu_id,
                    onu.display_name,
                    onu.mac_address,
                    onu.serial_number,
                    onu.rx_power_dbm,
                    onu.tx_power_dbm,
                    onu.onu_oper_status,
                    onu.last_polled_at,
                    olt.display_name AS olt_name
                FROM devices onu
                LEFT JOIN devices olt ON olt.id = onu.olt_id AND olt.type = 'olt'
                WHERE onu.type = 'onu'
                  AND (
                    REPLACE(UPPER(COALESCE(onu.mac_address, '')), ':', '') = ?
                    OR UPPER(onu.mac_address) = ?
                  )
                ORDER BY onu.rx_power_dbm DESC NULLS LAST, onu.updated_at DESC
                LIMIT 1
                SQL,
                [$normalized, strtoupper($colon)]
            );
        } catch (Throwable $e) {
            Log::warning('ispbilling syncByMac failed', ['mac' => $mac, 'error' => $e->getMessage()]);

            return null;
        }

        if ($row === null) {
            return null;
        }

        return $this->upsertLocalOnu($customer, $row);
    }

    /**
     * Try PPP username first, then caller-id / provided MAC.
     */
    public function autoLinkCustomer(CustomersInfo $customer): ?CustomerOnu
    {
        $customer->loadMissing('pppUser');
        $onu = $this->syncForCustomer($customer);
        if ($onu) {
            return $onu;
        }

        $mac = $customer->pppUser?->caller_id;
        if (filled($mac)) {
            return $this->syncByMac($customer, (string) $mac);
        }

        return null;
    }

    /**
     * Match local PPP usernames to ispbilling ONUs and upsert customer_onus.
     *
     * @return array{synced: int, skipped: int}
     */
    public function syncMatchedCustomers(int $limit = 500): array
    {
        if (! $this->enabled()) {
            return ['synced' => 0, 'skipped' => 0];
        }

        $secrets = PPPSecrets::query()
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->where('status', '!=', 'removed')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'username']);

        $synced = 0;
        $skipped = 0;

        foreach ($secrets as $secret) {
            $customer = CustomersInfo::where('ppp_user_id', $secret->id)->first();
            if (! $customer) {
                $skipped++;

                continue;
            }

            $customer->setRelation('pppUser', $secret);
            $onu = $this->syncForCustomer($customer);
            if ($onu) {
                $synced++;
            } else {
                $skipped++;
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped];
    }

    private function fetchByRadiusUsername(string $username): ?object
    {
        try {
            return DB::connection('ispbilling')->selectOne(
                <<<'SQL'
                SELECT
                    onu.id AS onu_id,
                    onu.display_name,
                    onu.mac_address,
                    onu.serial_number,
                    onu.rx_power_dbm,
                    onu.tx_power_dbm,
                    onu.onu_oper_status,
                    onu.last_polled_at,
                    olt.display_name AS olt_name
                FROM devices onu
                INNER JOIN customers c ON c.id = onu.customer_id
                LEFT JOIN devices olt ON olt.id = onu.olt_id AND olt.type = 'olt'
                WHERE onu.type = 'onu'
                  AND c.radius_username = ?
                ORDER BY onu.rx_power_dbm DESC NULLS LAST, onu.updated_at DESC
                LIMIT 1
                SQL,
                [$username]
            );
        } catch (Throwable $e) {
            Log::warning('ispbilling optical bridge failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function upsertLocalOnu(CustomersInfo $customer, object $row): CustomerOnu
    {
        $onu = $customer->primaryOnu() ?? new CustomerOnu(['customers_info_id' => $customer->id]);
        $onu->fill([
            'customers_info_id' => $customer->id,
            'olt_name' => $row->olt_name ?: null,
            'pon_port' => $row->display_name ?: null,
            'mac_address' => $row->mac_address ?: null,
            'serial_number' => $row->serial_number ?: null,
            'rx_power_dbm' => $row->rx_power_dbm,
            'tx_power_dbm' => $row->tx_power_dbm,
            'oper_status' => $row->onu_oper_status,
            'source' => 'ispbilling',
            'external_id' => (string) $row->onu_id,
            'last_polled_at' => $row->last_polled_at
                ? Carbon::parse($row->last_polled_at)
                : now(),
        ]);
        $onu->save();

        return $onu;
    }
}
