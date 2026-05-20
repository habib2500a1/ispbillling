<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Device;
use App\Models\SupportTicket;
use App\Models\User;

final class MobileNocService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $tenantId = (int) $user->tenant_id;

        $oltCount = Device::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'olt')->count();
        $onuCount = Device::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'onu')->count();
        $onuWeak = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('rx_power_dbm')
            ->where('rx_power_dbm', '<', -27)
            ->count();

        $online = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->filter(fn (Customer $c) => $c->isPppOnline())
            ->count();

        $openTickets = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->count();

        $alerts = [];
        if ($onuWeak > 0) {
            $alerts[] = ['type' => 'weak_signal', 'count' => $onuWeak, 'message' => "{$onuWeak} ONU(s) with weak RX power"];
        }
        if ($openTickets > 10) {
            $alerts[] = ['type' => 'tickets_high', 'count' => $openTickets, 'message' => 'High open ticket volume'];
        }

        return [
            'olt_count' => $oltCount,
            'onu_count' => $onuCount,
            'onu_weak_signal' => $onuWeak,
            'customers_online' => $online,
            'open_tickets' => $openTickets,
            'alerts' => $alerts,
        ];
    }
}
