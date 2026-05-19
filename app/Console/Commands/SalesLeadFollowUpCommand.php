<?php

namespace App\Console\Commands;

use App\Models\SalesLead;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SalesLeadFollowUpCommand extends Command
{
    protected $signature = 'isp:sales-lead-follow-ups';

    protected $description = 'Notify assignees about overdue sales lead follow-ups';

    public function handle(): int
    {
        $count = 0;

        SalesLead::query()
            ->whereNull('converted_customer_id')
            ->whereIn('status', [SalesLead::STATUS_NEW, SalesLead::STATUS_CONTACTED, SalesLead::STATUS_QUALIFIED])
            ->whereNotNull('assigned_to')
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->each(function (SalesLead $lead) use (&$count): void {
                $cacheKey = 'sales_lead_followup_'.$lead->id.'_'.now()->toDateString();
                if (Cache::has($cacheKey)) {
                    return;
                }

                $user = User::query()->find($lead->assigned_to);
                if ($user === null) {
                    return;
                }

                Notification::make()
                    ->title('Sales lead follow-up due')
                    ->body($lead->name.' — '.$lead->phone)
                    ->warning()
                    ->sendToDatabase($user);

                Cache::put($cacheKey, true, now()->addDay());
                $count++;
            });

        $this->info("Sent {$count} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
