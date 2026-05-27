<?php

namespace App\Support;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerStatus;
use Illuminate\Support\Str;

/**
 * Push network access changes to MikroTik/RADIUS immediately (no afterResponse delay).
 */
final class CustomerNetworkSync
{
    public static function runNow(Customer|int $customer, ?int $tenantId = null): void
    {
        if ($customer instanceof Customer) {
            $tenantId = (int) $customer->tenant_id;
            $customerId = (int) $customer->id;
        } else {
            $customerId = (int) $customer;
            $tenantId = (int) ($tenantId ?? 1);
        }

        SyncCustomerNetworkAccessJob::dispatchSync($tenantId, $customerId);

        $fresh = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($customerId);

        if ($fresh !== null) {
            static::pushMikrotikApiIfAvailable($fresh);
        }
    }

    /**
     * After payment is saved: if bill cleared (or paid invoice fully), turn line ON on MikroTik immediately.
     */
    public static function runAfterPayment(Payment $payment): void
    {
        $customer = $payment->customer;
        if ($customer === null) {
            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->find((int) $payment->customer_id);
        }

        if ($customer === null) {
            return;
        }

        $customer = $customer->fresh();
        $openDue = $customer->openInvoiceBalance();

        $paidInvoiceCleared = false;
        if ($payment->invoice_id) {
            $invoice = $payment->invoice?->fresh();
            $paidInvoiceCleared = $invoice !== null && $invoice->balanceDue() <= 0.01;
        }

        if ($openDue <= 0.01 || $paidInvoiceCleared) {
            $customer->forceFill([
                'status' => CustomerStatus::ACTIVE,
                'network_access_state' => 'active',
            ])->saveQuietly();
            $customer = $customer->fresh() ?? $customer;
            static::pushMikrotikApiIfAvailable($customer);
        }

        static::runNow($customer);
    }

    /**
     * New subscriber: create PPP secret on MikroTik when API is configured (meta.auto_pppoe).
     */
    public static function provisionOnCreate(Customer $customer): void
    {
        if (! static::shouldAutoProvisionPpp($customer)) {
            return;
        }

        $customer = static::ensurePppCredentials($customer);
        static::runNow($customer);
    }

    /**
     * When tenant has MikroTik API configured, always push PPP secret enable/disable
     * (even if NETWORK_PROVISIONER_DRIVER is null/log).
     */
    /**
     * Net ON / admin rescue: set panel active and push MikroTik enable immediately.
     * Does not run full policy job first (avoids expiry job re-suspending before API push).
     */
    public static function forceNetOn(Customer $customer): void
    {
        $coordinator = app(NetworkAccessCoordinator::class);

        if (! $coordinator->canAdminForceNetOn($customer)) {
            return;
        }

        $customer->forceFill([
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
        ])->save();

        static::pushMikrotikApiIfAvailable($customer->fresh() ?? $customer);
        static::runNow($customer->fresh() ?? $customer);
    }

    public static function pushMikrotikApiIfAvailable(Customer $customer): void
    {
        if (! (bool) config('network.mikrotik_push_enabled', true)) {
            return;
        }

        if (! static::tenantHasMikrotikApi((int) $customer->tenant_id)) {
            return;
        }

        app(MikrotikNetworkProvisioner::class)->syncAccessPolicy($customer->fresh() ?? $customer);
    }

    private static function shouldAutoProvisionPpp(Customer $customer): bool
    {
        if (! (bool) config('network.mikrotik_push_enabled', true)) {
            return false;
        }

        if (! (bool) config('network.mikrotik_provision_on_customer_create', true)) {
            return false;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        if (array_key_exists('auto_pppoe', $meta) && ! filter_var($meta['auto_pppoe'], FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return static::tenantHasMikrotikApi((int) $customer->tenant_id);
    }

    private static function tenantHasMikrotikApi(int $tenantId): bool
    {
        return MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->exists();
    }

    private static function ensurePppCredentials(Customer $customer): Customer
    {
        $updates = [];

        if (blank($customer->mikrotik_secret_name)) {
            $login = $customer->pppLoginName();
            if ($login !== '') {
                $updates['mikrotik_secret_name'] = $login;
            }
        }

        if ($customer->mikrotik_server_id === null && $customer->package_id !== null) {
            $serverId = Package::query()
                ->withoutGlobalScopes()
                ->whereKey($customer->package_id)
                ->value('mikrotik_server_id');
            if ($serverId !== null) {
                $updates['mikrotik_server_id'] = (int) $serverId;
            }
        }

        if (! filled($customer->mikrotik_ppp_password)) {
            $hasServerDefault = MikrotikServer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $customer->tenant_id)
                ->where('is_enabled', true)
                ->whereNotNull('default_ppp_password')
                ->exists();

            if (! $hasServerDefault) {
                $updates['mikrotik_ppp_password'] = Str::password(10, symbols: false);
            }
        }

        if ($updates === []) {
            return $customer;
        }

        $customer->forceFill($updates)->saveQuietly();

        return $customer->fresh() ?? $customer;
    }
}
