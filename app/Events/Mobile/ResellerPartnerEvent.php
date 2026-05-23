<?php

namespace App\Events\Mobile;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResellerPartnerEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Payment $payment,
        public int $resellerId,
        public string $eventName,
        public array $payload = [],
    ) {
        $this->payment->loadMissing('customer:id,name,customer_code,reseller_id');
    }

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.'.$this->payment->tenant_id.'.reseller.'.$this->resellerId);
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'customer' => $this->payment->customer?->only(['id', 'name', 'customer_code']),
        ], $this->payload);
    }
}
