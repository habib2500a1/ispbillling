<?php

namespace App\Jobs;

use App\Http\Controllers\MikrotikController;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCustomerRouterStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 45;

    public function __construct(
        public string $customerUniqueId,
        public string $status = 'active',
    ) {}

    public function handle(): void
    {
        $customer = CustomersInfo::where('customer_unique_id', $this->customerUniqueId)
            ->with('pppUser')
            ->first();

        if (! $customer?->pppUser || ! filled($customer->pppUser->router_name)) {
            return;
        }

        $router = $customer->pppUser->router_name;
        $username = $customer->pppUser->username;

        try {
            if ($this->status === 'active') {
                app(MikrotikController::class)->enablePPPSecret(
                    $customer->customer_unique_id,
                    $router,
                    $username
                );
                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'active']);
            } else {
                app(MikrotikController::class)->disablePPPSecret(
                    $customer->customer_unique_id,
                    $router,
                    $username
                );
                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'disable']);
            }
        } catch (\Throwable $e) {
            Log::debug('SyncCustomerRouterStatusJob skipped: '.$e->getMessage());
        }
    }
}
