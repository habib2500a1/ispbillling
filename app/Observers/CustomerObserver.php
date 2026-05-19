<?php

namespace App\Observers;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Services\Radius\CustomerRadiusSyncService;
use App\Services\Sms\AutomatedSmsNotifier;
use App\Models\CustomerNote;
use App\Models\MikrotikServer;
use App\Support\CustomerStatus;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        try {
            app(AutomatedSmsNotifier::class)->onClientCreated($customer);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('customer.sms_created_failed', [
                'customer_id' => $customer->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function saved(Customer $customer): void
    {
        if ($customer->wasChanged('status') && $customer->getOriginal('status') !== null) {
            $from = CustomerStatus::normalize((string) $customer->getOriginal('status'));
            $to = CustomerStatus::normalize((string) $customer->status);
            if ($from !== $to) {
                try {
                    app(AutomatedSmsNotifier::class)->onClientStatusChanged($customer, $from, $to);
                } catch (\Throwable $e) {
                    Log::channel('single')->warning('customer.sms_status_failed', [
                        'customer_id' => $customer->id,
                        'message' => $e->getMessage(),
                    ]);
                }
                CustomerNote::query()->create([
                    'customer_id' => $customer->id,
                    'tenant_id' => $customer->tenant_id,
                    'user_id' => auth('web')->id(),
                    'category' => 'status_change',
                    'body' => sprintf('Status changed from %s to %s.', CustomerStatus::label($from), CustomerStatus::label($to)),
                    'meta' => ['from' => $from, 'to' => $to],
                ]);
            }
        }

        if ($customer->contacts()->exists()) {
            $customer->syncPrimaryPhoneFromContacts();
        }

        if ($customer->wasChanged(['radius_username', 'package_id', 'portal_password'])
            && (bool) config('radius_admin.enabled', false)) {
            try {
                app(CustomerRadiusSyncService::class)->sync($customer);
            } catch (\Throwable $e) {
                Log::channel('single')->warning('customer.radius_sync_failed', [
                    'customer_id' => $customer->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            $status = CustomerStatus::normalize((string) $customer->status);
            if (! in_array($status, [CustomerStatus::ACTIVE, CustomerStatus::SUSPENDED], true)) {
                return;
            }

            if (! (bool) config('network.mikrotik_push_enabled', true)) {
                return;
            }

            if (! MikrotikServer::query()->withoutGlobalScopes()
                ->where('tenant_id', $customer->tenant_id)
                ->where('is_enabled', true)
                ->exists()) {
                return;
            }

            $driver = (string) config('network.provisioner_driver', 'null');
            $driverUsesMikrotik = in_array($driver, ['mikrotik', 'both'], true);
            $alwaysApiPpp = (bool) config('network.mikrotik_always_push_ppp_on_customer_save', true);

            if (! $driverUsesMikrotik && ! $alwaysApiPpp) {
                return;
            }

            SyncCustomerNetworkAccessJob::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterResponse();
        } catch (\Throwable $e) {
            Log::channel('single')->error('customer.observer.saved_failed', [
                'customer_id' => $customer->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}

