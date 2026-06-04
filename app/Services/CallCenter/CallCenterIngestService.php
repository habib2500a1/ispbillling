<?php

namespace App\Services\CallCenter;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Support\TenantResolver;
use Illuminate\Support\Carbon;

final class CallCenterIngestService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(array $payload, int $tenantId): CallLog
    {
        $externalId = filled($payload['external_id'] ?? null)
            ? (string) $payload['external_id']
            : null;

        if ($externalId !== null) {
            $existing = CallLog::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;
        if ($customerId === null && filled($payload['phone'] ?? null)) {
            $phone = preg_replace('/\D+/', '', (string) $payload['phone']) ?: '';
            if ($phone !== '') {
                $customerId = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($phone): void {
                        $q->where('phone', 'like', '%'.$phone)
                            ->orWhere('phone', 'like', '%'.substr($phone, -10));
                    })
                    ->value('id');
            }
        }

        $startedAt = filled($payload['started_at'] ?? null)
            ? Carbon::parse((string) $payload['started_at'])
            : now();

        $log = CallLog::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'staff_user_id' => isset($payload['staff_user_id']) ? (int) $payload['staff_user_id'] : null,
            'direction' => (string) ($payload['direction'] ?? CallLog::DIRECTION_OUTBOUND),
            'phone' => $payload['phone'] ?? null,
            'staff_extension' => $payload['staff_extension'] ?? null,
            'status' => (string) ($payload['status'] ?? 'completed'),
            'duration_seconds' => (int) ($payload['duration_seconds'] ?? 0),
            'remarks' => $payload['remarks'] ?? null,
            'recording_url' => $payload['recording_url'] ?? null,
            'external_id' => $externalId,
            'started_at' => $startedAt,
            'ended_at' => filled($payload['ended_at'] ?? null)
                ? Carbon::parse((string) $payload['ended_at'])
                : $startedAt->copy()->addSeconds((int) ($payload['duration_seconds'] ?? 0)),
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
        ]);

        if (
            config('call_center.auto_ticket_on_missed')
            && in_array($log->status, ['missed', 'no_answer', 'busy'], true)
            && $customerId !== null
        ) {
            $this->maybeOpenTicket($log, $tenantId);
        }

        return $log;
    }

    private function maybeOpenTicket(CallLog $log, int $tenantId): void
    {
        $customer = $log->customer;
        if ($customer === null) {
            return;
        }

        SupportTicket::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'channel' => 'call_center',
            'subject' => 'Missed call — '.($log->phone ?? 'unknown'),
            'description' => 'Auto-created from call center ingest.',
            'status' => 'open',
            'priority' => 'normal',
            'department' => 'support',
        ]);
    }
}
