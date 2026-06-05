<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OltPptpStatusCommand extends Command
{
    protected $signature = 'isp:olt-pptp-status {--olt-ip=103.29.127.94 : OLT IP to ping}';

    protected $description = 'Check PPTP tunnel (isp-olt) and OLT reachability from this server';

    public function handle(): int
    {
        $oltIp = (string) $this->option('olt-ip');
        $pptpServer = (string) config('olt_pptp.server', '103.29.127.228');

        $this->line('Bill server egress: '.(string) config('snmp.app_server_egress_ip', '72.18.215.205'));
        $this->line('PPTP peer: isp-olt → '.$pptpServer);

        $pppUp = $this->pppInterfaceUp();
        $this->line('PPP interface: '.($pppUp ? '<info>UP</info>' : '<error>DOWN</error>'));

        if ($pppUp) {
            exec('ip route show '.$oltIp.' 2>/dev/null', $routes);
            $this->line('Route to OLT: '.($routes[0] ?? '(none)'));
        }

        $pingOlt = $this->ping($oltIp);
        $pingPptp = $this->ping($pptpServer);

        $this->line("Ping {$pptpServer} (PPTP host): ".($pingPptp ? 'OK' : 'FAIL'));
        $this->line("Ping {$oltIp} (OLT): ".($pingOlt ? 'OK' : 'FAIL'));

        if (! $pppUp || ! $pingOlt) {
            $this->newLine();
            $this->warn('Fix MikroTik: allow GRE (proto 47) + TCP 1723 from 72.18.215.205');
            $this->warn('Docs: docs/OLT_PPTP_VPN.md — systemctl start isp-olt-pptp');

            return self::FAILURE;
        }

        $this->info('OLT reachable — run Test SNMP / isp:sync-aveis-epon-onus in panel.');

        return self::SUCCESS;
    }

    private function pppInterfaceUp(): bool
    {
        exec('ip -o link show type ppp 2>/dev/null', $out, $code);

        if ($code !== 0 || $out === []) {
            return false;
        }

        foreach ($out as $line) {
            if (str_contains($line, 'UP')) {
                return true;
            }
        }

        return false;
    }

    private function ping(string $host): bool
    {
        $cmd = sprintf('ping -c 1 -W 3 %s 2>/dev/null', escapeshellarg($host));
        exec($cmd, $output, $code);

        return $code === 0;
    }
}
