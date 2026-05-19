<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Support\MacAddress;
use Illuminate\Support\Str;

final class CustomerOnuAutoProvisionService
{
    public function shouldSkipPlaceholderInventoryForTenant(int $tenantId): bool
    {
        if (! config('optical.prefer_bdcom_snmp_inventory', true)) {
            return false;
        }

        $min = (int) config('optical.skip_placeholder_when_bdcom_onus', 50);

        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->count() >= $min;
    }

    /**
     * Ensure the subscriber has an ONU device row (for optical sync / panel display).
     */
    public function ensureForCustomer(Customer $customer): ?Device
    {
        $this->ensureCpeDeviceFromPppSession($customer);

        $existing = $this->findOnuForCustomer($customer);
        if ($existing !== null && ! $this->isPlaceholderWithoutSignal($existing)) {
            return $existing;
        }

        if ($existing !== null && $this->isPlaceholderWithoutSignal($existing)) {
            $existing->forceFill(['customer_id' => null, 'status' => 'inventory'])->saveQuietly();
        }

        $byMac = CustomerOnuMatcher::linkCustomerByMacFromOlt(
            $customer,
            config('optical.auto_sync_olt_on_mac_lookup', true),
        );
        if ($byMac !== null) {
            return $byMac;
        }

        $smart = app(CustomerOnuSmartLinkService::class)->findConfidentMatch($customer);
        if ($smart['onu'] !== null && $smart['reason'] !== null) {
            return $this->finalizeOnuLink(
                $customer,
                $smart['onu'],
                $smart['reason'],
                $smart['score'],
            );
        }

        $tenantId = (int) $customer->tenant_id;

        $byLogin = CustomerOnuMatcher::findUnlinkedOnuForLogin($tenantId, $customer);
        if ($byLogin !== null) {
            return $this->finalizeOnuLink(
                $customer,
                $byLogin,
                CustomerOnuSmartLinkService::REASON_LOGIN_EXACT,
                100,
            );
        }

        $byEpon = CustomerOnuMatcher::findUnlinkedOnuForEponHints($tenantId, $customer);
        if ($byEpon !== null) {
            return $this->finalizeOnuLink(
                $customer,
                $byEpon,
                CustomerOnuSmartLinkService::REASON_EPON_EXACT,
                92,
            );
        }

        foreach (CustomerOnuMatcher::macCandidatesForCustomer($customer) as $macCompact) {
            $byMac = CustomerOnuMatcher::findUnlinkedOnuForMac($tenantId, $macCompact);
            if ($byMac !== null) {
                return $this->finalizeOnuLink(
                    $customer,
                    $byMac,
                    CustomerOnuSmartLinkService::REASON_ONU_MAC_EXACT,
                    98,
                );
            }
        }

        if (! config('optical.auto_provision_customer_onu', true)) {
            return null;
        }

        if ($this->shouldSkipPlaceholderInventoryForTenant((int) $customer->tenant_id)) {
            return null;
        }

        return $this->createPlaceholderOnu($customer);
    }

    /**
     * @return array{linked: int, by_mac: int, by_login: int, skipped: int}
     */
    public function autoLinkCustomersToOnus(int $tenantId, int $limit = 2000): array
    {
        $result = app(CustomerOnuSmartLinkService::class)->smartRelinkTenant($tenantId, true);

        return [
            'linked' => $result['linked'],
            'by_mac' => $result['by_reason'][CustomerOnuSmartLinkService::REASON_ONU_MAC_EXACT] ?? 0,
            'by_login' => ($result['by_reason'][CustomerOnuSmartLinkService::REASON_LOGIN_EXACT] ?? 0)
                + ($result['by_reason'][CustomerOnuSmartLinkService::REASON_DESC_EXACT] ?? 0),
            'skipped' => $result['skipped'] + $result['conflicts'],
            'pruned' => $result['pruned'],
        ];
    }

