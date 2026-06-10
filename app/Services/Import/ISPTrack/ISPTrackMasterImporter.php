<?php

namespace App\Services\Import\ISPTrack;

use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Subzone;
use App\Models\Zone;

final class ISPTrackMasterImporter
{
    public function __construct(
        private readonly ISPTrackJsonLoader $loader,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(ISPTrackImportContext $ctx, string $path): array
    {
        $data = $this->loader->load($path);
        $area = $ctx->ensureDefaultArea();
        $areaId = (int) ($area->id ?? 0);

        foreach ($data['packages'] as $row) {
            $this->importPackage($ctx, $row);
        }

        foreach ($data['zones'] as $row) {
            $this->importZone($ctx, $row, $areaId);
        }

        foreach ($data['sub_zones'] as $row) {
            $this->importSubzone($ctx, $row);
        }

        foreach ($data['boxes'] as $row) {
            $this->importBox($ctx, $row);
        }

        foreach ($data['mikrotik_servers'] as $row) {
            $this->importMikrotikServer($ctx, $row);
        }

        return $ctx->stats();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importPackage(ISPTrackImportContext $ctx, array $row): void
    {
        $name = ISPTrackJsonLoader::str($row, 'name');
        if ($name === '') {
            $ctx->bump('packages_skipped');

            return;
        }

        $oldId = ISPTrackJsonLoader::int($row, 'id');
        $existing = Package::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($existing !== null && ! $ctx->force) {
            $ctx->mapId($ctx->packageMap, $oldId, (int) $existing->id);
            $ctx->bump('packages_skipped');

            return;
        }

        if ($ctx->dryRun) {
            $ctx->bump('packages_would_import');

            return;
        }

        $speed = ISPTrackJsonLoader::str($row, 'speed', 'bandwidth');
        $mbps = 10;
        if (preg_match('/(\d+)/', $speed, $m)) {
            $mbps = max(1, (int) $m[1]);
        }

        $attrs = [
            'tenant_id' => $ctx->tenantId,
            'name' => $name,
            'type' => 'residential',
            'download_mbps' => $mbps,
            'upload_mbps' => $mbps,
            'price_monthly' => ISPTrackJsonLoader::num($row, 'price', 'monthly_bill') ?: 500,
            'setup_fee' => ISPTrackJsonLoader::num($row, 'setup_fee'),
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($existing !== null && $ctx->force) {
            $existing->update($attrs);
            $package = $existing;
            $ctx->bump('packages_updated');
        } else {
            $package = Package::query()->create($attrs);
            $ctx->bump('packages_created');
        }

        $ctx->mapId($ctx->packageMap, $oldId, (int) $package->id);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importZone(ISPTrackImportContext $ctx, array $row, int $areaId): void
    {
        $name = ISPTrackJsonLoader::str($row, 'name');
        if ($name === '') {
            $ctx->bump('zones_skipped');

            return;
        }

        $oldId = ISPTrackJsonLoader::int($row, 'id');
        $existing = Zone::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($existing !== null && ! $ctx->force) {
            $ctx->mapId($ctx->zoneMap, $oldId, (int) $existing->id);
            $ctx->bump('zones_skipped');

            return;
        }

        if ($ctx->dryRun) {
            $ctx->bump('zones_would_import');

            return;
        }

        $attrs = [
            'tenant_id' => $ctx->tenantId,
            'area_id' => $areaId ?: $ctx->ensureDefaultArea()->id,
            'name' => $name,
            'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($existing !== null && $ctx->force) {
            $existing->update($attrs);
            $zone = $existing;
            $ctx->bump('zones_updated');
        } else {
            $zone = Zone::query()->create($attrs);
            $ctx->bump('zones_created');
        }

        $ctx->mapId($ctx->zoneMap, $oldId, (int) $zone->id);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importSubzone(ISPTrackImportContext $ctx, array $row): void
    {
        $name = ISPTrackJsonLoader::str($row, 'name');
        if ($name === '') {
            $ctx->bump('subzones_skipped');

            return;
        }

        $oldId = ISPTrackJsonLoader::int($row, 'id');
        $zoneOldId = ISPTrackJsonLoader::int($row, 'zone_id');
        $zoneId = $ctx->resolveMapped($ctx->zoneMap, $zoneOldId)
            ?? Zone::query()->withoutGlobalScopes()->where('tenant_id', $ctx->tenantId)->value('id');

        $existing = Subzone::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
            ->first();

        if ($existing !== null && ! $ctx->force) {
            $ctx->mapId($ctx->subzoneMap, $oldId, (int) $existing->id);
            $ctx->bump('subzones_skipped');

            return;
        }

        if ($ctx->dryRun) {
            $ctx->bump('subzones_would_import');

            return;
        }

        $attrs = [
            'tenant_id' => $ctx->tenantId,
            'zone_id' => $zoneId,
            'name' => $name,
            'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($existing !== null && $ctx->force) {
            $existing->update($attrs);
            $subzone = $existing;
            $ctx->bump('subzones_updated');
        } else {
            $subzone = Subzone::query()->create($attrs);
            $ctx->bump('subzones_created');
        }

        $ctx->mapId($ctx->subzoneMap, $oldId, (int) $subzone->id);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importBox(ISPTrackImportContext $ctx, array $row): void
    {
        $name = ISPTrackJsonLoader::str($row, 'name');
        if ($name === '') {
            $ctx->bump('boxes_skipped');

            return;
        }

        $oldId = ISPTrackJsonLoader::int($row, 'id');
        $subOldId = ISPTrackJsonLoader::int($row, 'sub_zone_id', 'subzone_id');
        $zoneOldId = ISPTrackJsonLoader::int($row, 'zone_id');

        $zoneId = $ctx->resolveMapped($ctx->zoneMap, $zoneOldId)
            ?? Zone::query()->withoutGlobalScopes()->where('tenant_id', $ctx->tenantId)->value('id');

        $subzoneId = $ctx->resolveMapped($ctx->subzoneMap, $subOldId);
        if ($subzoneId === null && $zoneId) {
            $subzone = Subzone::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ctx->tenantId)
                ->where('zone_id', $zoneId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();

            if ($subzone === null && ! $ctx->dryRun) {
                $subzone = Subzone::query()->create([
                    'tenant_id' => $ctx->tenantId,
                    'zone_id' => $zoneId,
                    'name' => $name,
                    'is_active' => true,
                ]);
                $ctx->bump('subzones_from_boxes');
            }

            $subzoneId = $subzone?->id;
        }

        if ($subzoneId !== null) {
            $ctx->mapId($ctx->boxMap, $oldId, (int) $subzoneId);
            $ctx->bump('boxes_mapped');
        } else {
            $ctx->bump('boxes_skipped');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importMikrotikServer(ISPTrackImportContext $ctx, array $row): void
    {
        $name = ISPTrackJsonLoader::str($row, 'name');
        $host = ISPTrackJsonLoader::str($row, 'ip', 'host');
        if ($name === '' && $host === '') {
            $ctx->bump('mikrotik_skipped');

            return;
        }

        if ($name === '') {
            $name = $host;
        }

        $oldId = ISPTrackJsonLoader::int($row, 'id');
        $existing = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where(function ($q) use ($name, $host): void {
                $q->whereRaw('LOWER(name) = ?', [strtolower($name)]);
                if ($host !== '') {
                    $q->orWhere('host', $host);
                }
            })
            ->first();

        if ($existing !== null && ! $ctx->force) {
            $ctx->mapId($ctx->mikrotikMap, $oldId, (int) $existing->id);
            $ctx->bump('mikrotik_skipped');

            return;
        }

        if ($ctx->dryRun) {
            $ctx->bump('mikrotik_would_import');

            return;
        }

        $attrs = [
            'tenant_id' => $ctx->tenantId,
            'name' => $name,
            'host' => $host !== '' ? $host : '127.0.0.1',
            'api_port' => ISPTrackJsonLoader::int($row, 'api_port') ?? 8728,
            'api_username' => ISPTrackJsonLoader::str($row, 'username', 'api_username') ?: 'admin',
            'api_password' => ISPTrackJsonLoader::str($row, 'password', 'api_password') ?: 'changeme',
            'is_enabled' => filter_var($row['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($existing !== null && $ctx->force) {
            $existing->update($attrs);
            $server = $existing;
            $ctx->bump('mikrotik_updated');
        } else {
            $server = MikrotikServer::query()->create($attrs);
            $ctx->bump('mikrotik_created');
        }

        $ctx->mapId($ctx->mikrotikMap, $oldId, (int) $server->id);
    }
}
