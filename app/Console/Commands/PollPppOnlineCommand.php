<?php

namespace App\Console\Commands;

use App\Livewire\MikrotikSync;
use App\Models\RouterList;
use App\Services\Bandwidth\CustomerTrafficUsageService;
use Illuminate\Console\Command;

/**
 * ispbillling PollMikrotikFleetJob equivalent — refresh PPP online flags.
 */
class PollPppOnlineCommand extends Command
{
    protected $signature = 'app:poll-ppp-online {--router= : Optional router_name}';

    protected $description = 'Poll connected MikroTik routers and mark PPP sessions online (ispbillling-style)';

    public function handle(): int
    {
        $sync = app(MikrotikSync::class);
        $query = RouterList::query()->where('action', 'connected');
        if ($name = $this->option('router')) {
            $query->where('router_name', $name);
        }

        $total = 0;
        foreach ($query->get() as $router) {
            try {
                $n = $sync->refreshOnlineSessions($router->router_name);
                $total += $n;
                $this->info("{$router->router_name}: {$n} online");
            } catch (\Throwable $e) {
                $this->warn("{$router->router_name}: ".$e->getMessage());
            }
        }

        $this->info("Done. Total online marked: {$total}");

        try {
            app(CustomerTrafficUsageService::class)->resetEndedMonths();
        } catch (\Throwable) {
        }

        return self::SUCCESS;
    }
}
