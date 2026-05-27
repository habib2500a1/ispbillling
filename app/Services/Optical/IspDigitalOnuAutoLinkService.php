<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikServer;
use App\Models\PppSessionLog;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Services\Mikrotik\MikrotikPppImportService;
use App\Support\CustomerPppLoginResolver;
use App\Support\MacAddress;
use App\Support\MikrotikOpticalHints;
use Illuminate\Support\Facades\Log;

/**
 * ISP Digital–style auto link: MikroTik hints → OLT inventory → subscriber names on optical grid.
 */
final class IspDigitalOnuAutoLinkService
{
    public function __construct(
        private readonly CustomerOnuAutoProvisionService $provision,
        private readonly CustomerOnuSmartLinkService $smartLink,
        private readonly MikrotikPppImportService $mikrotikImport,
    ) {}

    /**
     * @return array{
     *   ppp_sessions_fetched: int,
     *   ppp_online: int,
     *   mikrotik_enriched: int,
     *   ppp_customer_linked: int,
     *   ppp_session_linked: int,
     *   hint_linked: int,
     *   smart_linked: int,
     *   linked: int
     * }
     */
    public function runAfterOltSync(int $tenantId): array
    {
        $stats = [
            'ppp_sessions_fetched' => 0,
            'ppp_online' => 0,
            'mikrotik_enriched' => 0,
            'ppp_customer_linked' => 0,
            'ppp_session_linked' => 0,
            'hint_linked' => 0,
            'smart_linked' => 0,
            'linked' => 0,
        ];

        if (! config('optical.auto_link_on_bdcom_sync', true)) {
            return $stats;
        }

        if (config('optical.auto_fetch_ppp_sessions', true)
            && config('bandwidth.collection_enabled', true)) {
            $ppp = $this->fetchActivePppSessionsFromRouters($tenantId);
            $stats['ppp_sessions_fetched'] = (int) ($ppp['sessions'] ?? 0);
            $stats['ppp_online'] = (int) ($ppp['online_customers'] ?? 0);
        }

        if (config('optical.mikrotik_optical_bridge_enabled', true)) {
            $stats['mikrotik_enriched'] = $this->enrichCustomersFromMikrotikSecrets($tenantId);
        }

        $stats['ppp_customer_linked'] = $this->linkCustomersFromActivePppSessions($tenantId);
        $stats['ppp_session_linked'] = $this->linkByRecentPppSessions($tenantId);
        $stats['hint_linked'] = $this->linkByOnuClientCodeDescription($tenantId)
            + $this->linkCustomersWithOpticalHints($tenantId);

        $stats['macs_learned'] = $this->learnMacsFromSessions($tenantId);

        $smart = $this->smartLink->smartRelinkTenant($tenantId, true);
        $stats['smart_linked'] = (int) ($smart['linked'] ?? 0);
        $stats['linked'] = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('customer_id')
            ->count();

        Log::info('isp_digital_onu.auto_link_complete', array_merge(['tenant_id' => $tenantId], $stats));

        return $stats;
    }

    public function learnMacsFromSessions(int $tenantId): int
    {
        $learned = 0;

        Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('customer_id')
            ->where(fn ($q) => $q->whereNull('mac_address')->orWhere('mac_address', ''))
            ->with(['customer.activePppSession'])
            ->chunkById(100, function ($onus) use (&$learned): void {
                foreach ($onus as $onu) {
                    $sessionMac = $onu->customer?->activePppSession?->caller_id;
                    if (blank($sessionMac)) {
                        continue;
                    }

                    $normalized = MacAddress::normalizeColon($sessionMac);
                    if ($normalized) {
                        $onu->forceFill(['mac_address' => $normalized])->save();
                        $learned++;
                    }
                }
            });

        return $learned;
    }

