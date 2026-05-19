<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\NetflowFlow;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NetflowAnalysisService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $tenantId = null, int $hours = 24): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $since = now()->subHours($hours);

        $base = NetflowFlow::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since);

        $totals = (clone $base)->selectRaw('SUM(bytes) as total_bytes, SUM(packets) as total_packets, COUNT(*) as flow_count')->first();

        return [
            'hours' => $hours,
            'flow_count' => (int) ($totals->flow_count ?? 0),
            'total_bytes' => (int) ($totals->total_bytes ?? 0),
            'total_packets' => (int) ($totals->total_packets ?? 0),
            'top_sources' => $this->topSources($tenantId, $hours),
            'top_destinations' => $this->topDestinations($tenantId, $hours),
            'top_protocols' => $this->topProtocols($tenantId, $hours),
            'subscriber_usage' => $this->subscriberUsage($tenantId, $hours),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topSources(int $tenantId, int $hours, int $limit = 0): array
    {
        $limit = $limit > 0 ? $limit : (int) config('netflow.aggregate_top_n', 25);
        $since = now()->subHours($hours);

        return NetflowFlow::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->select('src_ip', DB::raw('SUM(bytes) as bytes'), DB::raw('SUM(packets) as packets'), DB::raw('COUNT(*) as flows'))
            ->groupBy('src_ip')
            ->orderByDesc('bytes')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'ip' => $row->src_ip,
                'bytes' => (int) $row->bytes,
                'packets' => (int) $row->packets,
                'flows' => (int) $row->flows,
                'bytes_human' => $this->formatBytes((int) $row->bytes),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topDestinations(int $tenantId, int $hours, int $limit = 0): array
    {
        $limit = $limit > 0 ? $limit : (int) config('netflow.aggregate_top_n', 25);
        $since = now()->subHours($hours);

        return NetflowFlow::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->select('dst_ip', DB::raw('SUM(bytes) as bytes'), DB::raw('COUNT(*) as flows'))
            ->groupBy('dst_ip')
            ->orderByDesc('bytes')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'ip' => $row->dst_ip,
                'bytes' => (int) $row->bytes,
                'flows' => (int) $row->flows,
                'bytes_human' => $this->formatBytes((int) $row->bytes),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topProtocols(int $tenantId, int $hours): array
    {
        $since = now()->subHours($hours);

        return NetflowFlow::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->select('protocol', DB::raw('SUM(bytes) as bytes'), DB::raw('COUNT(*) as flows'))
            ->groupBy('protocol')
            ->orderByDesc('bytes')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'protocol' => $row->protocol ?? 'unknown',
                'bytes' => (int) $row->bytes,
                'flows' => (int) $row->flows,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subscriberUsage(int $tenantId, int $hours, int $limit = 15): array
    {
        $since = now()->subHours($hours);
        $customerIps = $this->customerIpMap($tenantId);
        if ($customerIps->isEmpty()) {
            return [];
        }

        $ipToCustomer = $customerIps->flip();
        $usage = [];

        $flows = NetflowFlow::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->get(['src_ip', 'dst_ip', 'bytes']);

        foreach ($flows as $flow) {
            $cid = $ipToCustomer[$flow->src_ip] ?? $ipToCustomer[$flow->dst_ip] ?? null;
            if ($cid === null) {
                continue;
            }
            $usage[$cid] = ($usage[$cid] ?? 0) + (int) $flow->bytes;
        }

        arsort($usage);
        $usage = array_slice($usage, 0, $limit, true);

        $customers = Customer::withoutGlobalScopes()
            ->whereIn('id', array_keys($usage))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($usage as $customerId => $bytes) {
            $c = $customers->get($customerId);
            $rows[] = [
                'customer_id' => $customerId,
                'customer' => $c?->name ?? '—',
                'code' => $c?->customer_code ?? '—',
                'bytes' => $bytes,
                'bytes_human' => $this->formatBytes($bytes),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<string, int> ip => customer_id
     */
    private function customerIpMap(int $tenantId): Collection
    {
        $map = collect();

        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['devices' => fn ($q) => $q->whereNotNull('framed_ip_address')])
            ->get(['id']);

        foreach ($customers as $customer) {
            foreach ($customer->devices as $device) {
                if (filled($device->framed_ip_address)) {
                    $map[(string) $device->framed_ip_address] = (int) $customer->id;
                }
            }
        }

        return $map;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 ** 2) {
            return round($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 ** 3) {
            return round($bytes / 1024 ** 2, 1).' MB';
        }

        return round($bytes / 1024 ** 3, 2).' GB';
    }
}
