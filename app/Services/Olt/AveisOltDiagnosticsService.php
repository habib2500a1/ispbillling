<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Support\GponSnmpProfile;
use App\Support\OltManagementHelper;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Http;

/**
 * Pre-flight checks for Aveis OLT sync (ping, SNMP GET/walk sample, web port).
 */
final class AveisOltDiagnosticsService
{
    public function __construct(
        private readonly OltSnmpProbeService $probe,
        private readonly OltPptpTunnelService $pptpTunnel,
    ) {}

    /**
     * @return array{
     *   ping_ok: bool,
     *   snmp_get_ok: bool,
     *   sys_descr: ?string,
     *   snmp_walk_rows: int,
     *   web_ok: bool,
     *   web_status: ?int,
     *   summary: string,
     *   hints: list<string>
     * }
     */
    public function diagnose(Device $olt): array
    {
        $pptp = $this->pptpTunnel->status($olt->fresh());
        $host = filled($olt->snmp_host) ? trim((string) $olt->snmp_host) : trim((string) ($olt->management_ip ?? ''));
        $peer = $host !== '' ? $this->probe->snmpPeer($olt) : '';
        $community = $this->probe->effectiveCommunity($olt);

        $pingOk = $this->probe->pingOk($olt);

        $sysDescr = null;
        $snmpGetOk = false;
        if ($peer !== '' && SnmpClient::available()) {
            try {
                $sysDescr = $this->probe->fetchSysDescr($olt);
                $snmpGetOk = true;
            } catch (\Throwable) {
                $snmpGetOk = false;
            }
        }

        $walkRows = 0;
        if ($snmpGetOk && $peer !== '') {
            $oids = GponSnmpProfile::forOlt($olt);
            $table = (string) ($oids['aveis_onu_table'] ?? '1.3.6.1.4.1.50224.3.3.2.1');
            $macCol = max(1, (int) ($oids['aveis_onu_mac_column'] ?? 7));
            $timeoutUs = min(8000000, (int) config('gpon.aveis_gpon_walk_timeout_us', 10000000));
            $walk = config('gpon.aveis_snmp_use_unchecked_walk', true)
                ? SnmpClient::realWalkUnchecked($peer, $community, $table.'.'.$macCol, $timeoutUs, 1)
                : SnmpClient::realWalk($peer, $community, $table.'.'.$macCol, $timeoutUs, 1);
            $walkRows = count($walk);
        }

        $webUrl = OltManagementHelper::webUiUrl($olt);
        $webOk = false;
        $webStatus = null;
        if ($webUrl !== null) {
            try {
                $response = Http::timeout(6)->withOptions(['verify' => false])->get($webUrl);
                $webStatus = $response->status();
                $webOk = $response->successful() || in_array($webStatus, [401, 403], true);
            } catch (\Throwable) {
                $webOk = false;
            }
        }

        $hints = [];
        if (! $pingOk) {
            $hints[] = 'বিল সার্ভার থেকে OLT IP-তে ping যাচ্ছে না — SNMP/Web sync সম্ভব নয় যতক্ষণ রাউটিং/ফায়ারওয়াল ঠিক নয়।';
        }
        if (! $snmpGetOk) {
            $hints[] = 'SNMP GET (sysDescr) fail — community "public" ও OLT-তে SNMP v2c enable যাচাই করুন।';
        } elseif ($walkRows === 0) {
            $hints[] = 'SNMP GET OK কিন্তু ONU table walk খালি — OLT-তে SNMP walk/getbulk allow করুন (enterprise 50224) অথবা sync index-scan চেষ্টা করবে।';
        }
        if ($webUrl !== null && ! $webOk) {
            $hints[] = 'Web UI ('.$webUrl.') সার্ভার থেকে খোলা যাচ্ছে না — অন্য প্যানেল লোকাল নেটওয়ার্কে থাকলে সেখানে চলতে পারে।';
        }

        $pptpLine = OltManagementHelper::pptpEnabled($olt)
            ? ('PPTP '.(($pptp['ppp_up'] ?? false) ? 'UP' : 'DOWN'))
            : 'PPTP off';

        $summary = sprintf(
            '%s · Ping %s · SNMP %s · ONU walk %d row · Web %s',
            $pptpLine,
            $pingOk ? 'OK' : 'FAIL',
            $snmpGetOk ? 'OK' : 'FAIL',
            $walkRows,
            $webOk ? 'OK' : ($webUrl ? 'FAIL' : 'n/a'),
        );

        return [
            'pptp' => $pptp,
            'ping_ok' => $pingOk,
            'snmp_get_ok' => $snmpGetOk,
            'sys_descr' => $sysDescr,
            'snmp_walk_rows' => $walkRows,
            'web_ok' => $webOk,
            'web_status' => $webStatus,
            'summary' => $summary,
            'hints' => $hints,
        ];
    }
}
