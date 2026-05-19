<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckSupportTicketSlaCommand extends Command
{
    protected $signature = 'isp:support-check-sla';

    protected $description = 'Notify staff of overdue support tickets (once per ticket until resolved)';

    public function handle(SupportTicketNotifier $notifier): int
    {
        $query = SupportTicket::withoutGlobalScopes()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_resolve_due_at')
            ->where('sla_resolve_due_at', '<', now())
            ->whereNull('sla_breached_notified_at');

        $count = 0;
        $query->each(function (SupportTicket $ticket) use ($notifier, &$count): void {
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
                Log::error('isp:support-check-sla ticket failed', [
                    'ticket_id' => $ticket->id,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        });

        $this->info("Flagged {$count} overdue ticket(s).");

        return self::SUCCESS;
    }
}
