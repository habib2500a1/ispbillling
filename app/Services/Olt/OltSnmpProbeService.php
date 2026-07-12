<?php

namespace App\Services\Olt;

use App\Models\Olt;
use App\Support\SnmpClient;

/**
 * Minimal SNMP v2c probe (sysDescr). Requires PHP ext-snmp.
 * Adapted from ispbillling (without PPTP tunnel dependency).
 */
final class OltSnmpProbeService
{
    public static function isSnmpExtensionAvailable(): bool
    {
        return extension_loaded('snmp') && function_exists('snmp2_get');
    }

    public static function installInstructions(): string
    {
        $v = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        return implode("\n", [
            'Ubuntu / Debian (app server / Docker image):',
            "  apt update && apt install -y php{$v}-snmp snmp",
            'Verify: php -m | grep -i snmp',
        ]);
    }

    public function snmpPeer(Olt $olt): string
    {
        $host = $olt->snmpPeerHost();
        if ($host === '') {
            throw new \InvalidArgumentException('Set SNMP host or management IP.');
        }

        $port = (int) ($olt->snmp_port ?? 161);
        if ($port === 161) {
            return $host;
        }

        return $host.':'.$port;
    }

    public function effectiveCommunity(Olt $olt): string
    {
        $c = $olt->snmp_community;
        if (is_string($c) && $c !== '') {
            return $c;
        }

        return 'public';
    }

    public function fetchSysDescr(Olt $olt): string
    {
        if (($olt->snmp_version ?? 'v2c') !== 'v2c') {
            throw new \RuntimeException('SNMP test currently supports v2c only.');
        }

        if (! self::isSnmpExtensionAvailable()) {
            throw new \RuntimeException(
                'PHP snmp extension (ext-snmp) is not loaded. '.self::installInstructions()
            );
        }

        $peer = $this->snmpPeer($olt);
        $community = $this->effectiveCommunity($olt);

        $result = SnmpClient::get($peer, $community, '1.3.6.1.2.1.1.1.0');

        if ($result === null) {
            $timeoutUs = (int) config('snmp.timeout_us', 2000000);
            $retries = (int) config('snmp.retries', 1);
            $communityHint = filled($olt->snmp_community)
                ? 'custom community (check spelling & OLT ACL)'
                : 'default community "public"';

            $lines = [
                'SNMP GET failed — OLT did not answer.',
                "Target: UDP {$peer} | OID: 1.3.6.1.2.1.1.1.0 (sysDescr) | {$communityHint}",
                "Timeout: {$timeoutUs} µs, retries: {$retries}.",
                '',
                'Check:',
                '1) Management IP / SNMP host is correct and reachable (ICMP ping).',
                '2) Community matches OLT read community (v2c).',
                '3) Firewall allows UDP 161 between this server and the OLT.',
                '4) SNMP v2c is enabled on the OLT.',
            ];

            $reach = $this->networkReachabilityHint($olt);
            if ($reach !== '') {
                $lines[] = '';
                $lines[] = $reach;
            }

            $egress = $this->appServerEgressIp();
            if ($egress !== null) {
                $lines[] = "5) Allow this server IP on OLT SNMP ACL: {$egress}";
            }

            throw new \RuntimeException(implode("\n", $lines));
        }

        $olt->forceFill(['last_snmp_poll_at' => now()])->save();

        return $result;
    }

    /**
     * @return array{ok: bool, sys_descr: ?string, ping: array, message: string}
     */
    public function runCheck(Olt $olt): array
    {
        $ping = $this->pingSummary($olt);
        try {
            $sysDescr = $this->fetchSysDescr($olt);

            return [
                'ok' => true,
                'sys_descr' => $sysDescr,
                'ping' => $ping,
                'message' => 'SNMP OK: '.$sysDescr,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'sys_descr' => null,
                'ping' => $ping,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function pingOk(Olt $olt): bool
    {
        return $this->pingSummary($olt)['reachable'];
    }

    /**
     * @return array{host: string, reachable: bool, packet_loss_percent: ?float, avg_latency_ms: ?float, sample_count: int}
     */
    public function pingSummary(Olt $olt, int $sampleCount = 2): array
    {
        $host = $olt->snmpPeerHost();

        if ($host === '' || ! filter_var($host, FILTER_VALIDATE_IP)) {
            return [
                'host' => $host,
                'reachable' => false,
                'packet_loss_percent' => null,
                'avg_latency_ms' => null,
                'sample_count' => max(1, $sampleCount),
            ];
        }

        return $this->pingHostSummary($host, $sampleCount);
    }

    public function networkReachabilityHint(Olt $olt): string
    {
        $host = $olt->snmpPeerHost();
        if ($host === '' || ! filter_var($host, FILTER_VALIDATE_IP)) {
            return 'Set management IP / SNMP host.';
        }

        if (! $this->pingOk($olt)) {
            $egress = $this->appServerEgressIp();
            $acl = $egress !== null ? " Allow server IP ({$egress}) on OLT ACL." : '';

            return "Server cannot ping OLT IP ({$host}).{$acl}";
        }

        return "Ping OK ({$host}) — verify SNMP community and that SNMP v2c is enabled.";
    }

    public function appServerEgressIp(): ?string
    {
        $configured = config('snmp.app_server_egress_ip');
        if (is_string($configured) && filter_var(trim($configured), FILTER_VALIDATE_IP)) {
            return trim($configured);
        }

        return null;
    }

    /**
     * @return array{host: string, reachable: bool, packet_loss_percent: ?float, avg_latency_ms: ?float, sample_count: int}
     */
    private function pingHostSummary(string $host, int $sampleCount = 2): array
    {
        $count = max(1, min(4, $sampleCount));
        $cmd = sprintf('ping -n -q -c %d -W 2 %s 2>/dev/null', $count, escapeshellarg($host));
        $code = 1;
        $output = [];
        @exec($cmd, $output, $code);
        $text = implode("\n", $output);

        $packetLoss = null;
        if (preg_match('/([\d.]+)%\s+packet loss/i', $text, $matches) === 1) {
            $packetLoss = round((float) $matches[1], 1);
        }

        $avgLatency = null;
        if (preg_match('/(?:rtt|round-trip)\s+min\/avg\/max(?:\/[a-z]+)?\s+=\s+[\d.]+\/([\d.]+)\/[\d.]+/i', $text, $matches) === 1) {
            $avgLatency = round((float) $matches[1], 2);
        }

        return [
            'host' => $host,
            'reachable' => $code === 0,
            'packet_loss_percent' => $packetLoss,
            'avg_latency_ms' => $avgLatency,
            'sample_count' => $count,
        ];
    }
}
