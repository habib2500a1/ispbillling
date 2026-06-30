<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Optical\MikrotikOpticalBridgeService;
use App\Services\Portal\CustomerPortalAccessService;
use App\Support\MacAddress;
use Illuminate\Support\Facades\Crypt;

/**
 * MikroTik PPP IP/MAC → ONU path + stored home router LAN admin credentials.
 */
final class SubscriberNetworkPathService
{
    public function __construct(
        private readonly MikrotikOpticalBridgeService $bridge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function path(Customer $customer): array
    {
        $customer->loadMissing([
            'mikrotikServer:id,name,host',
            'onuDevice.olt:id,display_name',
            'activePppSession',
            'devices',
        ]);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $ppp = $customer->activePppSession;
        $onu = $customer->primaryOnu();
        $mt = $customer->mikrotikServer;

        $callerId = $ppp?->caller_id
            ?? $meta['mikrotik_caller_id']
            ?? $meta['mac_binding']
            ?? $meta['mikrotik_last_caller_id']
            ?? null;

        $homeIp = trim((string) ($meta['home_router_ip'] ?? '192.168.0.1')) ?: '192.168.0.1';
        $homeUser = trim((string) ($meta['home_router_user'] ?? 'admin')) ?: 'admin';
        $homePass = $this->decryptHomePassword($meta);

        $portal = app(CustomerPortalAccessService::class);

        return [
            'mikrotik' => [
                'name' => $mt?->name,
                'host' => $mt?->host,
                'admin_url' => filled($mt?->host) ? 'http://'.$mt->host : null,
            ],
            'ppp' => [
                'login' => $customer->pppLoginName(),
                'framed_ip' => $ppp?->framed_ip,
                'caller_id' => $callerId !== null ? (MacAddress::normalizeColon((string) $callerId) ?? $callerId) : null,
                'online' => $ppp !== null,
            ],
            'onu' => [
                'linked' => $onu instanceof Device,
                'serial' => $onu?->serial_number,
                'epon' => $onu?->epon_port ?? $meta['epon_port'] ?? null,
                'rx_dbm' => $onu?->rx_dbm,
                'olt' => $onu?->olt?->display_name,
            ],
            'path_label' => $this->pathLabel($mt?->host, $ppp?->framed_ip, $callerId, $onu),
            'home_router' => [
                'lan_url' => 'http://'.$homeIp,
                'user' => $homeUser,
                'password' => $homePass,
                'password_set' => $homePass !== null,
            ],
            'links' => [
                'billing_router_portal' => route('portal.router-home'),
                'portal_token' => $portal->accessTokenUrl($customer),
                'wan_try_url' => filled($ppp?->framed_ip) ? 'http://'.$ppp->framed_ip : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncAndRefresh(Customer $customer): array
    {
        $this->bridge->syncAndLinkFromMikrotik($customer, syncOltFirst: true);

        return $this->path($customer->fresh() ?? $customer);
    }

    public function saveHomeRouterCredentials(Customer $customer, string $ip, string $user, ?string $password): void
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['home_router_ip'] = trim($ip) !== '' ? trim($ip) : '192.168.0.1';
        $meta['home_router_user'] = trim($user) !== '' ? trim($user) : 'admin';

        if ($password !== null && $password !== '') {
            $meta['home_router_password_enc'] = Crypt::encryptString($password);
        }

        $customer->forceFill(['meta' => $meta])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function decryptHomePassword(array $meta): ?string
    {
        $enc = $meta['home_router_password_enc'] ?? null;
        if (! is_string($enc) || $enc === '') {
            return null;
        }

        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    private function pathLabel(?string $mikrotikHost, ?string $framedIp, ?string $callerId, ?Device $onu): string
    {
        $parts = array_filter([
            filled($mikrotikHost) ? 'MT '.$mikrotikHost : null,
            filled($framedIp) ? 'IP '.$framedIp : null,
            filled($callerId) ? 'MAC '.(MacAddress::normalizeColon((string) $callerId) ?? $callerId) : null,
            $onu !== null ? 'ONU '.($onu->epon_port ?? $onu->serial_number ?? 'linked') : 'ONU —',
        ]);

        return $parts !== [] ? implode(' → ', $parts) : 'No path — run auto-detect';
    }
}
