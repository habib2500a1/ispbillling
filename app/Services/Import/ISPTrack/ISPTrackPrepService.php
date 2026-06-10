<?php

namespace App\Services\Import\ISPTrack;

use App\Models\District;
use App\Models\MikrotikServer;
use Database\Seeders\BangladeshGeoSeeder;

final class ISPTrackPrepService
{
    public function __construct(
        private readonly ISPTrackJsonLoader $loader,
        private readonly ISPTrackMikrotikMatcher $mikrotikMatcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(ISPTrackImportContext $ctx, string $path): array
    {
        $data = $this->loader->load($path);
        $tenant = $ctx->tenant();
        $area = $ctx->ensureDefaultArea();

        if (District::query()->count() === 0 && ! $ctx->dryRun) {
            (new BangladeshGeoSeeder)->run();
            $ctx->bump('geo_seeded');
        }

        $mikrotikPreview = $this->mikrotikMatcher->preview($ctx->tenantId, $data['mikrotik_servers']);
        $matched = collect($mikrotikPreview)->where('status', 'matched')->count();
        $missing = collect($mikrotikPreview)->where('status', 'missing')->count();

        $localMikrotik = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('is_enabled', true)
            ->count();

        return [
            'tenant_id' => $ctx->tenantId,
            'tenant_name' => $tenant->name,
            'default_area' => $area->name ?? config('isptrack.default_area_name', 'Main coverage'),
            'districts_in_db' => District::query()->count(),
            'local_mikrotik_enabled' => $localMikrotik,
            'export_mikrotik_servers' => count($data['mikrotik_servers']),
            'mikrotik_matched' => $matched,
            'mikrotik_missing' => $missing,
            'packages' => count($data['packages']),
            'zones' => count($data['zones']),
            'sub_zones' => count($data['sub_zones']),
            'boxes' => count($data['boxes']),
            'clients' => count($data['clients']),
            'billings' => count($data['billings']),
            'invoices' => count($data['invoices']),
            'payments' => count($data['payments']),
            'dry_run' => $ctx->dryRun ? 'yes' : 'no',
            'mikrotik_preview' => $mikrotikPreview,
        ];
    }
}
