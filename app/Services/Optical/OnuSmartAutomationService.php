<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Models\SupportTicket;
use Carbon\Carbon;

/**
 * Smart ONU automation — offline ticket when due clear, RX threshold tickets/alerts.
 */
final class OnuSmartAutomationService
{
    public function __construct(
        private readonly OnuOfflineHandlingService $offlineHandling,
    ) {}

    /**
     * @return array{tickets_created: int, evaluated: int}
     */
    public function runForTenant(int $tenantId): array
    {
        if (! config('onu_management.smart_automation.enabled', true)) {
            return ['tickets_created' => 0, 'evaluated' => 0];
        }

        $created = 0;
        $evaluated = 0;
        $hours = max(1, (int) config('onu_management.smart_automation.offline_ticket_hours', 24));

        Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('customer_id')
            ->whereIn('onu_oper_status', ['offline', 'los', 'power_fail', 'dying_gasp'])
            ->chunkById(100, function ($onus) use ($hours, &$created, &$evaluated): void {
                foreach ($onus as $onu) {
                    $evaluated++;
                    if ($this->shouldAutoTicketForOffline($onu, $hours)) {
                        $created += $this->createOfflineTicket($onu, $hours) ? 1 : 0;
                    }
                }
            });

        return ['tickets_created' => $created, 'evaluated' => $evaluated];
    }

    private function shouldAutoTicketForOffline(Device $onu, int $hours): bool
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $offlineSince = $meta['onu_offline_since'] ?? null;
        if ($offlineSince === null) {
            return false;
        }

        if (Carbon::parse($offlineSince)->diffInHours(now()) < $hours) {
            return false;
        }

        $customer = $onu->customer;
        if ($customer === null) {
            return false;
        }

        if ($customer->openInvoiceBalance() > 0.009) {
            return false;
        }

        return ! SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->whereIn('issue_type', ['onu_offline', 'equipment'])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where('created_at', '>=', Carbon::parse($offlineSince))
            ->exists();
    }

    private function createOfflineTicket(Device $onu, int $hours): bool
    {
        $customer = $onu->customer;
        if ($customer === null) {
            return false;
        }

        try {
            SupportTicket::query()->create([
                'tenant_id' => $onu->tenant_id,
                'customer_id' => $customer->id,
                'channel' => 'system',
                'department' => 'technical_support',
                'priority' => (string) config('onu_management.smart_automation.offline_ticket_priority', 'high'),
                'issue_type' => 'onu_offline',
                'subject' => '[Auto] ONU offline '.$hours.'h+ · zero due',
                'description' => $this->offlineHandling->customerWarning($customer)['message']
                    ?? 'ONU has been offline beyond SLA with no outstanding balance.',
                'status' => 'open',
                'olt_device_id' => $onu->olt_id,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
