<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Device;
use App\Models\PopBox;
use App\Models\SupportAssignmentRule;
use App\Models\SupportRootIncident;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Collection;

final class SupportMassOutageService
{
    public function processNewTicket(SupportTicket $ticket): void
    {
        if (! (bool) config('support.mass_outage.enabled', true)) {
            return;
        }

        if ($ticket->root_incident_id !== null || $ticket->parent_ticket_id !== null) {
            return;
        }

        if (! $this->isOutageLikeIssue((string) $ticket->issue_type)) {
            return;
        }

        $ticket->loadMissing(['customer.onuDevice.olt']);
        $oltId = $ticket->olt_device_id ?? $this->resolveOltId($ticket->customer);

        if ($oltId === null) {
            return;
        }

        if ($ticket->olt_device_id === null) {
            $ticket->forceFill(['olt_device_id' => $oltId])->saveQuietly();
        }

        $windowHours = max(1, (int) config('support.mass_outage.window_hours', 4));
        $threshold = max(2, (int) config('support.mass_outage.ticket_threshold', 5));

        $related = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('olt_device_id', $oltId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();

        if ($related < $threshold) {
            return;
        }

        $incident = $this->findOrCreateIncident($ticket, $oltId);
        $this->attachTicketToIncident($ticket, $incident);
    }

    public function attachTicketToIncident(SupportTicket $ticket, SupportRootIncident $incident): void
    {
        $primaryId = $incident->primary_ticket_id ?? $ticket->id;

        $updates = [
            'root_incident_id' => $incident->id,
            'merged_at' => $ticket->merged_at ?? now(),
        ];

        if ((int) $ticket->id !== (int) $primaryId) {
            $updates['parent_ticket_id'] = $primaryId;
        }

        $ticket->forceFill($updates)->saveQuietly();

        $count = SupportTicket::withoutGlobalScopes()
            ->where('root_incident_id', $incident->id)
            ->count();

        $incident->forceFill([
            'ticket_count' => $count,
            'primary_ticket_id' => $primaryId,
        ])->saveQuietly();
    }

    private function findOrCreateIncident(SupportTicket $ticket, int $oltId): SupportRootIncident
    {
        $existing = SupportRootIncident::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('olt_device_id', $oltId)
            ->where('status', 'active')
            ->where('detected_at', '>=', now()->subHours(max(1, (int) config('support.mass_outage.window_hours', 4))))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $olt = Device::withoutGlobalScopes()->find($oltId);
        $oltName = $olt?->display_name ?? $olt?->hostname ?? 'OLT #'.$oltId;

        return SupportRootIncident::query()->create([
            'tenant_id' => $ticket->tenant_id,
            'incident_number' => SupportRootIncident::generateNumber((int) $ticket->tenant_id),
            'title' => "Root incident: {$oltName} outage",
            'description' => 'Auto-detected mass outage — multiple subscriber tickets on the same OLT.',
            'status' => 'active',
            'olt_device_id' => $oltId,
            'pop_box_id' => $ticket->pop_box_id,
            'area_id' => $ticket->customer?->area_id,
            'primary_ticket_id' => $ticket->id,
            'ticket_count' => 1,
            'detected_at' => now(),
        ]);
    }

    private function resolveOltId(?Customer $customer): ?int
    {
        if ($customer === null) {
            return null;
        }

        $customer->loadMissing(['onuDevice.olt']);
        $onu = $customer->primaryOnu();
        if ($onu?->olt_id !== null) {
            return (int) $onu->olt_id;
        }

        return $onu?->olt?->id !== null ? (int) $onu->olt->id : null;
    }

    private function isOutageLikeIssue(string $issueType): bool
    {
        $types = config('support.mass_outage.issue_types', []);

        return in_array($issueType, $types, true);
    }
}