    public function enrichCustomersFromMikrotikSecrets(int $tenantId): int
    {
        $updated = 0;

        $servers = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->get();

        foreach ($servers as $server) {
            try {
                $secrets = $this->mikrotikImport->listSecretsFromRouter($server);
            } catch (\Throwable $e) {
                Log::warning('isp_digital_onu.mikrotik_secrets_failed', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($secrets as $secret) {
                $name = trim((string) ($secret['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $customer = CustomerPppLoginResolver::resolve($tenantId, $name, (int) $server->id);
                if ($customer === null) {
                    continue;
                }

                if ($this->applyMikrotikHintsToCustomer($customer, $secret)) {
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $secret
     */
    private function applyMikrotikHintsToCustomer(Customer $customer, array $secret): bool
    {
        $hints = MikrotikOpticalHints::fromPppSecret($secret);
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $changed = false;

        if (filled($hints['comment']) && ($meta['mikrotik_comment'] ?? '') !== $hints['comment']) {
            $meta['mikrotik_comment'] = $hints['comment'];
            $changed = true;
        }

        if (filled($hints['comment']) && preg_match('/client\s*code\s*:\s*(\S+)/i', (string) $hints['comment'], $m)) {
            $code = trim($m[1]);
            if ($code !== '' && blank($meta['isp_digital_client_code_hint'] ?? null)) {
                $meta['isp_digital_client_code_hint'] = $code;
                $changed = true;
            }
        }

        if (filled($hints['last_caller_id']) && blank($meta['mac_binding'] ?? null)) {
            $meta['mac_binding'] = $hints['last_caller_id'];
            $changed = true;
        }

        $primaryEpon = $hints['epon_ports'][0] ?? null;
        if ($primaryEpon !== null && blank($meta['epon_port'] ?? null)) {
            $meta['epon_port'] = $primaryEpon;
            $changed = true;
        }

        $routerCompact = MacAddress::normalizeCompact($hints['last_caller_id'] ?? $hints['caller_id'] ?? null);
        foreach ($hints['mac_compacts'] as $compact) {
            if ($routerCompact !== null && $compact === $routerCompact) {
                continue;
            }
            $onuMac = MacAddress::normalizeColon($compact);
            if ($onuMac !== null && blank($meta['onu_mac'] ?? null)) {
                $meta['onu_mac'] = $onuMac;
                $changed = true;
                break;
            }
        }

        $meta['mikrotik_optical_synced_at'] = now()->toIso8601String();
        $customer->forceFill(['meta' => $meta])->saveQuietly();

        return $changed;
    }

    /**
     * @return array{sessions: int, online_customers: int, matched: int}
     */
    public function fetchActivePppSessionsFromRouters(int $tenantId): array
    {
        $collector = app(BandwidthCollectionService::class);

        if (! $collector->tenantHasEnabledMikrotik($tenantId)) {
            return ['sessions' => 0, 'online_customers' => 0, 'matched' => 0];
        }

        try {
            $result = $collector->collectForTenant($tenantId);
        } catch (\Throwable $e) {
            Log::warning('isp_digital_onu.ppp_collect_failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return ['sessions' => 0, 'online_customers' => 0, 'matched' => 0];
        }

        return [
            'sessions' => (int) ($result['api_sessions'] ?? $result['merged_users'] ?? $result['sessions_open'] ?? 0),
            'online_customers' => (int) ($result['matched_subscribers'] ?? 0),
            'matched' => (int) ($result['matched_subscribers'] ?? 0),
        ];
    }

    /**
     * After MikroTik collect: attach router MAC/IP to customer + try ONU link by login/MAC/EPON.
     */
    public function linkCustomersFromActivePppSessions(int $tenantId): int
    {
        $linked = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereHas('activePppSession')
            ->with(['activePppSession', 'mikrotikServer'])
            ->orderBy('id')
            ->chunkById(100, function ($customers) use ($tenantId, &$linked): void {
                foreach ($customers as $customer) {
                    if ($this->provision->findOnuForCustomer($customer) !== null) {
                        continue;
                    }

                    $session = $customer->activePppSession;
                    if ($session !== null && filled($session->caller_id)) {
                        $meta = is_array($customer->meta) ? $customer->meta : [];
                        if (blank($meta['mac_binding'] ?? null)) {
                            $meta['mac_binding'] = $session->caller_id;
                            $customer->forceFill(['meta' => $meta])->saveQuietly();
                        }
                    }

                    $this->provision->ensureCpeDeviceFromPppSession($customer);

                    $onu = CustomerOnuMatcher::linkCustomerByMacFromOlt($customer->fresh(), false);
                    if ($onu === null) {
                        $onu = CustomerOnuMatcher::findUnlinkedOnuForLogin($tenantId, $customer);
                        if ($onu !== null) {
                            $onu = $this->provision->assignOnuToCustomer(
                                $customer,
                                (int) $onu->id,
                                CustomerOnuSmartLinkService::REASON_LOGIN_EXACT,
                                100,
                            );
                        }
                    }

                    if ($onu === null) {
                        $match = $this->smartLink->findConfidentMatch($customer);
                        if ($match['onu'] !== null && $match['reason'] !== null) {
                            $onu = $this->provision->assignOnuToCustomer(
                                $customer,
                                (int) $match['onu']->id,
                                $match['reason'],
                                $match['score'],
                            );
                        }
                    }

                    if ($onu !== null) {
                        $linked++;
                    }
                }
            });

        return $linked;
    }

    public function linkByOnuClientCodeDescription(int $tenantId): int
    {
        $linked = 0;

        Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNull('customer_id')
            ->orderBy('id')
            ->chunkById(100, function ($onus) use ($tenantId, &$linked): void {
                foreach ($onus as $onu) {
                    $meta = is_array($onu->meta) ? $onu->meta : [];
                    $description = trim((string) ($meta['bdcom_description'] ?? ''));
                    if ($description === ''
                        || \App\Support\BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel($description)) {
                        continue;
                    }

                    $customer = Customer::query()
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('customer_code', $description)
                        ->first();

                    if ($customer === null) {
                        continue;
                    }

                    if ($this->provision->findOnuForCustomer($customer) !== null) {
                        continue;
                    }

                    $this->provision->assignOnuToCustomer(
                        $customer,
                        (int) $onu->id,
                        CustomerOnuSmartLinkService::REASON_DESC_EXACT,
                        100,
                    );
                    $linked++;
                }
            });

        return $linked;
    }

    public function linkByRecentPppSessions(int $tenantId): int
    {
        $linked = 0;

        $sessions = PppSessionLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('customer_id')
            ->whereNotNull('caller_id')
            ->orderByDesc('started_at')
            ->get(['id', 'customer_id', 'caller_id']);

        $seenCustomers = [];

        foreach ($sessions as $session) {
            $customerId = (int) $session->customer_id;
            if (isset($seenCustomers[$customerId])) {
                continue;
            }
            $seenCustomers[$customerId] = true;

            $customer = Customer::query()->withoutGlobalScopes()->find($customerId);
            if ($customer === null || $this->provision->findOnuForCustomer($customer) !== null) {
                continue;
            }

            $compact = MacAddress::normalizeCompact((string) $session->caller_id);
            if ($compact === null) {
                continue;
            }

            $onu = CustomerOnuMatcher::findUnlinkedOnuForMac($tenantId, $compact);
            if ($onu === null) {
                continue;
            }

            $this->provision->assignOnuToCustomer(
                $customer,
                (int) $onu->id,
                CustomerOnuSmartLinkService::REASON_ONU_MAC_EXACT,
                96,
            );
            $linked++;
        }

        return $linked;
    }

    public function linkCustomersWithOpticalHints(int $tenantId): int
    {
        $linked = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDoesntHave('devices', fn ($q) => $q->where('type', 'onu')->whereNotNull('customer_id'))
            ->orderBy('id')
            ->chunkById(100, function ($customers) use ($tenantId, &$linked): void {
                foreach ($customers as $customer) {
                    if ($this->provision->findOnuForCustomer($customer) !== null) {
                        continue;
                    }

                    $byEpon = CustomerOnuMatcher::findUnlinkedOnuForEponHints($tenantId, $customer);
                    if ($byEpon !== null) {
                        $this->provision->assignOnuToCustomer(
                            $customer,
                            (int) $byEpon->id,
                            CustomerOnuSmartLinkService::REASON_EPON_EXACT,
                            92,
                        );
                        $linked++;

                        continue;
                    }

                    $byLogin = CustomerOnuMatcher::findUnlinkedOnuForLogin($tenantId, $customer);
                    if ($byLogin !== null) {
                        $this->provision->assignOnuToCustomer(
                            $customer,
                            (int) $byLogin->id,
                            CustomerOnuSmartLinkService::REASON_LOGIN_EXACT,
                            100,
                        );
                        $linked++;

                        continue;
                    }

                    foreach (CustomerOnuMatcher::macCandidatesForCustomer($customer) as $macCompact) {
                        $onu = CustomerOnuMatcher::findUnlinkedOnuForMac($tenantId, $macCompact);
                        if ($onu === null) {
                            continue;
                        }
                        $this->provision->assignOnuToCustomer(
                            $customer,
                            (int) $onu->id,
                            CustomerOnuSmartLinkService::REASON_ONU_MAC_EXACT,
                            98,
                        );
                        $linked++;
                        break;
                    }
                }
            });

        return $linked;
    }
}
