<?php

namespace App\Console\Commands;

use App\Models\VoiceSmsCampaign;
use App\Services\CallCenter\VoiceSmsCampaignRunner;
use Illuminate\Console\Command;

class ProcessVoiceSmsCampaignsCommand extends Command
{
    protected $signature = 'isp:process-voice-sms-campaigns {--dry-run : Count targets only}';

    protected $description = 'Run scheduled voice SMS campaigns (sends transcript via SMS gateway)';

    public function handle(VoiceSmsCampaignRunner $runner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $due = VoiceSmsCampaign::query()
            ->withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->where(function ($q): void {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('id')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No voice SMS campaigns due.');

            return self::SUCCESS;
        }

        foreach ($due as $campaign) {
            try {
                $stats = $runner->run($campaign, $dryRun);
                $this->line(sprintf(
                    'Campaign #%d %s — targets %d, sent %d, failed %d',
                    $campaign->id,
                    $campaign->name,
                    $stats['targets'],
                    $stats['sent'],
                    $stats['failed'],
                ));
            } catch (\Throwable $e) {
                $this->error("Campaign #{$campaign->id}: {$e->getMessage()}");
                $campaign->forceFill(['status' => 'failed'])->saveQuietly();
            }
        }

        return self::SUCCESS;
    }
}
