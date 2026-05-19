<?php

namespace App\Services\Network;

use App\Models\NetflowExporter;
use App\Models\NetflowFlow;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class NetflowIngestService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{inserted: int, exporter_id: ?int}
     */
    public function ingestPayload(array $payload, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::currentTenantId() ?? 1;
        $exporterIp = isset($payload['exporter_ip']) ? (string) $payload['exporter_ip'] : null;
        $flows = $payload['flows'] ?? [];
        if (! is_array($flows)) {
            return ['inserted' => 0, 'exporter_id' => null];
        }

        $exporterId = null;
        if ($exporterIp) {
            $exporter = NetflowExporter::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'host' => $exporterIp],
                ['name' => 'Exporter '.$exporterIp, 'last_seen_at' => now(), 'is_active' => true]
            );
            $exporterId = $exporter->id;
        }

        $inserted = 0;
        $sampledAt = now();

        foreach ($flows as $flow) {
            if (! is_array($flow)) {
                continue;
            }

            $src = (string) ($flow['src'] ?? $flow['src_ip'] ?? '');
            $dst = (string) ($flow['dst'] ?? $flow['dst_ip'] ?? '');
            if ($src === '' || $dst === '') {
                continue;
            }

            NetflowFlow::query()->create([
                'tenant_id' => $tenantId,
                'netflow_exporter_id' => $exporterId,
                'exporter_ip' => $exporterIp,
                'src_ip' => $src,
                'dst_ip' => $dst,
                'src_port' => isset($flow['sport']) ? (int) $flow['sport'] : (isset($flow['src_port']) ? (int) $flow['src_port'] : null),
                'dst_port' => isset($flow['dport']) ? (int) $flow['dport'] : (isset($flow['dst_port']) ? (int) $flow['dst_port'] : null),
                'protocol' => isset($flow['proto']) ? (string) $flow['proto'] : (isset($flow['protocol']) ? (string) $flow['protocol'] : null),
                'bytes' => (int) ($flow['bytes'] ?? 0),
                'packets' => (int) ($flow['packets'] ?? 0),
                'flow_start' => $this->parseTime($flow['start'] ?? $flow['flow_start'] ?? null),
                'flow_end' => $this->parseTime($flow['end'] ?? $flow['flow_end'] ?? null),
                'sampled_at' => $sampledAt,
            ]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'exporter_id' => $exporterId];
    }

    public function processInboxFiles(): int
    {
        $dir = (string) config('netflow.inbox_path');
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir);

            return 0;
        }

        $total = 0;
        foreach (glob($dir.'/*.json') ?: [] as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $payload = json_decode($content, true);
            if (is_array($payload)) {
                $total += $this->ingestPayload($payload)['inserted'];
            }

            @unlink($file);
        }

        return $total;
    }

    public function purgeOldFlows(): int
    {
        $days = (int) config('netflow.retention_days', 7);

        return NetflowFlow::query()
            ->where('sampled_at', '<', now()->subDays($days))
            ->delete();
    }

    private function parseTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
