<?php

namespace App\Console\Commands;

use App\Models\Olt;
use App\Services\Olt\OltHealthProbeService;
use Illuminate\Console\Command;

class PollOltHealth extends Command
{
    protected $signature = 'olt:poll-health {--olt= : OLT id to poll only}';

    protected $description = 'SNMP health poll for registered OLTs';

    public function handle(OltHealthProbeService $probe): int
    {
        $query = Olt::query()->where('status', 'active');
        if ($id = $this->option('olt')) {
            $query->whereKey((int) $id);
        }

        $olts = $query->get();
        if ($olts->isEmpty()) {
            $this->warn('No active OLTs to poll.');

            return self::SUCCESS;
        }

        $ok = 0;
        foreach ($olts as $olt) {
            try {
                $probe->probeAndPersist($olt);
                $ok++;
                $this->line("Polled: {$olt->name}");
            } catch (\Throwable $e) {
                $this->error("{$olt->name}: {$e->getMessage()}");
            }
        }

        $this->info("OLT health poll finished — {$ok}/{$olts->count()} OK.");

        return self::SUCCESS;
    }
}
