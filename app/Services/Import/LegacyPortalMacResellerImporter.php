<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Reseller;
use App\Support\CustomerPppLoginResolver;
use App\Support\ResellerType;

final class LegacyPortalMacResellerImporter
{
    public function __construct(
        private readonly int $tenantId = 1,
        private readonly ?LegacyPortalMacResellerCustomerMatcher $matcher = null,
        private readonly ?LegacyPortalResellerPackageSyncService $packageSync = null,
    ) {}

    /**
     * @return array{imported: int, updated: int, skipped: int, linked: int, clients_imported: int, packages_synced: int, duplicates_removed: int}
     */
    public function importAll(LegacyPortalSessionClient $client, bool $force = false, bool $resetLinks = false): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'linked' => 0,
            'clients_imported' => 0,
            'packages_synced' => 0,
            'duplicates_removed' => 0,
        ];

        if ($resetLinks) {
            $stats['duplicates_removed'] = $this->resetMacResellerLinks();
        }

        $page = $client->fetchMacResellersPage(0, 100);
        $baseUrl = config('legacy_portal.base_url');
        $username = config('legacy_portal.username');
        $password = config('legacy_portal.password');

        foreach ($page['data'] as $row) {
            $linkClient = new LegacyPortalSessionClient((string) $baseUrl, (string) $username, (string) $password);
            $linkClient->login();
            $result = $this->importRow($client, $row, $force, $linkClient);
            $stats[$result['action']]++;
            $stats['linked'] += $result['linked'];
            $stats['clients_imported'] += $result['clients_imported'];
            $stats['packages_synced'] += $result['packages_synced'];
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{action: 'imported'|'updated'|'skipped', linked: int, clients_imported: int, packages_synced: int}
     */
    public function importRow(
        LegacyPortalSessionClient $client,
        array $row,
        bool $force = false,
        ?LegacyPortalSessionClient $linkClient = null,
    ): array {
        $macId = (int) ($row['MACResellerId'] ?? 0);
        if ($macId < 1) {
            return ['action' => 'skipped', 'linked' => 0, 'clients_imported' => 0, 'packages_synced' => 0];
        }

        $code = trim((string) ($row['MACResellerCode'] ?? ''));
        if ($code === '') {
            $code = 'MAC-'.str_pad((string) $macId, 4, '0', STR_PAD_LEFT);
        }

        $existing = Reseller::query()
            ->where('tenant_id', $this->tenantId)
            ->where(function ($q) use ($code, $macId): void {
                $q->where('code', $code)
                    ->orWhere('meta->legacy_portal_mac_reseller_id', $macId);
            })
            ->first();

        $linkSession = $linkClient ?? $client;

        if ($existing !== null && ! $force) {
            $linkStats = $this->linkResellerClients($linkSession, $existing, $macId);

            return ['action' => 'skipped', 'linked' => $linkStats['linked'], 'clients_imported' => $linkStats['imported'], 'packages_synced' => $linkStats['packages_synced']];
        }

        $attrs = $this->mapResellerAttributes($row, $code, $macId);

        if ($existing !== null) {
            $existing->forceFill($attrs)->saveQuietly();
            $reseller = $existing;
            $action = 'updated';
        } else {
            $reseller = Reseller::query()->create($attrs);
            $action = 'imported';
        }

        $linkStats = $this->linkResellerClients($linkSession, $reseller, $macId);

        return [
            'action' => $action,
            'linked' => $linkStats['linked'],
            'clients_imported' => $linkStats['imported'],
            'packages_synced' => $linkStats['packages_synced'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapResellerAttributes(array $row, string $code, int $macId): array
    {
        $name = trim((string) (($row['MACResellerName'] ?? '') ?: ($row['ResellerCompanyName'] ?? 'Reseller')));
        $commission = $this->resolveCommissionPercent($row);

        return [
            'tenant_id' => $this->tenantId,
            'code' => $code,
            'name' => $name,
            'client_id_prefix' => filled($row['Tariff'] ?? null) ? trim((string) $row['Tariff']) : null,
            'franchise_type' => $this->mapFranchiseType($row),
            'contact_person' => trim((string) ($row['ContactPerson'] ?? '')),
            'phone' => trim((string) ($row['Mobile'] ?? '')),
            'email' => null,
            'address' => trim((string) ($row['ServerName'] ?? '')),
            'wallet_balance' => round((float) ($row['CurrentBalance'] ?? 0), 2),
            'max_clients' => max(0, (int) ($row['NumberOfClients'] ?? 0)),
            'is_active' => ! filter_var($row['IsArchived'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && ! filter_var($row['AccountLockedStatus'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'notes' => trim((string) (($row['LevelNumber'] ?? '').' · '.($row['ResellerCompanyName'] ?? ''))),
            'commission_type' => 'percent',
            'commission_value' => $commission,
            'revenue_share_percent' => $commission,
            'meta' => [
                'import_source' => 'legacy_portal',
                'legacy_portal_mac_reseller_id' => $macId,
                'legacy_portal_raw' => $row,
                'fund_start_status' => (bool) ($row['FundStartStatus'] ?? false),
                'minimum_balance' => (float) ($row['MinimumBalance'] ?? 0),
                'number_of_clients' => (int) ($row['NumberOfClients'] ?? 0),
                'number_of_enabled_clients' => (int) ($row['NumberOfEnabledClients'] ?? 0),
                'reseller_type_code' => (int) ($row['ResellerType'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCommissionPercent(array $row): float
    {
        foreach (['CommissionRate', 'CommissionPercent', 'Commission', 'DefaultCommission'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return round((float) $row[$key], 2);
            }
        }

        return round((float) config('legacy_portal.default_mac_reseller_commission_percent', 0), 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapFranchiseType(array $row): string
    {
        $type = (int) ($row['ResellerType'] ?? 0);

        return match ($type) {
            1 => ResellerType::SUB_RESELLER,
            2 => ResellerType::FRANCHISE,
            default => ResellerType::RESELLER,
        };
    }

    /**
     * @return array{linked: int, imported: int, packages_synced: int}
     */
    public function linkResellerClients(LegacyPortalSessionClient $client, Reseller $reseller, int $macResellerId): array
    {
        $linked = 0;
        $imported = 0;
        $start = 0;
        $length = 200;
        $allRows = [];

        do {
            $page = $client->fetchMacResellerClientsPage($macResellerId, $start, $length);
            $rows = $page['data'];
            $total = $page['iTotalDisplayRecords'];

            foreach ($rows as $row) {
                $allRows[] = $row;
                $customer = $this->matcher()->find($row);

                if ($customer === null) {
                    $customer = $this->importMacResellerClient($row, $reseller);
                    if ($customer !== null) {
                        $imported++;
                    }

                    continue;
                }

                $this->applyClientToCustomer($customer, $row, $reseller);

                if ((int) $customer->reseller_id === (int) $reseller->id) {
                    continue;
                }

                $linked++;
            }

            $start += $length;
        } while ($start < $total && $rows !== []);

        $packagesSynced = $this->packageSync()->syncFromClientRows($reseller, $allRows);

        return ['linked' => $linked, 'imported' => $imported, 'packages_synced' => $packagesSynced];
    }

    public function resetMacResellerLinks(): int
    {
        $macResellerIds = Reseller::query()
            ->whereNotNull('meta->legacy_portal_mac_reseller_id')
            ->pluck('id');

        if ($macResellerIds->isEmpty()) {
            return 0;
        }

        return Customer::query()
            ->whereIn('reseller_id', $macResellerIds)
            ->update(['reseller_id' => null]);
    }

    /**
     * Remove duplicate Sm* subscribers when a canonical numeric-code customer exists for same PPP/header.
     */
    public function mergeDuplicateMacClients(): int
    {
        $removed = 0;

        Customer::query()
            ->where('customer_code', 'like', 'Sm%')
            ->orderBy('id')
            ->each(function (Customer $duplicate) use (&$removed): void {
                $canonical = $this->findCanonicalForDuplicate($duplicate);
                if ($canonical === null || $canonical->id === $duplicate->id) {
                    return;
                }

                if ($duplicate->reseller_id !== null && $canonical->reseller_id === null) {
                    $canonical->forceFill(['reseller_id' => $duplicate->reseller_id])->saveQuietly();
                }

                $duplicate->forceFill([
                    'status' => 'terminated',
                    'network_access_state' => 'suspended',
                    'reseller_id' => null,
                    'notes' => trim(($duplicate->notes ?? '').' · Merged into '.$canonical->customer_code.' (legacy portal MAC import)'),
                ])->saveQuietly();

                $removed++;
            });

        return $removed;
    }

    private function findCanonicalForDuplicate(Customer $duplicate): ?Customer
    {
        $headerId = (int) data_get($duplicate->meta, 'legacy_portal_raw.CustomerHeaderId', 0);
        if ($headerId > 0) {
            $byHeader = Customer::query()
                ->fromLegacyPortal()
                ->where('meta->legacy_id', (string) $headerId)
                ->where('id', '!=', $duplicate->id)
                ->first();

            if ($byHeader !== null) {
                return $byHeader;
            }
        }

        $login = CustomerPppLoginResolver::normalize((string) ($duplicate->mikrotik_secret_name ?? ''));
        if ($login === '') {
            return null;
        }

        return Customer::query()
            ->where('id', '!=', $duplicate->id)
            ->where(function ($q) use ($login): void {
                $q->whereRaw('LOWER(mikrotik_secret_name) = ?', [$login])
                    ->orWhereRaw('LOWER(radius_username) = ?', [$login]);
            })
            ->where('customer_code', 'not like', 'Sm%')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyClientToCustomer(Customer $customer, array $row, Reseller $reseller): void
    {
        $importer = new LegacyPortalCustomerImporter($this->tenantId);
        $packageId = $importer->resolvePackageIdForRow([
            'PackageSpeed' => $row['PackageSpeed'] ?? '',
            'Package' => $row['PackageName'] ?? '',
            'PackageId' => $row['PackageId'] ?? null,
        ]);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['mac_reseller_id'] = $reseller->meta['legacy_portal_mac_reseller_id'] ?? null;
        $meta['legacy_portal_mac_client_raw'] = $row;
        if ($headerId = (int) ($row['CustomerHeaderId'] ?? 0)) {
            $meta['legacy_id'] = (string) $headerId;
        }

        $patch = [
            'reseller_id' => $reseller->id,
            'meta' => $meta,
        ];

        if ($packageId !== null) {
            $patch['package_id'] = $packageId;
        }

        $customer->forceFill($patch)->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importMacResellerClient(array $row, Reseller $reseller): ?Customer
    {
        $existing = $this->matcher()->find($row);
        if ($existing !== null) {
            $this->applyClientToCustomer($existing, $row, $reseller);

            return $existing;
        }

        $code = trim((string) ($row['CustomerId'] ?? ''));
        if ($code === '') {
            return null;
        }

        try {
            $mapped = [
                'CustomerId' => $code,
                'CustomerHeaderId' => $row['CustomerHeaderId'] ?? null,
                'UserName' => $row['UserName'] ?? $code,
                'Password' => $row['Password'] ?? null,
                'CustomerName' => $row['CustomerName'] ?? $code,
                'MobileNumber' => $row['MobileNumber'] ?? '',
                'ZoneName' => $row['Zone'] ?? '',
                'SubZoneName' => '',
                'ConnectionType' => $row['ConnectionType'] ?? '',
                'CustomerType' => $row['CustomerType'] ?? 'Home',
                'PackageSpeed' => $row['PackageSpeed'] ?? '',
                'PackageId' => $row['PackageId'] ?? null,
                'MonthlyBill' => 0,
                'MACAddress' => $row['MacAddress'] ?? '',
                'Server' => $row['Server'] ?? '',
                'Status' => $row['Status'] ?? 'Active',
                'ShortStatus' => strtolower((string) ($row['Status'] ?? 'active')),
                'Disabled' => filter_var($row['Disabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'Remarks' => $row['Remarks'] ?? '',
                'IsVIPClient' => false,
                'MACBindStatus' => false,
                'IsConnectedToMikrotik' => false,
                'IsOnline' => false,
                'ConnectivityStatus' => '',
            ];

            $customer = (new LegacyPortalCustomerImporter($this->tenantId))->importRow($mapped, true);
            $this->applyClientToCustomer($customer, $row, $reseller);

            return $customer;
        } catch (\Throwable) {
            return null;
        }
    }

    private function matcher(): LegacyPortalMacResellerCustomerMatcher
    {
        return $this->matcher ?? new LegacyPortalMacResellerCustomerMatcher;
    }

    private function packageSync(): LegacyPortalResellerPackageSyncService
    {
        return $this->packageSync ?? new LegacyPortalResellerPackageSyncService($this->tenantId);
    }
}
