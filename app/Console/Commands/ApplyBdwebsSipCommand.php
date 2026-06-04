<?php

namespace App\Console\Commands;

use App\Models\CallCenterSetting;
use App\Services\CallCenter\WebSipConfigPresenter;
use App\Services\CallCenter\PortSipConnectionProfile;
use App\Support\BdwebsWebSipDefaults;
use Illuminate\Console\Command;

class ApplyBdwebsSipCommand extends Command
{
    protected $signature = 'isp:apply-bdwebs-sip {--tenant=1 : Tenant ID}';

    protected $description = 'Apply BDWebs/PortSIP SIP settings from BDWEBS_* env vars (password never in code)';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $username = trim((string) env('BDWEBS_SIP_USERNAME', ''));
        $password = trim((string) env('BDWEBS_SIP_PASSWORD', ''));
        $domain = trim((string) env('BDWEBS_SIP_DOMAIN', 'sip17.bdwebs.com'));
        $server = trim((string) env('BDWEBS_SIP_SERVER', '202.40.176.2'));
        $wss = trim((string) env('BDWEBS_WSS_URI', ''));

        if ($username === '' || $password === '') {
            $this->error('Set BDWEBS_SIP_USERNAME and BDWEBS_SIP_PASSWORD in .env first.');

            return self::FAILURE;
        }

        $settings = CallCenterSetting::forTenant($tenantId);
        $meta = is_array($settings->meta) ? $settings->meta : [];
        $meta['websip_username'] = $username;
        $meta['websip_password'] = WebSipConfigPresenter::encryptPassword($password);
        $meta['sip_port'] = PortSipConnectionProfile::DEFAULT_SIP_PORT;

        $autoWss = BdwebsWebSipDefaults::resolveWssUris(null, $domain, $server);

        $settings->update([
            'websip_enabled' => true,
            'sip_domain' => $domain,
            'sip_server' => $server,
            'default_extension' => $username,
            'outbound_caller_id' => $username,
            'wss_uri' => $wss !== '' ? $wss : $settings->wss_uri,
            'meta' => $meta,
        ]);

        $this->info("BDWebs SIP settings saved for tenant {$tenantId}.");
        $this->line('Enable CALL_CENTER_WEBSIP_ENABLED=true and open Call center → confirm WebSIP shows Ready.');

        if ($wss === '') {
            $this->warn('BDWEBS_WSS_URI empty — browser will auto-try: '.implode(', ', array_slice($autoWss, 0, 3)).'…');
            $this->line('If all fail, get exact WSS URL from BDWebs support (PortSIP UDP still works).');
        }

        return self::SUCCESS;
    }
}
