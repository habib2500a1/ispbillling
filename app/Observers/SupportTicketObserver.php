<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Services\Sms\AutomatedSmsNotifier;
use App\Services\Support\SupportTicketAutoAssignment;
use App\Services\Support\SupportTicketNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportTicketObserver
{
    public function created(SupportTicket $ticket): void
    {
        try {
            app(SupportTicketAutoAssignment::class)->assignIfUnassigned($ticket);
            $ticket->refresh();

            $notifier = app(SupportTicketNotifier::class);
            $notifier->notifyStaffNewTicket($ticket);
            $notifier->notifyCustomerTicketOpened($ticket);
            if ($ticket->assigned_to !== null) {
                $notifier->notifyAssignee($ticket);
            }

            app(AutomatedSmsNotifier::class)->onSupportTicketCreated($ticket);
        } catch (Throwable $e) {
            Log::error('support_ticket_observer.created', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    public function updating(SupportTicket $ticket): void
    {
        if ($ticket->isDirty('status')) {
            if ($ticket->status === 'resolved' && $ticket->resolved_at === null) {
                $ticket->resolved_at = now();
            }
            if ($ticket->status === 'closed' && $ticket->closed_at === null) {
                $ticket->closed_at = now();
            }
            if (in_array($ticket->status, ['open', 'in_progress', 'pending'], true)) {
                if ($ticket->getOriginal('status') === 'resolved') {
                    $ticket->resolved_at = null;
                }
                if ($ticket->getOriginal('status') === 'closed') {
                    $ticket->closed_at = null;
                }
            }
        }
    }

    public function updated(SupportTicket $ticket): void
    {
        try {
            $notifier = app(SupportTicketNotifier::class);

            if ($ticket->wasChanged('assigned_to') && $ticket->assigned_to !== null) {
                $notifier->notifyAssignee($ticket);
            }

            if ($ticket->wasChanged('status') && in_array($ticket->status, ['resolved', 'closed'], true)) {
                $notifier->notifyCustomerResolved($ticket);
                app(AutomatedSmsNotifier::class)->onSupportTicketResolved($ticket);
            }
        } catch (Throwable $e) {
            Log::error('support_ticket_observer.updated', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
