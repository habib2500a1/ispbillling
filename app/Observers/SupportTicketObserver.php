<?php

namespace App\Observers;

use App\Events\SupportTicketUpdated;
use App\Models\SupportTicket;
use App\Services\Notifications\OpsNotificationService;
use App\Services\Sms\AutomatedSmsNotifier;
use App\Services\Support\SupportMassOutageService;
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

            app(SupportMassOutageService::class)->processNewTicket($ticket);
            $ticket->refresh();

            $notifier = app(SupportTicketNotifier::class);
            $notifier->notifyStaffNewTicket($ticket);
            $notifier->notifyCustomerTicketOpened($ticket);
            if ($ticket->assigned_to !== null) {
                $notifier->notifyAssignee($ticket);
            }

            app(AutomatedSmsNotifier::class)->onSupportTicketCreated($ticket);
            app(OpsNotificationService::class)->onSupportTicketCreated($ticket);

            $this->broadcastTicket($ticket, 'created');
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
            if (in_array($ticket->status, ['open', 'assigned', 'in_progress', 'pending_customer', 'pending_vendor', 'pending'], true)) {
                if ($ticket->getOriginal('status') === 'resolved') {
                    $ticket->resolved_at = null;
                }
                if ($ticket->getOriginal('status') === 'closed') {
                    $ticket->closed_at = null;
                }
            }
        }

        if ($ticket->isDirty('assigned_to') && $ticket->assigned_to !== null && $ticket->status === 'open') {
            $ticket->status = 'assigned';
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
                app(OpsNotificationService::class)->onSupportTicketResolved($ticket);
            }

            $this->broadcastTicket($ticket, 'updated');
        } catch (Throwable $e) {
            Log::error('support_ticket_observer.updated', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function broadcastTicket(SupportTicket $ticket, string $event): void
    {
        if (! (bool) config('support.broadcast_enabled', true)) {
            return;
        }

        try {
            event(new SupportTicketUpdated(
                (int) $ticket->tenant_id,
                (int) $ticket->id,
                [
                    'event' => $event,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'assigned_to' => $ticket->assigned_to,
                    'escalation_level' => $ticket->escalation_level,
                    'ticket_number' => $ticket->ticket_number,
                ],
            ));
        } catch (Throwable) {
            // Broadcast is optional — polling fallback remains.
        }
    }
}
