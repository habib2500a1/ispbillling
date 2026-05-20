<?php

namespace App\Events\Mobile;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RouterAlertEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $tenantId,
        public string $message,
        public array $meta = [],
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.'.$this->tenantId.'.mobile');
    }

    public function broadcastAs(): string
    {
        return 'router_alert';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'meta' => $this->meta,
        ];
    }
}
