<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Tenant;
use App\Support\MacAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RepairOnuMacsCommand extends Command
{
    protected $signature = 'isp:repair-onu-macs {--tenant= : Tenant ID} {--force : Update even if already set} {--link : Run smart-link after repair}';
    protected $description = 'Auto-repair missing ONU MAC addresses from MikroTik sessions and hints';

    public function handle(): int
    {
        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        foreach ($tenantIds as $tenantId) {
            $this->repairForTenant((int) $tenantId);

            if ($this->option('link')) {
                $this->info("Running smart-link for Tenant #{$tenantId}...");
                Artisan::call('isp:smart-link-customer-onus', [
                    '--tenant' => $tenantId,
                    '--no-reset' => true,
                ], $this->output);
            }
        }

        return self::SUCCESS;
    }

    private function repairForTenant(int $tenantId): void
    {
        $query = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu');

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('mac_address')->orWhere('mac_address', '');
            });
        }

        $onus = $query->with(['customer.activePppSession'])->get();

        if ($onus->isEmpty()) {
            $this->line("Tenant #{$tenantId}: No ONUs need repair.");
            return;
        }

        $this->info("Repairing MACs for Tenant #{$tenantId} ({$onus->count()} ONUs found)");

        $fixed = 0;
        foreach ($onus as $onu) {
            $mac = $this->findMacForOnu($onu);
            if ($mac && $mac !== $onu->mac_address) {
                $onu->mac_address = $mac;
                $onu->save();
                $fixed++;
                $this->line("  ✓ Set MAC {$mac} for ONU #{$onu->id} ({$onu->display_name}) - Customer: " . ($onu->customer?->name ?? 'None'));
            }
        }

        $this->info("Fixed {$fixed} ONU MAC addresses for Tenant #{$tenantId}.");
    }

    private function findMacForOnu(Device $onu): ?string
    {
        // 1. From Customer's Active PPP Session (highest priority if linked)
        if ($onu->customer && $onu->customer->activePppSession) {
            $sessionMac = $onu->customer->activePppSession->caller_id;
            if (filled($sessionMac)) {
                $normalized = MacAddress::normalizeColon($sessionMac);
                if ($normalized) return $normalized;
            }
        }

        // 2. From Customer's Meta (mac_binding / onu_mac / cpe_mac / router_mac)
        if ($onu->customer) {
            $meta = is_array($onu->customer->meta) ? $onu->customer->meta : [];
            $keys = ['onu_mac', 'mac_binding', 'cpe_mac', 'router_mac', 'mikrotik_caller_id'];
            foreach ($keys as $key) {
                if (filled($meta[$key] ?? null)) {
                    $normalized = MacAddress::normalizeColon($meta[$key]);
                    if ($normalized) return $normalized;
                }
            }
        }

        // 3. From Device's Serial Number (if it looks like a MAC)
        if (filled($onu->serial_number)) {
            $normalized = MacAddress::normalizeColon($onu->serial_number);
            if ($normalized) return $normalized;
        }

        // 4. From Device's onu_external_id (if it looks like a MAC)
        if (filled($onu->onu_external_id)) {
            $normalized = MacAddress::normalizeColon($onu->onu_external_id);
            if ($normalized) return $normalized;
        }

        // 5. From Device's Meta (if stored there by some sync)
        $deviceMeta = is_array($onu->meta) ? $onu->meta : [];
        if (filled($deviceMeta['onu_mac'] ?? null)) {
            $normalized = MacAddress::normalizeColon($deviceMeta['onu_mac']);
            if ($normalized) return $normalized;
        }

        return null;
    }
}
