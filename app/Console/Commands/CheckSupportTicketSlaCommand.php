<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Services\Support\SupportSlaService;
use App\Services\Support\SupportTicketNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckSupportTicketSlaCommand extends Command
{
    protected $signature = 'isp:support-check-sla';

    protected $description = 'Notify staff of overdue support tickets and first-response SLA breaches';

    public function handle(SupportTicketNotifier $notifier, SupportSlaService $sla): int
    {
        $resolveCount = $this->flagResolveBreaches($notifier);
        $firstResponseCount = $this->flagFirstResponseBreaches($notifier, $sla);

        $this->info("Flagged {$resolveCount} resolve breach(es) and {$firstResponseCount} first-response breach(es).");

        return self::SUCCESS;
    }

    private function flagResolveBreaches(SupportTicketNotifier $notifier): int
    {
        $count = 0;

        SupportTicket::withoutGlobalScopes()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_resolve_due_at')
            ->where('sla_resolve_due_at', '<', now())
            ->whereNull('sla_breached_notified_at')
            ->each(function (SupportTicket $ticket) use ($notifier, &$count): void {
                try {
                    $nextLevel = min(3, max(1, (int) $ticket->escalation_level + 1));

                    $ticket->forceFill([
                        'sla_breached_notified_at' => now(),
                        'escalation_level' => $nextLevel,
                        'escalated_at' => now(),
                    ])->saveQuietly();

                    $notifier->notifySlaEscalation($ticket->fresh(), $nextLevel);
                    $count++;
                } catch (Throwable $e) {
                    Log::error('isp:support-check-sla resolve failed', [
                        'ticket_id' => $ticket->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            });

        return $count;
    }

    private function flagFirstResponseBreaches(SupportTicketNotifier $notifier, SupportSlaService $sla): int
    {
        $count = 0;

        SupportTicket::withoutGlobalScopes()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('first_responded_at')
            ->whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '<', now())
            ->whereNull('first_response_breached_notified_at')
            ->each(function (SupportTicket $ticket) use ($notifier, $sla, &$count): void {
                if (! $sla->isFirstResponseBreached($ticket)) {
                    return;
                }

                try {
                    $ticket->forceFill(['first_response_breached_notified_at' => now()])->saveQuietly();
                    $notifier->notifyFirstResponseBreached($ticket->fresh());
                    $count++;
                } catch (Throwable $e) {
                    Log::error('isp:support-check-sla first-response failed', [
                        'ticket_id' => $ticket->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            });

        return $count;
    }
}
