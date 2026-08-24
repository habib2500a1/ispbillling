<?php

namespace App\Console\Commands;

use App\Http\Controllers\MikrotikController;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use Illuminate\Console\Command;

/**
 * Manual Net ON — force PPP enable for one customer (ops tool).
 */
class NetOnCustomer extends Command
{
    protected $signature = 'cpagol:net-on {customer : customer_unique_id}';

    protected $description = 'Force Net ON: set customer active and enable MikroTik PPP secret';

    public function handle(): int
    {
        $id = (string) $this->argument('customer');
        $customer = CustomersInfo::query()
            ->where('customer_unique_id', $id)
            ->with(['pppUser', 'billing'])
            ->first();

        if (! $customer) {
            $this->error("Customer not found: {$id}");

            return self::FAILURE;
        }

        if (! $customer->pppUser) {
            $this->error('Customer has no PPP secret linked.');

            return self::FAILURE;
        }

        $customer->status = 'active';
        $customer->disable_count = 0;
        $customer->save();

        try {
            app(MikrotikController::class)->enablePPPSecret(
                $customer->customer_unique_id,
                $customer->pppUser->router_name,
                $customer->pppUser->username
            );
            app(MikrotikController::class)->updatePPPSecret(
                $customer->pppUser->router_name,
                $customer->pppUser->username,
                'profile',
                $customer->pppUser->profile
            );
            PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'active']);
        } catch (\Throwable $e) {
            $this->warn('DB status set active, but MikroTik call failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Net ON OK for {$id} ({$customer->pppUser->username}).");

        return self::SUCCESS;
    }
}
