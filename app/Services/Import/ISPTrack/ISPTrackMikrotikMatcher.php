<?php

namespace App\Services\Import\ISPTrack;

use App\Models\MikrotikServer;

final class ISPTrackMikrotikMatcher
{
    /**
     * @param  list<array<string, mixed>>  $exportServers
     * @return list<array{export_name: string, export_host: string, local_id: ?int, local_name: ?string, status: string}>
     */
    public function preview(int $tenantId, array $exportServers): array
    {
        $local = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy(fn (MikrotikServer $s): string => strtolower($s->host));

        $rows = [];
        foreach ($exportServers as $row) {
            $name = ISPTrackJsonLoader::str($row, 'name');
            $host = ISPTrackJsonLoader::str($row, 'ip', 'host');
            $match = $host !== '' ? $local->get(strtolower($host)) : null;
            if ($match === null && $name !== '') {
                $match = MikrotikServer::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->first();
            }

            $rows[] = [
                'export_name' => $name ?: $host,
                'export_host' => $host,
                'local_id' => $match?->id,
                'local_name' => $match?->name,
                'status' => $match !== null ? 'matched' : 'missing',
            ];
        }

        return $rows;
    }
}
