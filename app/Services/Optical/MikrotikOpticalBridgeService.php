<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Services\Mikrotik\MikrotikServerService;
use App\Support\EponLabel;
use App\Support\MacAddress;
use App\Support\MikrotikOpticalHints;
use Illuminate\Support\Facades\Log;

/**
 * Pull EPON / MAC hints from MikroTik PPP, persist on subscriber, then match OLT inventory.
 */
final class MikrotikOpticalBridgeService
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
        private readonly MikrotikFleetCoordinator $fleet,
    ) {}

    /**
     * @return array{updated: bool, hints: array<string, mixed>}
     */
    public function syncHintsFromMikrotik(Customer $customer): array
    {
        if (! config('optical.mikrotik_optical_bridge_enabled', true)) {
            return ['updated' => false, 'hints' => []];
        }

        $login = $customer->pppLoginName();
        if ($login === '') {
            return ['updated' => false, 'hints' => []];
        }

        $server = $this->fleet->serversForCustomer($customer)->first()
            ?? $customer->mikrotikServer;

        if ($server === null || ! $server->is_enabled) {
            return ['updated' => false, 'hints' => []];
        }

        $secret = $this->mikrotik->fetchPppSecretForLogin($server, $login);
        $secretHints = $secret !== null ? MikrotikOpticalHints::fromPppSecret($secret) : [
            'epon_ports' => [],
            'mac_compacts' => [],
            'comment' => null,
            'caller_id' => null,
            'last_caller_id' => null,
        ];

        $sessionHints = ['epon_ports' => [], 'mac_compacts' => []];
        try {
            $active = $this->mikrotik->fetchActivePppSessionForLogin($server, $login);
            if (($active['found'] ?? false) && filled($active['caller_id'] ?? null)) {
                $sessionHints = MikrotikOpticalHints::fromActiveSession([
                    'caller_id' => $active['caller_id'],
                ]);
            }
        } catch (\Throwable) {
            // offline — last-caller-id from secret is enough
        }

        $merged = MikrotikOpticalHints::merge($secretHints, $sessionHints);
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $changed = false;

        if (filled($secretHints['comment'])) {
            if (($meta['mikrotik_comment'] ?? '') !== $secretHints['comment']) {
                $meta['mikrotik_comment'] = $secretHints['comment'];
                $changed = true;
            }
        }

        if (filled($secretHints['last_caller_id'])) {
            if (($meta['mikrotik_last_caller_id'] ?? '') !== $secretHints['last_caller_id']) {
                $meta['mikrotik_last_caller_id'] = $secretHints['last_caller_id'];
                $changed = true;
            }
        }

        if (filled($sessionHints['caller_id'] ?? null)) {
            if (($meta['mikrotik_caller_id'] ?? '') !== $sessionHints['caller_id']) {
                $meta['mikrotik_caller_id'] = $sessionHints['caller_id'];
                $changed = true;
            }
        }

        $primaryEpon = $merged['epon_ports'][0] ?? null;
        if ($primaryEpon !== null && blank($meta['epon_port'] ?? null)) {
            $meta['epon_port'] = $primaryEpon;
            $changed = true;
        }

        $routerMac = $secretHints['last_caller_id'] ?? $sessionHints['caller_id'] ?? null;
        if ($routerMac !== null && blank($meta['mac_binding'] ?? null)) {
            $meta['mac_binding'] = $routerMac;
            $changed = true;
        }

        $routerCompact = MacAddress::normalizeCompact($routerMac);
        $commentMacs = filled($secretHints['comment'])
            ? MikrotikOpticalHints::extractMacsFromText((string) $secretHints['comment'])
            : [];
        foreach ($commentMacs as $compact) {
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

        if ($changed) {
            $customer->forceFill(['meta' => $meta])->saveQuietly();
            Log::info('mikrotik_optical_bridge.hints_updated', [
                'customer_id' => $customer->id,
                'login' => $login,
                'epon' => $merged['epon_ports'],
                'macs' => $merged['mac_compacts'],
            ]);
        } else {
            $customer->forceFill(['meta' => $meta])->saveQuietly();
        }

        return [
            'updated' => $changed,
            'hints' => array_merge($merged, [
                'comment' => $secretHints['comment'],
                'last_caller_id' => $secretHints['last_caller_id'],
                'caller_id' => $sessionHints['caller_id'] ?? null,
            ]),
        ];
    }

    /**
     * MikroTik hints → OLT inventory search → link ONU.
     */
    public function syncAndLinkFromMikrotik(Customer $customer, bool $syncOltFirst = true): ?\App\Models\Device
    {
        $this->syncHintsFromMikrotik($customer);
        $customer = $customer->fresh();

        if ($syncOltFirst && config('optical.auto_sync_olt_on_mac_lookup', true)) {
            app(IspDigitalOnuPipelineService::class)->syncAllBdcomOlts((int) $customer->tenant_id);
        }

        $byEpon = CustomerOnuMatcher::findUnlinkedOnuForEponHints((int) $customer->tenant_id, $customer);
        if ($byEpon !== null) {
            return app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer(
                $customer,
                (int) $byEpon->id,
                CustomerOnuSmartLinkService::REASON_EPON_EXACT,
                92,
            );
        }

        $byMac = CustomerOnuMatcher::linkCustomerByMacFromOlt($customer, false);
        if ($byMac !== null) {
            return $byMac;
        }

        return app(CustomerOnuAutoProvisionService::class)->autoFindAndLinkOnu($customer);
    }
}