    /**
     * @return array{linked: int, skipped: int}
     */
    public function linkCustomersToOnusByMac(int $tenantId, int $limit = 2000): array
    {
        $result = $this->autoLinkCustomersToOnus($tenantId, $limit);

        return [
            'linked' => $result['linked'],
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * @return array{created: int, linked: int, skipped: int}
     */
    public function provisionMissingForTenant(int $tenantId, int $limit = 500): array
    {
        $stats = ['created' => 0, 'linked' => 0, 'skipped' => 0];

        if (! config('optical.auto_provision_customer_onu', true)) {
            return $stats;
        }

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDoesntHave('devices', fn ($q) => $q->where('type', 'onu'))
            ->orderBy('id')
            ->limit($limit)
            ->each(function (Customer $customer) use (&$stats): void {
                $before = $this->findOnuForCustomer($customer);
                $onu = $this->ensureForCustomer($customer);
                if ($onu === null) {
                    $stats['skipped']++;

                    return;
                }
                if ($before !== null) {
                    return;
                }
                if ($onu->wasRecentlyCreated) {
                    $stats['created']++;
                } else {
                    $stats['linked']++;
                }
            });

        return $stats;
    }

    public function findOnuForCustomer(Customer $customer): ?Device
    {
        if ($customer->relationLoaded('devices')) {
            return $customer->devices->firstWhere('type', 'onu');
        }

        return $customer->devices()->where('type', 'onu')->orderByDesc('id')->first();
    }

    /**
     * Try every strategy (EPON notes, MAC, login) — use on subscriber view load.
     */
    public function autoFindAndLinkOnu(Customer $customer): ?Device
    {
        return $this->ensureForCustomer($customer);
    }

    public function assignOnuToCustomer(
        Customer $customer,
        int $onuDeviceId,
        string $linkedBy = 'manual',
        ?int $matchScore = null,
    ): ?Device {
        $onu = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('type', 'onu')
            ->find($onuDeviceId);

        if ($onu === null) {
            return null;
        }

        if ($onu->customer_id !== null && (int) $onu->customer_id !== (int) $customer->id) {
            return null;
        }

        Device::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'onu')
            ->where('id', '!=', $onu->id)
            ->update(['customer_id' => null]);

        return $this->finalizeOnuLink($customer, $onu, $linkedBy, $matchScore);
    }

    private function finalizeOnuLink(
        Customer $customer,
        Device $onu,
        string $linkedBy,
        ?int $matchScore = null,
    ): Device {
        $login = $customer->pppLoginName();
        $meta = array_merge(
            is_array($onu->meta) ? $onu->meta : [],
            CustomerOnuMatcher::linkMetaForCustomer($customer, $linkedBy),
        );
        if ($matchScore !== null) {
            $meta['match_score'] = $matchScore;
        }

        $onu->forceFill([
            'customer_id' => $customer->id,
            'status' => 'assigned',
            'onu_external_id' => $onu->onu_external_id ?: ($login !== '' ? $login : null),
            'meta' => $meta,
        ])->save();

        $this->syncFramedIpFromSession($customer);
        $this->ensureCpeDeviceFromPppSession($customer);

        return $onu->fresh();
    }

    public function ensureCpeDeviceFromPppSession(Customer $customer): ?Device
    {
        $session = $customer->relationLoaded('activePppSession')
            ? $customer->activePppSession
            : $customer->activePppSession()->first();

        if ($session === null) {
            return null;
        }

        $macColon = MacAddress::normalizeColon($session->caller_id);
        $ip = filled($session->framed_ip) ? (string) $session->framed_ip : null;

        if ($macColon === null && $ip === null) {
            return null;
        }

        $cpe = $customer->devices()
            ->where('type', '!=', 'olt')
            ->where('type', '!=', 'onu')
            ->whereNotNull('mac_address')
            ->first();

        if ($cpe === null && $macColon !== null) {
            $cpe = $customer->devices()
                ->where('type', '!=', 'olt')
                ->where('type', '!=', 'onu')
                ->first();
        }

        if ($cpe === null) {
            $serial = MacAddress::normalizeCompact($macColon)
                ?? 'CPE-'.$customer->id;

            $cpe = Device::query()->create([
                'tenant_id' => $customer->tenant_id,
                'type' => 'router',
                'customer_id' => $customer->id,
                'serial_number' => $serial,
                'display_name' => $customer->pppLoginName() ?: $customer->name,
                'mac_address' => $macColon,
                'framed_ip_address' => $ip,
                'status' => 'assigned',
                'meta' => [
                    'source' => 'ppp_session',
                    'synced_at' => now()->toIso8601String(),
                ],
            ]);

            return $cpe;
        }

        $updates = [];
        if ($macColon !== null && blank($cpe->mac_address)) {
            $updates['mac_address'] = $macColon;
        }
        if ($ip !== null && blank($cpe->framed_ip_address)) {
            $updates['framed_ip_address'] = $ip;
        }
        if ($updates !== []) {
            $cpe->forceFill($updates)->save();
        }

        return $cpe->fresh();
    }

    /**
     * @return array{onu: ?Device, ppp_mac: ?string, olt_onu_count: int, hint: ?string}
     */
    public function opticalLinkDiagnostics(Customer $customer): array
    {
        $session = $customer->relationLoaded('activePppSession')
            ? $customer->activePppSession
            : $customer->activePppSession()->first();

        $pppMac = MacAddress::normalizeColon($session?->caller_id);
        $oltCount = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('type', 'onu')
            ->count();

        $onu = $this->findOnuForCustomer($customer);
        $hint = null;

        if ($onu === null) {
            $login = $customer->pppLoginName();
            if ($oltCount > 0) {
                $hint = "Auto link হয়নি — router MAC (PPP) OLT-এ ONU MAC নয়। একবার EPON port (যেমন EPON0/4:29) বা ONU MAC দিন → Save; অথবা OLT inventory থেকে ONU বেছে নিন। OLT description = «{$login}» হলে cron-এও auto হবে।";
            } else {
                $hint = 'OLT inventory খালি — BDCOM sync চালান, তারপর ONU link করুন।';
            }
        }

        return [
            'onu' => $onu,
            'ppp_mac' => $pppMac,
            'olt_onu_count' => $oltCount,
            'hint' => $hint,
        ];
    }

    private function syncFramedIpFromSession(Customer $customer): void
    {
        $session = $customer->relationLoaded('activePppSession')
            ? $customer->activePppSession
            : $customer->activePppSession()->first();

        if ($session === null || blank($session->framed_ip)) {
            return;
        }

        $customer->devices()
            ->where('type', '!=', 'olt')
            ->whereNull('framed_ip_address')
            ->update(['framed_ip_address' => $session->framed_ip]);
    }

    private function createPlaceholderOnu(Customer $customer): ?Device
    {
        $olt = $this->resolveDefaultOlt((int) $customer->tenant_id);
        if ($olt === null) {
            return null;
        }

        $login = $customer->pppLoginName();
        $serial = $this->uniqueSerialForTenant((int) $customer->tenant_id, 'SUB-'.$customer->id);

        return Device::query()->create([
            'tenant_id' => $customer->tenant_id,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'customer_id' => $customer->id,
            'serial_number' => $serial,
            'display_name' => $customer->name,
            'onu_external_id' => $login !== '' ? $login : null,
            'connection_type' => 'optical_fiber',
            'status' => 'assigned',
            'onu_oper_status' => 'unknown',
            'meta' => [
                'auto_provisioned' => true,
                'ppp_login' => $login,
                'provisioned_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function resolveDefaultOlt(int $tenantId): ?Device
    {
        $configured = config('optical.default_olt_id');
        if ($configured) {
            $olt = Device::query()->withoutGlobalScopes()->olts()->find((int) $configured);
            if ($olt !== null && (int) $olt->tenant_id === $tenantId) {
                return $olt;
            }
        }

        return Device::query()
            ->withoutGlobalScopes()
            ->olts()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'decommissioned')
            ->orderBy('id')
            ->first();
    }

    private function isPlaceholderWithoutSignal(Device $onu): bool
    {
        if ($onu->rx_power_dbm !== null) {
            return false;
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];

        return ! empty($meta['auto_provisioned'])
            || str_starts_with((string) $onu->serial_number, 'SUB-');
    }

    private function uniqueSerialForTenant(int $tenantId, string $base): string
    {
        $serial = Str::limit($base, 120, '');
        if (! Device::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('serial_number', $serial)->exists()) {
            return $serial;
        }

        $suffix = 1;
        do {
            $candidate = Str::limit($base.'-'.$suffix, 120, '');
            $suffix++;
        } while (Device::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('serial_number', $candidate)->exists());

        return $candidate;
    }
}
