<?php

namespace App\Events\Mobile;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OnuSignalEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $tenantId,
        public int $deviceId,
        public array $snapshot,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.'.$this->tenantId.'.mobile');
    }

    public function broadcastAs(): string
    {
        return 'onu_signal_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'onu' => $this->snapshot,
        ];
    }
}
