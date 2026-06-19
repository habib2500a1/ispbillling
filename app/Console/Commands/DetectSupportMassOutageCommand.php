<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\SupportRootIncident;
use App\Models\SupportTicket;
use App\Services\Support\SupportMassOutageService;
use Illuminate\Console\Command;

class DetectSupportMassOutageCommand extends Command
{
    protected $signature = 'isp:support-detect-mass-outage {--tenant= : Tenant ID}';

    protected $description = 'Scan open tickets for OLT clusters and attach root incidents';

    public function handle(SupportMassOutageService $service): int
    {
        if (! (bool) config('support.mass_outage.enabled', true)) {
            $this->warn('Mass outage detection is disabled.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $windowHours = max(1, (int) config('support.mass_outage.window_hours', 4));
        $threshold = max(2, (int) config('support.mass_outage.ticket_threshold', 5));
        $issueTypes = config('support.mass_outage.issue_types', []);
        $attached = 0;

        $query = SupportTicket::withoutGlobalScopes()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('olt_device_id')
            ->whereNull('root_incident_id')
            ->where('created_at', '>=', now()->subHours($windowHours));

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($issueTypes !== []) {
            $query->whereIn('issue_type', $issueTypes);
        }

        $clusters = $query
            ->selectRaw('tenant_id, olt_device_id, COUNT(*) as ticket_count')
            ->groupBy('tenant_id', 'olt_device_id')
            ->having('ticket_count', '>=', $threshold)
            ->get();

        foreach ($clusters as $cluster) {
            $existing = SupportRootIncident::withoutGlobalScopes()
                ->where('tenant_id', $cluster->tenant_id)
                ->where('olt_device_id', $cluster->olt_device_id)
                ->where('status', 'active')
                ->where('detected_at', '>=', now()->subHours($windowHours))
                ->first();

            $primary = SupportTicket::withoutGlobalScopes()
                ->where('tenant_id', $cluster->tenant_id)
                ->where('olt_device_id', $cluster->olt_device_id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderBy('id')
                ->first();

            if ($primary === null) {
                continue;
            }

            $incident = $existing ?? SupportRootIncident::query()->create([
                'tenant_id' => $primary->tenant_id,
                'incident_number' => SupportRootIncident::generateNumber((int) $primary->tenant_id),
                'title' => 'Root incident: '.($this->oltLabel((int) $cluster->olt_device_id)),
                'description' => 'Batch-detected mass outage on OLT cluster.',
                'status' => 'active',
                'olt_device_id' => $cluster->olt_device_id,
                'primary_ticket_id' => $primary->id,
                'ticket_count' => (int) $cluster->ticket_count,
                'detected_at' => now(),
            ]);

            SupportTicket::withoutGlobalScopes()
                ->where('tenant_id', $cluster->tenant_id)
                ->where('olt_device_id', $cluster->olt_device_id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->whereNull('root_incident_id')
                ->orderBy('id')
                ->each(function (SupportTicket $ticket) use ($service, $incident, &$attached): void {
                    $service->attachTicketToIncident($ticket, $incident);
                    $attached++;
                });
        }

        $this->info("Attached {$attached} ticket(s) to root incident(s).");

        return self::SUCCESS;
    }

    private function oltLabel(int $oltId): string
    {
        $olt = Device::withoutGlobalScopes()->find($oltId);

        return $olt?->display_name ?? $olt?->hostname ?? 'OLT #'.$oltId;
    }
}
