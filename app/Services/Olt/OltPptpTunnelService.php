<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Support\OltManagementHelper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Per-OLT PPTP (configured on Edit/Create OLT) — used before SNMP/sync when direct reach fails.
 */
final class OltPptpTunnelService
{
    public function pptpEnabled(Device $olt): bool
    {
        return OltManagementHelper::pptpEnabled($olt);
    }

    public function vpnEnabled(Device $olt): bool
    {
        return OltManagementHelper::vpnEnabled($olt);
    }

    public function storeOpenVpnConfig(Device $olt, string $ovpnText): void
    {
        $dir = storage_path('app/private/olt-vpn');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.$olt->id.'.ovpn', $this->sanitizeOpenVpnConfigForLinux($ovpnText));
        @chmod($dir.'/'.$olt->id.'.ovpn', 0600);
    }

    /**
     * Strip Windows-only directives so Linux openvpn 2.5+ can parse the file.
     */
    public function sanitizeOpenVpnConfigForLinux(string $ovpnText): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($ovpnText)) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === 'block-outside-dns') {
                continue;
            }
            if (preg_match('/^ignore-unknown-option\s+block-outside-dns\s*$/i', $trim)) {
                continue;
            }

            $out[] = $line;
        }

        return trim(implode("\n", $out))."\n";
    }

    public function removeVpn(Device $olt): void
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $olt->forceFill(['meta' => OltManagementHelper::clearVpnFromMeta($meta)])->saveQuietly();
        @unlink(storage_path('app/private/olt-vpn/'.$olt->id.'.ovpn'));
        $this->runCtl('disconnect', (int) $olt->id);
    }

    /**
     * Full report for panel “Test VPN” (shows why it failed).
     *
     * @return array{success: bool, summary: string, lines: list<string>, via_pptp: bool}
     */
    public function diagnose(Device $olt): array
    {
        $lines = [];
        $host = trim((string) ($olt->management_ip ?? ''));

        $vpnType = OltManagementHelper::vpnType($olt);
        if ($vpnType === OltManagementHelper::VPN_NONE) {
            return [
                'success' => false,
                'summary' => 'VPN বন্ধ — OLT list → Edit → VPN section → PPTP বা OpenVPN বেছে Save করুন।',
                'lines' => ['VPN type: none'],
                'via_pptp' => false,
            ];
        }

        $lines[] = 'VPN type: '.$vpnType;
        if ($vpnType === OltManagementHelper::VPN_PPTP) {
            $config = OltManagementHelper::pptpConfig($olt);
            if ($config === null) {
                $lines[] = 'Config: INCOMPLETE (server / user / password / subnet)';

                return [
                    'success' => false,
                    'summary' => 'PPTP তথ্য অসম্পূর্ণ — server, username, password পূরণ করে Save করুন।',
                    'lines' => $lines,
                    'via_pptp' => true,
                ];
            }
            $lines[] = 'PPTP server: '.$config['server'];
            $lines[] = 'User: '.$config['username'];
            $lines[] = 'Route subnet: '.$config['subnet'];
        } else {
            $config = OltManagementHelper::openVpnConfig($olt);
            if ($config === null) {
                $lines[] = 'Config: .ovpn file missing — paste .ovpn in form and Save';

                return [
                    'success' => false,
                    'summary' => 'OpenVPN — .ovpn ফাইল নেই। Edit OLT-এ config পেস্ট করে Save করুন।',
                    'lines' => $lines,
                    'via_pptp' => true,
                ];
            }
            $lines[] = 'OpenVPN file: OK';
            $lines[] = 'Route subnet: '.$config['subnet'];
        }
        $lines[] = 'OLT IP: '.$host;

        if (! $this->sudoCtlAvailable()) {
            $lines[] = 'Sudo: FAIL — run on server: sudo bash scripts/olt-pptp/install-isp-olt-pptp.sh';

            return [
                'success' => false,
                'summary' => 'সার্ভারে PPTP sudo সেটআপ নেই (www-data → isp-olt-pptp-ctl)।',
                'lines' => $lines,
                'via_pptp' => true,
            ];
        }
        $lines[] = 'Sudo ctl: OK';

        $direct = $host !== '' && $this->pingHost($host);
        $lines[] = 'Direct ping OLT: '.($direct ? 'OK' : 'FAIL');
        if ($direct) {
            return [
                'success' => true,
                'summary' => 'সরাসরি OLT reach — PPTP লাগছে না।',
                'lines' => $lines,
                'via_pptp' => false,
            ];
        }

        if (! $this->writeConfigFile($olt)) {
            $lines[] = 'Config file: FAIL';

            return [
                'success' => false,
                'summary' => 'VPN config তৈরি হয়নি — Save করুন।',
                'lines' => $lines,
                'via_pptp' => true,
            ];
        }

        $sync = $this->runCtl('sync-peer', (int) $olt->id);
        $lines[] = 'Peer file: '.(($sync['ok'] ?? false) ? 'OK' : ('FAIL — '.($sync['message'] ?? '')));

        $ctl = $this->runCtl('connect', (int) $olt->id);
        $lines[] = 'VPN connect: '.(($ctl['ok'] ?? false) ? 'OK' : 'FAIL');
        if (! empty($ctl['message'])) {
            $lines[] = 'Detail: '.$ctl['message'];
        }
        if (! empty($ctl['log_tail'])) {
            $lines[] = 'Log: '.$ctl['log_tail'];
        }

        $pppUp = (bool) ($ctl['ppp_up'] ?? false);
        $lines[] = 'PPP interface UP: '.($pppUp ? 'YES' : 'NO');

        $oltPing = $host !== '' && $this->pingHost($host);
        $lines[] = 'Ping OLT after VPN: '.($oltPing ? 'OK' : 'FAIL');

        if ($oltPing) {
            return [
                'success' => true,
                'summary' => 'VPN কাজ করছে — OLT reachable।',
                'lines' => $lines,
                'via_pptp' => true,
            ];
        }

        if (! $pppUp) {
            $egress = config('snmp.app_server_egress_ip') ?? '72.18.215.205';
            $lines[] = "MikroTik: allow GRE (proto 47) + TCP 1723 from {$egress}";

            return [
                'success' => false,
                'summary' => 'VPN টানেল উঠেনি — PPTP: GRE+1723 | OpenVPN: .ovpn ও UDP 1194',
                'lines' => $lines,
                'via_pptp' => true,
            ];
        }

        return [
            'success' => false,
            'summary' => 'PPTP up কিন্তু OLT ping fail — subnet/route চেক করুন।',
            'lines' => $lines,
            'via_pptp' => true,
        ];
    }

    /**
     * @return array{success: bool, message: string, via_pptp: bool}
     */
    public function ensureConnected(Device $olt): array
    {
        $host = trim((string) ($olt->management_ip ?? ''));
        if ($host === '' || ! filter_var($host, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => 'Set OLT management IP.', 'via_pptp' => false];
        }

        if ($this->pingHost($host)) {
            return ['success' => true, 'message' => 'Direct reach OK (no PPTP needed).', 'via_pptp' => false];
        }

        if (! $this->vpnEnabled($olt)) {
            return [
                'success' => false,
                'message' => 'OLT unreachable. Edit OLT → VPN: PPTP or OpenVPN সেট করে Save করুন।',
                'via_pptp' => false,
            ];
        }

        if (! $this->writeConfigFile($olt)) {
            return [
                'success' => false,
                'message' => 'VPN config incomplete — check Edit OLT VPN section.',
                'via_pptp' => false,
            ];
        }

        $this->runCtl('sync-peer', (int) $olt->id);

        $ctl = $this->runCtl('connect', (int) $olt->id);
        if (! ($ctl['ok'] ?? false)) {
            $msg = trim((string) ($ctl['message'] ?? 'VPN connect failed'));
            if (! empty($ctl['log_tail'])) {
                $msg .= "\nLog: ".$ctl['log_tail'];
            }
            if (OltManagementHelper::vpnType($olt) === OltManagementHelper::VPN_PPTP) {
                $egress = config('snmp.app_server_egress_ip') ?? '72.18.215.205';
                $msg .= "\nMikroTik: GRE (proto 47) + TCP 1723 allow from {$egress}.";
            }

            return ['success' => false, 'message' => $msg, 'via_pptp' => true];
        }

        if ($this->pingHost($host)) {
            return ['success' => true, 'message' => 'VPN up — OLT reachable.', 'via_pptp' => true];
        }

        return [
            'success' => false,
            'message' => 'VPN up but OLT ping fail — check route subnet in VPN settings.',
            'via_pptp' => true,
        ];
    }

    private function sudoCtlAvailable(): bool
    {
        exec('sudo -n true 2>/dev/null', $output, $code);

        return $code === 0;
    }

    /**
     * @return array{success: bool, message: string, ppp_up: bool, direct_ping: bool, olt_ping: bool}
     */
    public function status(Device $olt): array
    {
        $host = trim((string) ($olt->management_ip ?? ''));
        $direct = $host !== '' && $this->pingHost($host);
        $ctl = $this->runCtl('status', (int) $olt->id);
        $pppUp = (bool) ($ctl['ppp_up'] ?? false);
        $oltPing = $direct || ($pppUp && $host !== '' && $this->pingHost($host));

        return [
            'success' => $oltPing,
            'message' => (string) ($ctl['message'] ?? ''),
            'ppp_up' => $pppUp,
            'direct_ping' => $direct,
            'olt_ping' => $oltPing,
        ];
    }

    /**
     * Try direct ping, then OpenVPN (if .ovpn exists), then PPTP (if creds in meta). Picks first that reaches OLT IP.
     *
     * @return array{
     *   recommended: string,
     *   summary_bn: string,
     *   direct: array{reachable: bool},
     *   methods: list<array{method: string, label: string, tried: bool, success: bool, message: string, ppp_up?: bool}>
     * }
     */
    public function compareAllReachMethods(Device $olt): array
    {
        $this->runCtl('disconnect', (int) $olt->id, 15);

        $oltIp = trim((string) ($olt->management_ip ?? ''));
        $direct = $oltIp !== '' ? $this->pingHost($oltIp) : false;

        $methods = [];
        $winner = 'none';

        if ($direct) {
            $winner = 'direct';
        }

        $ovpn = OltManagementHelper::openVpnConfigFromFile($olt);
        if ($ovpn !== null) {
            $this->runCtl('disconnect', (int) $olt->id, 15);
            $this->writeJsonConfig($olt, 'openvpn', $ovpn);
            $ctl = $this->runCtl('connect', (int) $olt->id, 35);
            $pppUp = (bool) ($ctl['ppp_up'] ?? false);
            $oltPing = $oltIp !== '' ? $this->pingHost($oltIp) : false;
            $ok = $oltPing || $pppUp;
            $methods[] = [
                'method' => 'openvpn',
                'label' => 'OpenVPN (.ovpn)',
                'tried' => true,
                'success' => $ok,
                'message' => (string) ($ctl['message'] ?? ($ok ? 'OLT reachable' : 'Tunnel up but OLT ping failed')),
                'ppp_up' => $pppUp,
            ];
            if ($ok && $winner === 'none') {
                $winner = 'openvpn';
            }
            $this->runCtl('disconnect', (int) $olt->id, 15);
        } else {
            $methods[] = [
                'method' => 'openvpn',
                'label' => 'OpenVPN (.ovpn)',
                'tried' => false,
                'success' => false,
                'message' => 'No .ovpn file — upload habib.ovpn on Edit OLT or paste in textarea',
            ];
        }

        $pptp = OltManagementHelper::pptpConfigFromMeta($olt);
        if ($pptp !== null) {
            $this->runCtl('disconnect', (int) $olt->id, 15);
            $this->writeJsonConfig($olt, 'pptp', $pptp);
            $ctl = $this->runCtl('connect', (int) $olt->id, 35);
            $pppUp = (bool) ($ctl['ppp_up'] ?? false);
            $oltPing = $oltIp !== '' ? $this->pingHost($oltIp) : false;
            $ok = $oltPing || $pppUp;
            $methods[] = [
                'method' => 'pptp',
                'label' => 'PPTP',
                'tried' => true,
                'success' => $ok,
                'message' => (string) ($ctl['message'] ?? ($ok ? 'OLT reachable' : 'PPTP LCP/GRE failed — open GRE+TCP 1723 on MikroTik')),
                'ppp_up' => $pppUp,
            ];
            if ($ok && $winner === 'none') {
                $winner = 'pptp';
            }
            $this->runCtl('disconnect', (int) $olt->id, 15);
        } else {
            $methods[] = [
                'method' => 'pptp',
                'label' => 'PPTP',
                'tried' => false,
                'success' => false,
                'message' => 'PPTP server/user/password missing in meta',
            ];
        }

        if ($winner === 'direct') {
            $summary = 'Direct ping OK — VPN optional.';
        } elseif ($winner === 'openvpn') {
            $summary = 'Use OpenVPN — set VPN type to OpenVPN and Save.';
        } elseif ($winner === 'pptp') {
            $summary = 'Use PPTP — set VPN type to PPTP and Save.';
        } else {
            $summary = 'No method reached OLT — fix OpenVPN file or MikroTik GRE for PPTP.';
        }

        return [
            'recommended' => $winner,
            'summary_bn' => $summary,
            'direct' => ['reachable' => $direct],
            'methods' => $methods,
        ];
    }

    public function syncPeerFromOlt(Device $olt): void
    {
        if (! $this->vpnEnabled($olt)) {
            $this->runCtl('disconnect', (int) $olt->id);

            return;
        }

        if ($this->writeConfigFile($olt)) {
            $this->runCtl('sync-peer', (int) $olt->id);
        }
    }

    private function writeConfigFile(Device $olt): bool
    {
        $type = OltManagementHelper::vpnType($olt);

        if ($type === OltManagementHelper::VPN_PPTP) {
            $config = OltManagementHelper::pptpConfig($olt);

            return $config !== null && $this->writeJsonConfig($olt, 'pptp', $config);
        }
        if ($type === OltManagementHelper::VPN_OPENVPN) {
            $config = OltManagementHelper::openVpnConfig($olt);

            return $config !== null && $this->writeJsonConfig($olt, 'openvpn', $config);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function writeJsonConfig(Device $olt, string $type, array $config): bool
    {
        $dir = storage_path('app/private/olt-pptp');
        File::ensureDirectoryExists($dir);

        $payload = [
            'olt_id' => (int) $olt->id,
            'type' => $type,
            'subnet' => $config['subnet'],
            'olt_ip' => $config['olt_ip'],
            'peer' => $this->peerName($olt),
        ];

        if ($type === 'pptp') {
            $payload['server'] = $config['server'];
            $payload['username'] = $config['username'];
            $payload['password'] = $config['password'];
        } else {
            $payload['config_path'] = $config['config_path'];
        }

        File::put(
            $dir.'/'.$olt->id.'.json',
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
        @chmod($dir.'/'.$olt->id.'.json', 0600);

        return true;
    }

    private function peerName(Device $olt): string
    {
        return 'olt-pptp-'.$olt->id;
    }

    /**
     * @return array{ok: bool, message?: string, ppp_up?: bool}
     */
    private function runCtl(string $action, int $oltId, int $timeoutSec = 0): array
    {
        $script = (string) config('olt_pptp.ctl_script');
        if (! is_executable($script)) {
            return [
                'ok' => false,
                'message' => 'PPTP control script missing. Run: sudo bash scripts/olt-pptp/install-isp-olt-pptp.sh',
            ];
        }

        $inner = sprintf(
            'sudo -n %s %s %d 2>&1',
            escapeshellarg($script),
            escapeshellarg($action),
            $oltId,
        );
        $cmd = $timeoutSec > 0
            ? sprintf('timeout %d %s', $timeoutSec, $inner)
            : $inner;
        $output = [];
        $code = 1;
        @exec($cmd, $output, $code);
        if ($timeoutSec > 0 && $code === 124) {
            $this->runCtl('disconnect', $oltId, 15);

            return [
                'ok' => false,
                'message' => 'VPN command timed out — try again or use OpenVPN',
                'ppp_up' => false,
            ];
        }
        $text = trim(implode("\n", $output));

        if ($text !== '' && str_starts_with($text, '{')) {
            try {
                $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                Log::warning('olt_pptp.ctl_json', ['olt_id' => $oltId, 'action' => $action, 'error' => $e->getMessage()]);
            }
        }

        return [
            'ok' => $code === 0,
            'message' => $text !== '' ? $text : ($code === 0 ? 'OK' : 'Command failed'),
            'ppp_up' => $code === 0 && $action === 'status',
        ];
    }

    private function pingHost(string $host): bool
    {
        $cmd = sprintf('ping -c 1 -W 2 %s 2>/dev/null', escapeshellarg($host));
        $code = 1;
        @exec($cmd, $output, $code);

        return $code === 0;
    }
}
