<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $tenantId,
        public int $ticketId,
        public array $payload,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.'.$this->tenantId.'.support'),
            new Channel('tenant.'.$this->tenantId.'.support.ticket.'.$this->ticketId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.ticket.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge(['ticket_id' => $this->ticketId], $this->payload);
    }
}
