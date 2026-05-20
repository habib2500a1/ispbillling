<?php

namespace App\Events\Mobile;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Payment $payment)
    {
        $this->payment->loadMissing('customer:id,name,customer_code');
    }

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.'.$this->payment->tenant_id.'.mobile');
    }

    public function broadcastAs(): string
    {
        return 'payment_received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'customer' => $this->payment->customer?->only(['id', 'name', 'customer_code']),
        ];
    }
}
