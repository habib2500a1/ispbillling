<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikServer;
use App\Support\CustomerPppLoginResolver;
use App\Support\MacAddress;
use Illuminate\Support\Facades\Log;
use RouterOS\Query;

/**
 * When office and subscribers share the same MikroTik (or routed LAN), resolve live
 * PPP address + optional LAN IP from ARP/DHCP for router admin access.
 */
final class MikrotikCustomerLanResolver
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
    ) {}

    /**
     * @return array{
     *   online: bool,
     *   wan_ip: ?string,
     *   caller_id: ?string,
     *   lan_ip: ?string,
     *   wan_admin_url: ?string,
     *   lan_admin_url: ?string,
     *   same_network: bool
     * }
     */
    public function hints(MikrotikServer $server, string $pppLogin, ?string $callerIdFallback = null): array
    {
        $empty = [
            'online' => false,
            'wan_ip' => null,
            'caller_id' => $callerIdFallback,
            'lan_ip' => null,
            'wan_admin_url' => null,
            'lan_admin_url' => null,
            'same_network' => true,
        ];

        if (! $server->is_enabled) {
            return $empty;
        }

        $login = CustomerPppLoginResolver::normalize($pppLogin);
        if ($login === '') {
            return $empty;
        }

        try {
            $client = $this->mikrotik->makeClient($server);
            $row = $this->findPppRow($client, $login);

            if ($row === null) {
                return $empty;
            }

            $wanIp = trim((string) ($row['address'] ?? ''));
            $callerId = trim((string) ($row['caller-id'] ?? $row['caller_id'] ?? ''));
            if ($callerId === '') {
                $callerId = (string) ($callerIdFallback ?? '');
            }

            $lanIp = $this->findLanIpByMac($client, $callerId);

            return [
                'online' => true,
                'wan_ip' => $wanIp !== '' ? $wanIp : null,
                'caller_id' => $callerId !== '' ? (MacAddress::normalizeColon($callerId) ?? $callerId) : null,
                'lan_ip' => $lanIp,
                'wan_admin_url' => $wanIp !== '' ? 'http://'.$wanIp : null,
                'lan_admin_url' => $lanIp !== null ? 'http://'.$lanIp : null,
                'same_network' => true,
            ];
        } catch (\Throwable $e) {
            Log::debug('mikrotik.lan_resolver.failed', [
                'server_id' => $server->id,
                'login' => $login,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPppRow(\RouterOS\Client $client, string $login): ?array
    {
        try {
            $query = new Query('/ppp/active/print');
            $query->where('name', $login);
            $rows = $client->query($query)->read();
            if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
                return $rows[0];
            }
        } catch (\Throwable) {
            // scan all
        }

        $rows = $client->query('/ppp/active/print')->read();
        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = CustomerPppLoginResolver::normalize((string) ($row['name'] ?? ''));
            if ($name === $login) {
                return $row;
            }
        }

        return null;
    }

    private function findLanIpByMac(\RouterOS\Client $client, ?string $mac): ?string
    {
        if ($mac === null || trim($mac) === '') {
            return null;
        }

        $colon = MacAddress::normalizeColon($mac) ?? $mac;

        foreach (['/ip/dhcp-server/lease/print', '/ip/arp/print'] as $path) {
            try {
                $query = new Query($path);
                $query->where('mac-address', $colon);
                $rows = $client->query($query)->read();
                if (! is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $ip = trim((string) ($row['active-address'] ?? $row['address'] ?? ''));
                    if ($this->isPrivateLanIp($ip)) {
                        return $ip;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function isPrivateLanIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
