<?php

namespace App\Observers;

use App\Events\SupportTicketMessageCreated;
use App\Models\SupportTicketMessage;
use App\Services\Support\SupportSlaService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportTicketMessageObserver
{
    public function created(SupportTicketMessage $message): void
    {
        if (! (bool) config('support.broadcast_enabled', true)) {
            return;
        }

        try {
            $message->loadMissing('ticket');
            $ticket = $message->ticket;
            if ($ticket === null) {
                return;
            }

            if (! $message->is_internal) {
                app(SupportSlaService::class)->markFirstResponse($ticket);
            }

            event(new SupportTicketMessageCreated(
                (int) $message->tenant_id,
                (int) $message->support_ticket_id,
                (int) $message->id,
                [
                    'is_internal' => (bool) $message->is_internal,
                    'preview' => \Illuminate\Support\Str::limit($message->body, 120),
                ],
            ));
        } catch (Throwable $e) {
            Log::error('support_ticket_message_observer.created', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
