<?php

namespace App\Observers;

use App\Models\SupportTicketMessage;
use App\Services\Support\SupportTicketNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportTicketMessageObserver
{
    public function created(SupportTicketMessage $message): void
    {
        if ($message->is_internal) {
            return;
        }

        if ($message->user_id === null) {
            return;
        }

        $ticket = $message->ticket;
        if ($ticket === null) {
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
