<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Support\OltManagementHelper;
use App\Support\SnmpClient;

/**
 * Minimal SNMP v2c probe (sysDescr). Requires PHP ext-snmp.
 * Blank community uses "public". Host falls back to management_ip when snmp_host is empty.
 */
final class OltSnmpProbeService
{
    public function __construct(
        private readonly OltPptpTunnelService $pptpTunnel,
    ) {}

    public static function isSnmpExtensionAvailable(): bool
    {
        return extension_loaded('snmp') && function_exists('snmp2_get');
    }

    /**
     * Human-readable install steps when ext-snmp is missing (Ubuntu/Debian + verify).
     */
    public static function installInstructions(): string
    {
        $v = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        return implode("\n", [
            'Ubuntu / Debian (app server):',
            "  sudo apt update && sudo apt install -y php{$v}-snmp",
            '  sudo systemctl restart php'.$v.'-fpm 2>/dev/null || sudo systemctl restart php-fpm 2>/dev/null || sudo systemctl restart apache2',
            'Verify CLI: php -m | grep -i snmp',
            'If the panel uses a different PHP than CLI, install snmp for that SAPI too (same package usually enables both).',
        ]);
    }

    public function snmpPeer(Device $olt): string
    {
        $host = filled($olt->snmp_host) ? trim((string) $olt->snmp_host) : trim((string) ($olt->management_ip ?? ''));
        if ($host === '') {
            throw new \InvalidArgumentException('Set SNMP host or management IP.');
        }

        $port = (int) ($olt->snmp_port ?? 161);
        if ($port === 161) {
            return $host;
        }

        return $host.':'.$port;
    }

    public function effectiveCommunity(Device $olt): string
    {
        $c = $olt->snmp_community;
        if (is_string($c) && $c !== '') {
            return $c;
        }

        return 'public';
    }

    public function fetchSysDescr(Device $olt): string
    {
        $this->ensureReachability($olt);

        if (($olt->snmp_version ?? 'v2c') !== 'v2c') {
            throw new \RuntimeException('SNMP test from the panel currently supports v2c only. Set version to v2c or use an external NMS for v3.');
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
            $phpErr = error_get_last();
            $phpSnmpMsg = '';
            if (is_array($phpErr) && isset($phpErr['message']) && str_contains(strtolower((string) $phpErr['message']), 'snmp')) {
                $phpSnmpMsg = ' PHP: '.trim((string) $phpErr['message']);
            }

            $communityHint = filled($olt->snmp_community)
                ? 'custom community (saved in panel — check spelling & OLT ACL)'
                : 'default community "public" (set real community on OLT or in panel if not public)';

            $lines = [
                'SNMP GET failed — এটি প্যানেলের বাগ নয়; রাউটার/OLT SNMP উত্তর দেয়নি।',
                "Target: UDP {$peer}  |  OID: 1.3.6.1.2.1.1.1.0 (sysDescr)  |  {$communityHint}",
                "Timeout: {$timeoutUs} µs, retries: {$retries}.{$phpSnmpMsg}",
                '',
                'যাচাই করুন / Check:',
                '1) Management IP / SNMP host সঠিক, OLT থেকে ICMP ping হয় (ঐ হোস্টে SNMP চালু আছে কিনা)।',
                '2) Community string OLT-র read community-র সাথে মিলে (v2c) — অনেক ডিভাইসে public বন্ধ থাকে।',
                '3) SNMP শুধো management VLAN/IP তে চালু — প্যানেল সার্ভার সেই নেটওয়ার্কে পৌঁছাতে পারে কিনা।',
                '4) ফায়ারওয়াল / security group: outbound UDP 161 (এপ সার্ভার) ও inbound OLT-তে UDP 161 খোলা।',
                '5) কিছু OLT-তে SNMP বন্ধ থাকে — Web/CLI দিয়ে SNMP v2c enable করুন।',
            ];

            $reach = $this->networkReachabilityHint($olt);
            if ($reach !== '') {
                $lines[] = '';
                $lines[] = $reach;
            }

            $egress = $this->appServerEgressIp();
            if ($egress !== null) {
                $lines[] = "6) OLT/Router ACL-তে প্যানেল সার্ভার IP allow করুন: {$egress} → UDP 161 (outbound from server, inbound on OLT).";
            }

            throw new \RuntimeException(implode("\n", $lines));
        }

        return $result;
    }

