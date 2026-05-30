<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerNetworkSync;
use App\Support\CustomerStatus;
use Illuminate\Console\Command;

class NetworkSyncCustomerCommand extends Command
{
    protected $signature = 'isp:network-sync-customer
                            {customer : Customer ID, code (e.g. 0605), or phone}
                            {--tenant=1 : Tenant ID}
                            {--set-active : Set status + network_access_state active before sync}
                            {--force-mikrotik : Push enable to MikroTik API only (skip policy re-suspend)}';

    protected $description = 'Force MikroTik PPP secret ON/OFF sync for one subscriber (paid / Net ON / renew).';

    public function handle(
        NetworkAccessCoordinator $coordinator,
        MikrotikFleetCoordinator $fleet,
    ): int {
        $tenantId = (int) ($this->option('tenant') ?: 1);
        $input = trim((string) $this->argument('customer'));
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        $customer = $this->resolveCustomer($tenantId, $input, $digits);

        if ($customer === null) {
            $this->error("Customer not found (tenant {$tenantId}, lookup: {$input}).");

            return self::FAILURE;
        }

        $this->printDiagnostics($customer, $coordinator, $fleet);

        if ((bool) $this->option('set-active')) {
            $customer->forceFill([
                'status' => CustomerStatus::ACTIVE,
                'network_access_state' => 'active',
            ])->save();
            $customer = $customer->fresh() ?? $customer;
            $this->warn('DB set to active (status + network_access_state).');
        }

        if ((bool) $this->option('force-mikrotik')) {
            CustomerNetworkSync::forceNetOn($customer);
            $this->info('MikroTik PPP enabled (--force-mikrotik). Check RouterOS /ppp/secret now.');

            return self::SUCCESS;
        }

        CustomerNetworkSync::runNow($customer);
        $customer->refresh();

        $this->newLine();
        $this->info("Done. Customer #{$customer->id} ({$customer->customer_code})");
        $this->line("  status: {$customer->status}");
        $this->line('  network_access_state: '.($customer->network_access_state ?? '—'));
        $this->line('  desired after policy: '.$coordinator->desiredNetworkAccessState($customer));

        if (($customer->network_access_state ?? '') === 'suspended') {
            $this->warn('Still suspended in DB — overdue invoice or service expired may block ON.');
            $this->line('  Try: php artisan isp:network-sync-customer '.$customer->customer_code.' --tenant='.$tenantId.' --set-active --force-mikrotik');
        }

        return self::SUCCESS;
    }

    /**
     * Lookup for isp:net-on (code, PPP login, phone, or DB id).
     */
    public function resolveCustomerForNetOn(int $tenantId, string $input): ?Customer
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        return $this->resolveCustomer($tenantId, $input, $digits);
    }

    /**
     * Prefer customer_code / PPP login over numeric DB id (258 = code, not always id 258).
     */
    private function resolveCustomer(int $tenantId, string $input, string $digits): ?Customer
    {
        $base = Customer::query()->withoutGlobalScopes()->where('tenant_id', $tenantId);

        $byCode = (clone $base)->where('customer_code', $input)->first();
        if ($byCode !== null) {
            return $byCode;
        }

        if (ctype_digit($input) && strlen($input) < 4) {
            $padded = str_pad($input, 4, '0', STR_PAD_LEFT);
            $byPadded = (clone $base)->where('customer_code', $padded)->first();
            if ($byPadded !== null) {
                return $byPadded;
            }
        }

        $byLogin = (clone $base)->where(function ($q) use ($input): void {
            $q->where('mikrotik_secret_name', $input)
                ->orWhere('radius_username', $input);
        })->first();
        if ($byLogin !== null) {
            return $byLogin;
        }

        if ($digits !== '') {
            $byPhone = (clone $base)->where(function ($q) use ($digits, $input): void {
                $q->where('phone', $digits)->orWhere('phone', $input);
            })->first();
            if ($byPhone !== null) {
                return $byPhone;
            }
        }

        $numericId = is_numeric($input)
            && $input === (string) (int) $input
            && ! str_starts_with($input, '0');

        if ($numericId) {
            return (clone $base)->where('id', (int) $input)->first();
        }

        return null;
    }

    private function printDiagnostics(
        Customer $customer,
        NetworkAccessCoordinator $coordinator,
        MikrotikFleetCoordinator $fleet,
    ): void {
        $overdue = $coordinator->hasOverdueOpenBalance($customer);
        $servers = $fleet->serversForCustomer($customer);

        $this->table(
            ['Field', 'Value'],
            [
                ['DB id', (string) $customer->id],
                ['Code', (string) $customer->customer_code],
                ['Status', (string) $customer->status],
                ['Network state', (string) ($customer->network_access_state ?? '—')],
                ['Service valid until', $customer->service_expires_at?->toDateString() ?? '—'],
                ['Service expired?', $customer->isServiceExpired() ? 'YES' : 'no'],
                ['Open invoice overdue?', $overdue ? 'YES (sync may OFF again)' : 'no'],
                ['PPP secret name', $customer->pppLoginName()],
                ['Has PPP password', filled($customer->mikrotik_ppp_password) ? 'yes' : 'NO — API may skip'],
                ['mikrotik_server_id', (string) ($customer->mikrotik_server_id ?? '—')],
                ['Routers to push', $servers->isEmpty() ? 'NONE (fix server assignment!)' : $servers->pluck('name', 'id')->map(fn ($n, $id) => "#{$id} {$n}")->implode(', ')],
                ['mikrotik_push_enabled', config('network.mikrotik_push_enabled') ? 'yes' : 'NO'],
            ],
        );
    }
}
