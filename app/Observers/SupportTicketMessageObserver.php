<?php

namespace App\Observers;

use App\Models\SupportTicketMessage;
use App\Services\Support\SupportSlaService;
use App\Services\Support\SupportTicketNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportTicketMessageObserver
{
    public function created(SupportTicketMessage $message): void
    {
        $ticket = $message->ticket;
        if ($ticket === null) {
            return;
        }

        if ($message->user_id !== null) {
            try {
                app(SupportSlaService::class)->markFirstResponse($ticket);
            } catch (Throwable $e) {
                Log::error('support_ticket_message_observer.first_response', [
                    'message_id' => $message->id,
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($message->is_internal || $message->user_id === null) {
            return;
        }

        try {
            app(SupportTicketNotifier::class)->notifyCustomerPublicReply($ticket, (string) $message->body);
        } catch (Throwable $e) {
            Log::error('support_ticket_message_observer.created', [
                'message_id' => $message->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
