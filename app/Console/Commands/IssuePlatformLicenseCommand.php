<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformLicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IssuePlatformLicenseCommand extends Command
{
    protected $signature = 'isp:issue-license
        {domain : Allowed hostname e.g. bill.flixbd.xyz}
        {--expires= : Expiry date Y-m-d (empty = no expiry)}
        {--deployment=on_premise : saas|on_premise}
        {--max-tenants=1 : Max ISP tenants on this install}
        {--extra-domain=* : Additional allowed hosts}';

    protected $description = 'Sign a license key for a sold (on-premise) install — customer puts result in ISP_LICENSE_KEY';

    public function handle(): int
    {
        $privatePath = (string) config('isp.license.private_key_path');
        if (! is_readable($privatePath)) {
            $this->error('Private key missing. Run: php artisan isp:license:generate-keys');

            return self::FAILURE;
        }

        $privatePem = file_get_contents($privatePath);
        if (! is_string($privatePem) || ! str_contains($privatePem, 'BEGIN')) {
            $this->error('Invalid private key file.');

            return self::FAILURE;
        }

        $domain = strtolower(trim((string) $this->argument('domain')));
        $domains = array_values(array_unique(array_filter(array_merge(
            [$domain],
            array_map('strtolower', array_map('trim', (array) $this->option('extra-domain'))),
        ))));

        $deployment = strtolower((string) $this->option('deployment'));
        if (! in_array($deployment, [PlatformLicenseService::DEPLOYMENT_SAAS, PlatformLicenseService::DEPLOYMENT_ON_PREMISE], true)) {
            $this->error('deployment must be saas or on_premise');

            return self::FAILURE;
        }

        $payload = [
            'v' => 1,
            'domains' => $domains,
            'expires' => $this->option('expires') ? (string) $this->option('expires') : null,
            'max_tenants' => max(1, (int) $this->option('max-tenants')),
            'deployment' => $deployment,
            'issued_at' => now()->toIso8601String(),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = '';
        $ok = openssl_sign($json, $signature, $privatePem, OPENSSL_ALGO_SHA256);

        if (! $ok) {
            $this->error('Signing failed.');

            return self::FAILURE;
        }

        $key = $this->encodePart($json).'.'.$this->encodePart($signature);

        $this->newLine();
        $this->line('Add to customer .env:');
        $this->newLine();
        $this->line('ISP_DEPLOYMENT_MODE='.$deployment);
        $this->line('ISP_LICENSE_ENFORCE=true');
        $this->line('ISP_LICENSE_KEY='.$key);
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['domains', implode(', ', $domains)],
            ['expires', $payload['expires'] ?? 'never'],
            ['max_tenants', (string) $payload['max_tenants']],
        ]);

        $out = storage_path('app/license-'.str_replace('.', '-', $domain).'.txt');
        File::put($out, "ISP_LICENSE_KEY={$key}\n");
        $this->info('Also saved: '.$out);

        return self::SUCCESS;
    }

    private function encodePart(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
