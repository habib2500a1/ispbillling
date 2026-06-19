<?php

namespace App\Services\Support;

use App\Models\SupportRootIncident;
use App\Models\SupportTicket;
use Illuminate\Support\Collection;

final class SupportNocWallService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? (int) (\App\Support\TenantResolver::requiredTenantId());

        $open = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['resolved', 'closed']);

        $critical = (clone $open)->where('priority', 'critical')->count();
        $slaBreach = (clone $open)
            ->whereNotNull('sla_resolve_due_at')
            ->where('sla_resolve_due_at', '<', now())
            ->count();
        $firstResponseRisk = (clone $open)
            ->whereNull('first_responded_at')
            ->whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '<', now()->addMinutes(15))
            ->count();

        return [
            'open' => (clone $open)->count(),
            'critical' => $critical,
            'sla_breach' => $slaBreach,
            'first_response_risk' => $firstResponseRisk,
            'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
            'root_incidents' => $this->activeIncidents($tenantId),
            'olt_complaints' => $this->oltComplaintRows($tenantId),
            'map_points' => $this->mapPoints($tenantId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeIncidents(int $tenantId): array
    {
        return SupportRootIncident::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with(['olt:id,display_name,hostname', 'primaryTicket:id,ticket_number,subject'])
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get()
            ->map(fn (SupportRootIncident $i): array => [
                'id' => $i->id,
                'number' => $i->incident_number,
                'title' => $i->title,
                'ticket_count' => $i->ticket_count,
                'olt' => $i->olt?->display_name ?? $i->olt?->hostname ?? '—',
                'detected_at' => $i->detected_at?->diffForHumans(),
                'primary_ticket' => $i->primaryTicket?->ticket_number,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function oltComplaintRows(int $tenantId): array
    {
        return SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('olt_device_id')
            ->selectRaw('olt_device_id, COUNT(*) as ticket_count')
            ->groupBy('olt_device_id')
            ->orderByDesc('ticket_count')
            ->limit(15)
            ->get()
            ->map(function ($row) use ($tenantId): array {
                $olt = \App\Models\Device::withoutGlobalScopes()->find($row->olt_device_id);

                return [
                    'olt_id' => (int) $row->olt_device_id,
                    'olt_name' => $olt?->display_name ?? $olt?->hostname ?? 'OLT #'.$row->olt_device_id,
                    'open_tickets' => (int) $row->ticket_count,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapPoints(int $tenantId): array
    {
        $tickets = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->with(['customer:id,name,customer_code,meta'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $tickets
            ->map(function (SupportTicket $ticket): ?array {
                $meta = is_array($ticket->customer?->meta) ? $ticket->customer->meta : [];
                $lat = $meta['gps_lat'] ?? null;
                $lng = $meta['gps_lng'] ?? null;
                if (! is_numeric($lat) || ! is_numeric($lng)) {
                    return null;
                }

                return [
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'label' => $ticket->ticket_number.' · '.($ticket->customer?->name ?? ''),
                    'priority' => $ticket->priority,
                    'ticket_id' => $ticket->id,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
