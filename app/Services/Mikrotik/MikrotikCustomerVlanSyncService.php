<?php

namespace App\Services\Mikrotik;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Support\CustomerPppLoginResolver;
use App\Support\MikrotikVlanParser;
use Illuminate\Support\Facades\Log;

/**
 * Pull VLAN from MikroTik PPP secrets into customer.meta.vlan for NOC / PON tables.
 */
final class MikrotikCustomerVlanSyncService
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
    ) {}

    /**
     * @param  array<string, mixed>  $secretRow
     * @param  array<string, string>  $profileVlanMap
     */
    public function applyToCustomer(Customer $customer, array $secretRow, array $profileVlanMap = []): bool
    {
        $profile = trim((string) ($secretRow['profile'] ?? ''));
        $profileVlan = $profile !== '' ? ($profileVlanMap[$profile] ?? null) : null;
        $vlan = MikrotikVlanParser::fromPppSecret($secretRow, $profileVlan);
        if ($vlan === null) {
            return false;
        }

        $extra = [];
        if ($profile !== '') {
            $extra['mikrotik_profile'] = $profile;
        }

        return $this->applyVlanToCustomer($customer, $vlan, $extra);
    }

    /**
     * @param  array<string, mixed>  $importRow  MikrotikPppImportService row
     */
    public function applyFromImportRow(Customer $customer, array $importRow, MikrotikServer $server, array $profileVlanMap = []): bool
    {
        return $this->applyToCustomer($customer, $this->secretRowFromImport($importRow, $server), $profileVlanMap);
    }

    /**
     * @return array{updated: int, matched: int, skipped: int, errors: list<string>}
     */
    public function syncServer(MikrotikServer $server): array
    {
        $updated = 0;
        $matched = 0;
        $skipped = 0;
        $errors = [];

        if (! $server->is_enabled) {
            return compact('updated', 'matched', 'skipped', 'errors');
        }

        try {
            $vlanInterfaceMap = $this->mikrotik->fetchInterfaceVlanMap($server);
            $pppoeBindings = $this->mikrotik->fetchPppoeServerInterfaceBindings($server);
            $secrets = $this->mikrotik->fetchPppSecrets($server);
            $profileVlanMap = $this->buildProfileVlanMap($server);
        } catch (\Throwable $e) {
            return [
                'updated' => 0,
                'matched' => 0,
                'skipped' => 0,
                'errors' => [$e->getMessage()],
            ];
        }

        $customersByLogin = $this->indexCustomersForServer((int) $server->tenant_id, (int) $server->id);
        $touchedLogins = [];

        foreach ($pppoeBindings as $binding) {
            $login = CustomerPppLoginResolver::normalize((string) ($binding['user'] ?? ''));
            if ($login === '') {
                $skipped++;

                continue;
            }

            $customer = $customersByLogin[$login] ?? null;
            if ($customer === null) {
                $skipped++;

                continue;
            }

            $vlan = $this->mikrotik->resolveVlanForPppoeInterface(
                (string) ($binding['interface'] ?? ''),
                $vlanInterfaceMap,
            );
            if ($vlan === null) {
                $skipped++;

                continue;
            }

            $matched++;
            $touchedLogins[$login] = true;

            try {
                if ($this->applyVlanToCustomer($customer, $vlan, [
                    'mikrotik_interface' => $binding['interface'] ?? null,
                    'mikrotik_pppoe_service' => $binding['service'] ?? null,
                ])) {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$login}: {$e->getMessage()}";
            }
        }

        foreach ($secrets as $secret) {
            $login = CustomerPppLoginResolver::normalize((string) ($secret['name'] ?? ''));
            if ($login === '' || isset($touchedLogins[$login])) {
                continue;
            }

            $customer = $customersByLogin[$login] ?? null;
            if ($customer === null) {
                continue;
            }

            $matched++;

            try {
                if ($this->applyToCustomer($customer, $secret, $profileVlanMap)) {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$login}: {$e->getMessage()}";
            }
        }

        return compact('updated', 'matched', 'skipped', 'errors');
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     */
    public function applyVlanToCustomer(Customer $customer, string $vlan, array $extraMeta = []): bool
    {
        if (! config('mikrotik.auto_sync_vlan', true)) {
            return false;
        }

        $vlan = MikrotikVlanParser::normalizeVlan($vlan);
        if ($vlan === null) {
            return false;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        if (($meta['vlan_source'] ?? '') === 'manual' && trim((string) ($meta['vlan'] ?? '')) !== '') {
            return false;
        }

        $changed = false;
        $current = trim((string) ($meta['vlan'] ?? ''));

        if ($current !== $vlan) {
            $meta['vlan'] = $vlan;
            $meta['vlan_source'] = 'mikrotik';
            $meta['vlan_synced_at'] = now()->toIso8601String();
            $changed = true;
        }

        foreach ($extraMeta as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (($meta[$key] ?? null) !== $value) {
                $meta[$key] = $value;
                $changed = true;
            }
        }

        if (! $changed) {
            return false;
        }

        $customer->forceFill(['meta' => $meta])->saveQuietly();

        return true;
    }

    /**
     * @return array{updated: int, matched: int, skipped: int, errors: list<string>}
     */
    public function syncTenant(int $tenantId, ?int $onlyServerId = null): array
    {
        $totals = ['updated' => 0, 'matched' => 0, 'skipped' => 0, 'errors' => []];

        $q = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->orderBy('id');

        if ($onlyServerId !== null) {
            $q->whereKey($onlyServerId);
        }

        foreach ($q->cursor() as $server) {
            $result = $this->syncServer($server);
            $totals['updated'] += $result['updated'];
            $totals['matched'] += $result['matched'];
            $totals['skipped'] += $result['skipped'];
            $totals['errors'] = array_merge($totals['errors'], $result['errors']);
        }

        if (config('mikrotik.auto_sync_pon_port_names', true)) {
            app(MikrotikPonPortLabelSyncService::class)->syncTenant($tenantId);
        }

        return $totals;
    }

    /**
     * @return array<string, string> profile name => vlan
     */
    public function buildProfileVlanMap(MikrotikServer $server): array
    {
        if (! $server->is_enabled) {
            return [];
        }

        try {
            $client = $this->mikrotik->makeClient($server);
            $rows = $client->query('/ppp/profile/print')->read();
        } catch (\Throwable $e) {
            Log::debug('mikrotik.vlan_sync.profile_fetch_failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $vlan = MikrotikVlanParser::fromPppProfile($row);
            if ($vlan !== null) {
                $map[$name] = $vlan;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $importRow
     * @return array<string, mixed>
     */
    public function secretRowFromImport(array $importRow, MikrotikServer $server): array
    {
        $secretName = trim((string) ($importRow['secret_name'] ?? $importRow['username'] ?? ''));

        return [
            'name' => $secretName,
            'profile' => $importRow['profile'] ?? null,
            'comment' => $importRow['comment'] ?? null,
            'raw' => is_array($importRow['raw'] ?? null) ? $importRow['raw'] : [],
        ];
    }

    /**
     * @return array<string, Customer>
     */
    private function indexCustomersForServer(int $tenantId, int $mikrotikServerId): array
    {
        $map = [];

        Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('mikrotik_server_id', $mikrotikServerId)
            ->select(['id', 'tenant_id', 'meta', 'mikrotik_secret_name', 'radius_username', 'mikrotik_server_id'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$map): void {
                foreach ($customers as $customer) {
                    foreach (CustomerPppLoginResolver::loginKeysForCustomer($customer) as $key) {
                        $map[$key] = $customer;
                    }
                }
            });

        return $map;
    }
}
