<?php

namespace App\Services\Collector;

use App\Models\CollectorVisit;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;

final class CollectorVisitService
{
    /**
     * @param  array<string, mixed>  $location
     */
    public function logFromPayment(Payment $payment, User $collector, array $location = []): CollectorVisit
    {
        return CollectorVisit::query()->create([
            'tenant_id' => $payment->tenant_id,
            'collector_id' => $collector->id,
            'customer_id' => $payment->customer_id,
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'amount_collected' => $payment->amount,
            'payment_method' => $payment->method,
            'latitude' => isset($location['latitude']) ? (float) $location['latitude'] : null,
            'longitude' => isset($location['longitude']) ? (float) $location['longitude'] : null,
            'accuracy_meters' => isset($location['accuracy_meters']) ? (int) $location['accuracy_meters'] : null,
            'location_text' => $location['location_text'] ?? null,
            'notes' => $location['notes'] ?? $payment->notes,
            'device_meta' => $location['device_meta'] ?? null,
            'visited_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordCollection(User $collector, Customer $customer, Payment $payment, array $data = []): CollectorVisit
    {
        return $this->logFromPayment($payment, $collector, $data);
    }
}