    /**
     * Quick ICMP hint for panel messages (SNMP needs same L3 reachability as ping on many ISPs).
     */
    public function pingOk(Device $olt): bool
    {
        return $this->pingSummary($olt)['reachable'];
    }

    /**
     * @return array{host: string, reachable: bool, packet_loss_percent: ?float, avg_latency_ms: ?float, sample_count: int}
     */
    public function pingSummary(Device $olt, int $sampleCount = 2): array
    {
        $host = filled($olt->snmp_host) ? trim((string) $olt->snmp_host) : trim((string) ($olt->management_ip ?? ''));

        if ($host === '' || ! filter_var($host, FILTER_VALIDATE_IP)) {
            return [
                'host' => $host,
                'reachable' => false,
                'packet_loss_percent' => null,
                'avg_latency_ms' => null,
                'sample_count' => max(1, $sampleCount),
            ];
        }

        $stats = $this->pingHostSummary($host, $sampleCount);
        if ($stats['reachable']) {
            return $stats;
        }

        if ($this->pptpTunnel->vpnEnabled($olt)) {
            $reach = $this->pptpTunnel->ensureConnected($olt->fresh());

            if ($reach['success']) {
                return $this->pingHostSummary($host, $sampleCount);
            }
        }

        return $stats;
    }

    private function ensureReachability(Device $olt): void
    {
        $host = filled($olt->snmp_host) ? trim((string) $olt->snmp_host) : trim((string) ($olt->management_ip ?? ''));
        if ($host !== '' && $this->pingHost($host)) {
            return;
        }

        if (! $this->pptpTunnel->vpnEnabled($olt)) {
            return;
        }

        $reach = $this->pptpTunnel->ensureConnected($olt->fresh());
        if (! $reach['success']) {
            throw new \RuntimeException('PPTP VPN: '.($reach['message'] ?? 'connect failed'));
        }
    }

    public function networkReachabilityHint(Device $olt): string
    {
        $host = filled($olt->snmp_host) ? trim((string) $olt->snmp_host) : trim((string) ($olt->management_ip ?? ''));
        if ($host === '' || ! filter_var($host, FILTER_VALIDATE_IP)) {
            return 'Management IP / SNMP host সেট করুন।';
        }

        if (! $this->pingOk($olt)) {
            $egress = $this->appServerEgressIp();
            $acl = $egress !== null ? " প্যানেল সার্ভার IP ({$egress}) OLT SNMP ACL-তে allow করুন।" : '';

            if (OltManagementHelper::vpnEnabled($olt)) {
                return "সার্ভার থেকে OLT IP ({$host}) ping হচ্ছে না — VPN চালু কিন্তু টানেল up হয়নি (PPTP: GRE+1723 | OpenVPN: .ovpn)।{$acl}";
            }

            return "সার্ভার থেকে OLT IP ({$host}) ping হচ্ছে না — Edit OLT → VPN (PPTP/OpenVPN) সেট করুন।{$acl}";
        }

        return "Ping OK ({$host}) — SNMP community ও OLT-তে SNMP v2c enable যাচাই করুন।";
    }

    public function appServerEgressIp(): ?string
    {
        $configured = config('snmp.app_server_egress_ip');
        if (is_string($configured) && filter_var(trim($configured), FILTER_VALIDATE_IP)) {
            return trim($configured);
        }

        return null;
    }

    private function pingHost(string $host): ?bool
    {
        $cmd = sprintf('ping -c 1 -W 2 %s 2>/dev/null', escapeshellarg($host));
        $code = 1;
        @exec($cmd, $output, $code);

        return $code === 0 ? true : false;
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
