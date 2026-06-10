<?php

namespace App\Services\Import\ISPTrack;

use App\Models\Area;
use App\Models\Tenant;

final class ISPTrackImportContext
{
    public const IMPORT_SOURCE = 'isptrack';

    /** @var array<int, int> */
    public array $packageMap = [];

    /** @var array<int, int> */
    public array $zoneMap = [];

    /** @var array<int, int> */
    public array $subzoneMap = [];

    /** @var array<int, int> */
    public array $boxMap = [];

    /** @var array<int, int> */
    public array $mikrotikMap = [];

    /** @var array<int, int> */
    public array $customerMap = [];

    /** @var array<int, int> */
    public array $billingMap = [];

    public ?int $defaultAreaId = null;

    /** @var array<string, int> */
    private array $stats = [];

    public function __construct(
        public readonly int $tenantId,
        public readonly bool $dryRun = false,
        public readonly bool $force = false,
    ) {}

    public function tenant(): Tenant
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            throw new \InvalidArgumentException("Tenant {$this->tenantId} not found.");
        }

        return $tenant;
    }

    public function ensureDefaultArea(): Area
    {
        if ($this->defaultAreaId !== null) {
            $existing = Area::query()->withoutGlobalScopes()->find($this->defaultAreaId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $areaName = (string) config('isptrack.default_area_name', 'Main coverage');
        $areaCode = (string) config('isptrack.default_area_code', 'MAIN');

        $area = Area::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereRaw('LOWER(name) = ?', [strtolower($areaName)])
            ->first();

        if ($area === null && ! $this->dryRun) {
            $area = Area::query()->create([
                'tenant_id' => $this->tenantId,
                'name' => $areaName,
                'code' => $areaCode,
                'is_active' => true,
            ]);
            $this->bump('default_area_created');
        }

        if ($area !== null) {
            $this->defaultAreaId = (int) $area->id;
        }

        return $area ?? new Area([
            'tenant_id' => $this->tenantId,
            'name' => $areaName,
            'code' => $areaCode,
            'is_active' => true,
        ]);
    }

    public function bump(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        return $this->stats;
    }

    public function mapId(array &$map, int|string|null $oldId, int $newId): void
    {
        if ($oldId === null || $oldId === '') {
            return;
        }

        $map[(int) $oldId] = $newId;
    }

    public function resolveMapped(array $map, int|string|null $oldId): ?int
    {
        if ($oldId === null || $oldId === '') {
            return null;
        }

        return $map[(int) $oldId] ?? null;
    }
}
