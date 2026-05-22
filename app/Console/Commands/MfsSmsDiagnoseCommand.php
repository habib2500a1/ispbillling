<?php

namespace App\Console\Commands;

use App\Models\MfsSmsRecord;
use Illuminate\Console\Command;

class MfsSmsDiagnoseCommand extends Command
{
    protected $signature = 'mfs:sms-diagnose';

    protected $description = 'Check MFS SMS ingest configuration and recent ledger rows';

    public function handle(): int
    {
        $enabled = (bool) config('mfs_personal.sms_ingest.enabled', false);
        $keySet = filled(config('mfs_personal.sms_ingest.api_key'));
        $autoApprove = (bool) config('mfs_personal.sms_ingest.auto_approve_sms', false);

        $this->info('MFS SMS ingest');
        $this->table(
            ['Setting', 'Value'],
            [
                ['enabled', $enabled ? 'YES' : 'NO'],
                ['device_api_key', $keySet ? 'SET' : 'MISSING'],
                ['auto_approve_sms', $autoApprove ? 'YES' : 'NO (ledger = awaiting_review)'],
                ['endpoint', url('/api/v1/mfs/sms/ingest')],
            ],
        );

        $recent = MfsSmsRecord::query()->orderByDesc('id')->limit(10)->get();
        if ($recent->isEmpty()) {
            $this->warn('No SMS records in ledger — verify app is sending (device key + permissions).');
        } else {
            $this->info('Recent ledger ('.$recent->count().' rows):');
            $this->table(
                ['ID', 'Gateway', 'TrxID', 'Amount', 'Status', 'Device', 'At'],
                $recent->map(fn (MfsSmsRecord $r) => [
                    $r->id,
                    $r->gateway,
                    $r->transaction_id,
                    $r->amount,
                    $r->status,
                    $r->device_name ?? '—',
                    $r->created_at?->toDateTimeString(),
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
