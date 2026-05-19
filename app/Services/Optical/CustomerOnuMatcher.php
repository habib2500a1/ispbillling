<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Models\PppSessionLog;
use App\Support\CustomerPppLoginResolver;
use App\Support\EponLabel;
use App\Support\MacAddress;
use App\Support\MikrotikOpticalHints;
use Illuminate\Support\Str;

final class CustomerOnuMatcher
{
    public static function matchCustomerByOnuMac(int $tenantId, string $mac, string $macCompact): ?Customer
    {
        $variants = MacAddress::variants($macCompact !== '' ? $macCompact : $mac);

        $device = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', '!=', 'olt')
            ->whereNotNull('customer_id')
            ->where(function ($q) use ($variants, $mac, $macCompact): void {
                foreach ($variants as $variant) {
                    $q->orWhere('mac_address', $variant);
                }
                if ($mac !== '') {
                    $q->orWhere('mac_address', $mac);
                }
                if ($macCompact !== '') {
                    $q->orWhereRaw('REPLACE(LOWER(mac_address), \':\', \'\') = ?', [strtolower($macCompact)]);
                }
            })
            ->first();

        if ($device?->customer_id) {
            return Customer::query()->withoutGlobalScopes()->find($device->customer_id);
        }

        $session = PppSessionLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('customer_id')
            ->where(function ($q) use ($variants, $macCompact): void {
                foreach ($variants as $variant) {
                    $q->orWhere('caller_id', $variant)
                        ->orWhereRaw('REPLACE(UPPER(caller_id), \':\', \'\') = ?', [strtoupper($variant)]);
                }
                if ($macCompact !== '') {
                    $q->orWhereRaw('REPLACE(UPPER(caller_id), \':\', \'\') = ?', [strtoupper($macCompact)]);
                }
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('started_at')
            ->first();

        if ($session?->customer_id) {
            return Customer::query()->withoutGlobalScopes()->find($session->customer_id);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function macCandidatesForCustomer(Customer $customer): array
    {
        $candidates = [];

        $session = $customer->relationLoaded('activePppSession')
            ? $customer->activePppSession
            : $customer->activePppSession()->first();

        if ($session?->caller_id) {
            $candidates[] = (string) $session->caller_id;
        }

        $devices = $customer->relationLoaded('devices')
            ? $customer->devices
            : $customer->devices()->where('type', '!=', 'olt')->get();

        foreach ($devices as $device) {
            if (filled($device->mac_address)) {
                $candidates[] = (string) $device->mac_address;
            }
        }

        $latestSession = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('caller_id')
            ->orderByDesc('started_at')
            ->value('caller_id');

        if (filled($latestSession)) {
            $candidates[] = (string) $latestSession;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        foreach ([
            'onu_mac',
            'mac_binding',
            'cpe_mac',
            'router_mac',
            'mikrotik_caller_id',
            'mikrotik_last_caller_id',
        ] as $key) {
            if (filled($meta[$key] ?? null)) {
                $candidates[] = (string) $meta[$key];
            }
        }

        if (filled($meta['mikrotik_comment'] ?? null)) {
            $candidates = array_merge($candidates, MikrotikOpticalHints::extractMacsFromText((string) $meta['mikrotik_comment']));
        }

        return MacAddress::parseMacInputs(...$candidates);
    }

    public static function findUnlinkedOnuForMac(int $tenantId, string $macCompact): ?Device
    {
        return self::findOnuForMac($tenantId, $macCompact, unlinkedOnly: true);
    }

    public static function findOnuForMac(int $tenantId, string $mac, bool $unlinkedOnly = false): ?Device
    {
        $compact = MacAddress::normalizeCompact($mac);
        if ($compact === null) {
            return null;
        }

        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->when($unlinkedOnly, fn ($q) => $q->whereNull('customer_id'))
            ->tap(fn ($q) => MacAddress::applyOnuMacMatch($q, $compact))
            ->orderByDesc('rx_power_dbm')
            ->orderByDesc('last_polled_at')
            ->first();
    }

    /**
     * Try every known MAC (PPP, form fields) against OLT inventory; optionally sync OLT first.
     */
    public static function linkCustomerByMacFromOlt(Customer $customer, bool $syncOltFirst = false): ?Device
    {
        $tenantId = (int) $customer->tenant_id;
        $candidates = self::macCandidatesForCustomer($customer);

        if ($candidates === []) {
            return null;
        }

        $tryFind = function () use ($tenantId, $candidates, $customer): ?Device {
            foreach ($candidates as $compact) {
                $onu = self::findOnuForMac($tenantId, $compact, unlinkedOnly: true);
                if ($onu !== null) {
                    return app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer(
                        $customer,
                        (int) $onu->id,
                        CustomerOnuSmartLinkService::REASON_ONU_MAC_EXACT,
                        98,
                    );
                }
            }

            return null;
        };

        $linked = $tryFind();
        if ($linked !== null || ! $syncOltFirst) {
            return $linked;
        }

        if (config('optical.auto_sync_olt_on_mac_lookup', true)) {
            app(IspDigitalOnuPipelineService::class)->syncAllBdcomOlts($tenantId);
        }

        return $tryFind();
    }

    /**
     * @return list<string>
     */
    public static function loginCandidatesForCustomer(Customer $customer): array
    {
        $candidates = [
            $customer->pppLoginName(),
            $customer->mikrotik_secret_name,
            $customer->radius_username,
            $customer->customer_code,
            $customer->phone,
        ];

        $session = $customer->relationLoaded('activePppSession')
            ? $customer->activePppSession
            : $customer->activePppSession()->first();

        if (filled($session?->username)) {
            $candidates[] = (string) $session->username;
        }

        $unique = [];
        foreach ($candidates as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $unique[Str::lower($value)] = $value;
            $normalized = CustomerPppLoginResolver::normalize($value);
            if ($normalized !== '') {
                $unique[$normalized] = $value;
            }
        }

        return array_values($unique);
    }

    /**
     * @return list<string>
     */
    public static function eponHintsForCustomer(Customer $customer): array
    {
        $hints = EponLabel::extractFromText($customer->notes);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        foreach (['epon_port', 'onu_port', 'pon_port'] as $key) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $hints = array_merge($hints, EponLabel::extractFromText($value));
            $normalized = EponLabel::normalize($value);
            if ($normalized !== null) {
                $hints[] = $normalized;
            }
        }

        if (filled($meta['mikrotik_comment'] ?? null)) {
            $hints = array_merge($hints, EponLabel::extractFromText((string) $meta['mikrotik_comment']));
        }

        foreach (self::loginCandidatesForCustomer($customer) as $login) {
            $hints = array_merge($hints, EponLabel::extractFromText($login));
        }

        return array_values(array_unique(array_filter($hints)));
    }

    public static function findUnlinkedOnuByEponLabel(int $tenantId, string $label): ?Device
    {
        $normalized = EponLabel::normalize($label);
        if ($normalized === null) {
            return null;
        }

        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNull('customer_id')
            ->where(function ($q) use ($normalized): void {
                $q->where('display_name', $normalized)
                    ->orWhere('display_name', 'ilike', $normalized)
                    ->orWhere('meta->bdcom_label', $normalized);
            })
            ->orderByDesc('rx_power_dbm')
            ->orderByDesc('last_polled_at')
            ->first();
    }

    public static function findUnlinkedOnuForEponHints(int $tenantId, Customer $customer): ?Device
    {
        foreach (self::eponHintsForCustomer($customer) as $label) {
            $onu = self::findUnlinkedOnuByEponLabel($tenantId, $label);
            if ($onu !== null) {
                return $onu;
            }
        }

        return null;
    }

    public static function findUnlinkedOnuForLogin(int $tenantId, Customer $customer): ?Device
    {
        foreach (self::loginCandidatesForCustomer($customer) as $login) {
            $onu = self::findUnlinkedOnuByLoginValue($tenantId, $login);
            if ($onu !== null) {
                return $onu;
            }
        }

        return null;
    }

    public static function findUnlinkedOnuByLoginValue(int $tenantId, string $login): ?Device
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        $normalized = CustomerPppLoginResolver::normalize($login);
        $compactMac = MacAddress::normalizeCompact($login);

        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNull('customer_id')
            ->where(function ($q) use ($login, $normalized, $compactMac): void {
                $q->where('serial_number', $login)
                    ->orWhere('onu_external_id', $login)
                    ->orWhere('display_name', $login);

                if ($normalized !== '' && $normalized !== Str::lower($login)) {
                    $q->orWhereRaw('LOWER(serial_number) = ?', [$normalized])
                        ->orWhereRaw('LOWER(onu_external_id) = ?', [$normalized])
                        ->orWhereRaw('LOWER(display_name) = ?', [$normalized]);
                }

                $q->orWhere('meta->ppp_login', $login)
                    ->orWhere('meta->subscriber_login', $login)
                    ->orWhere('meta->username', $login)
                    ->orWhereRaw("LOWER(COALESCE(meta->>'bdcom_description', '')) = ?", [Str::lower($login)]);

                if ($normalized !== '' && $normalized !== Str::lower($login)) {
                    $q->orWhere('meta->ppp_login', $normalized)
                        ->orWhere('meta->subscriber_login', $normalized)
                        ->orWhereRaw("LOWER(COALESCE(meta->>'bdcom_description', '')) = ?", [Str::lower($normalized)]);
                }

                if ($compactMac !== null) {
                    $q->orWhere('serial_number', $compactMac);
                }
            })
            ->orderByDesc('rx_power_dbm')
            ->orderByDesc('last_polled_at')
            ->first();
    }

    /**
     * Unlinked ONUs that might belong to this subscriber (partial login / EPON hints).
     *
     * @return list<array{onu: Device, reason: string, score: int}>
     */
    public static function suggestOnusForCustomer(Customer $customer, int $limit = 8): array
    {
        $tenantId = (int) $customer->tenant_id;
        $suggestions = [];
        $seen = [];

        foreach (self::loginCandidatesForCustomer($customer) as $login) {
            $login = trim($login);
            if ($login === '' || strlen($login) < 3) {
                continue;
            }

            $candidates = Device::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', 'onu')
                ->whereNull('customer_id')
                ->where(function ($q) use ($login): void {
                    $like = '%'.Str::lower($login).'%';
                    $q->whereRaw("LOWER(COALESCE(meta->>'bdcom_description', '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(meta->>'ppp_login', '')) LIKE ?", [$like])
                        ->orWhereRaw('LOWER(COALESCE(onu_external_id, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$like]);
                })
                ->orderByDesc('rx_power_dbm')
                ->limit($limit)
                ->get();

            foreach ($candidates as $onu) {
                if (isset($seen[$onu->id])) {
                    continue;
                }
                $seen[$onu->id] = true;
                $meta = is_array($onu->meta) ? $onu->meta : [];
                $suggestions[] = [
                    'onu' => $onu,
                    'reason' => 'Login match: '.($meta['bdcom_description'] ?? $login),
                    'score' => 75,
                ];
            }
        }

        foreach (self::eponHintsForCustomer($customer) as $label) {
            $onu = self::findUnlinkedOnuByEponLabel($tenantId, $label);
            if ($onu !== null && ! isset($seen[$onu->id])) {
                $seen[$onu->id] = true;
                $suggestions[] = [
                    'onu' => $onu,
                    'reason' => 'EPON: '.$label,
                    'score' => 80,
                ];
            }
        }

        usort($suggestions, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, $limit);
    }

    public static function matchCustomerForOnuDevice(int $tenantId, Device $onu): ?Customer
    {
        if ($onu->mac_address) {
            $compact = strtoupper(str_replace(':', '', (string) $onu->mac_address));
            $customer = self::matchCustomerByOnuMac($tenantId, (string) $onu->mac_address, $compact);
            if ($customer !== null) {
                return $customer;
            }
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $loginHints = array_filter([
            $onu->onu_external_id,
            $meta['ppp_login'] ?? null,
            $meta['subscriber_login'] ?? null,
            $meta['username'] ?? null,
            self::looksLikeLoginSerial((string) $onu->serial_number) ? $onu->serial_number : null,
        ]);

        foreach ($loginHints as $hint) {
            $customer = CustomerPppLoginResolver::resolve($tenantId, (string) $hint);
            if ($customer !== null) {
                return $customer;
            }
        }

        return null;
    }

    public static function looksLikeLoginSerial(string $serial): bool
    {
        $serial = trim($serial);
        if ($serial === '' || strlen($serial) < 3) {
            return false;
        }

        if (MacAddress::normalizeCompact($serial) !== null && strlen(preg_replace('/[^A-Fa-f0-9]/', '', $serial) ?? '') >= 12) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9_.@-]+$/', $serial);
    }

    /**
     * @return array{ppp_login: string, linked_by: string}
     */
    public static function linkMetaForCustomer(Customer $customer, string $linkedBy): array
    {
        return [
            'ppp_login' => $customer->pppLoginName(),
            'subscriber_login' => $customer->pppLoginName(),
            'linked_by' => $linkedBy,
            'linked_at' => now()->toIso8601String(),
        ];
    }
}
