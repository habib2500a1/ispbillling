<?php

namespace App\Services\Import\ISPTrack;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Subzone;
use App\Models\Zone;
use App\Services\Geo\BangladeshGeoResolver;
use App\Support\CustomerStatus;
use Carbon\Carbon;

final class ISPTrackCustomerImporter
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

        foreach ($data['clients'] as $row) {
            $this->importClient($ctx, $row);
        }

        return $ctx->stats();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importClient(ISPTrackImportContext $ctx, array $row): void
    {
        $code = ISPTrackJsonLoader::str($row, 'client_id', 'customer_code', 'code');
        $phone = $this->normalizePhone(ISPTrackJsonLoader::str($row, 'phone', 'mobile'));
        $name = ISPTrackJsonLoader::str($row, 'name', 'customer_name');

        if ($code === '' && $phone === '') {
            $ctx->bump('customers_skipped');

            return;
        }

        if ($code === '') {
            $code = 'IT-'.$phone;
        }

        if ($name === '') {
            $name = $code;
        }

        $oldId = ISPTrackJsonLoader::int($row, 'id');
        $existing = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('customer_code', $code)
            ->first();

        if ($existing !== null && ! $ctx->force) {
            $ctx->mapId($ctx->customerMap, $oldId, (int) $existing->id);
            $ctx->bump('customers_skipped');

            return;
        }

        if ($ctx->dryRun) {
            $ctx->bump('customers_would_import');

            return;
        }

        $zoneIds = $this->resolveZoneIds($ctx, $row);
        $packageId = $this->resolvePackageId($ctx, $row);
        $status = $this->mapStatus(ISPTrackJsonLoader::str($row, 'status') ?: 'active');
        $networkState = CustomerStatus::isRestricted($status) ? 'suspended' : 'active';

        $connectionId = ISPTrackJsonLoader::str($row, 'connection_id', 'username', 'ppp_username');
        $pppLogin = $connectionId !== '' ? $connectionId : $phone;

        $geo = app(BangladeshGeoResolver::class)->resolve(
            ISPTrackJsonLoader::str($row, 'district') ?: null,
            ISPTrackJsonLoader::str($row, 'upazila', 'thana') ?: null,
        );

        $attrs = [
            'tenant_id' => $ctx->tenantId,
            'customer_code' => $code,
            'name' => $name,
            'phone' => $phone !== '' ? $phone : '00000000000',
            'email' => filled($row['email'] ?? null) ? trim((string) $row['email']) : null,
            'nid_number' => ISPTrackJsonLoader::str($row, 'nid_number', 'nid') ?: null,
            'address' => ISPTrackJsonLoader::str($row, 'address', 'installation_address') ?: '—',
            'district_id' => $geo['district_id'],
            'upazila_id' => $geo['upazila_id'],
            'area_id' => $zoneIds['area_id'],
            'zone_id' => $zoneIds['zone_id'],
            'subzone_id' => $zoneIds['subzone_id'],
            'package_id' => $packageId,
            'status' => $status,
            'network_access_state' => $networkState,
            'billing_day' => ISPTrackJsonLoader::int($row, 'billing_cycle', 'billing_day') ?? 1,
            'joined_at' => $this->parseDate($row['connection_date'] ?? $row['joined_at'] ?? null)?->toDateString() ?? now()->toDateString(),
            'service_expires_at' => $this->parseDate($row['due_date'] ?? $row['service_expires_at'] ?? null)?->toDateString(),
            'mikrotik_secret_name' => $pppLogin,
            'radius_username' => $pppLogin,
            'mikrotik_server_id' => $this->resolveMikrotikId($ctx, $row),
            'notes' => ISPTrackJsonLoader::str($row, 'notes'),
            'import_source' => ISPTrackImportContext::IMPORT_SOURCE,
            'meta' => $this->buildMeta($row, $geo),
        ];

        $customer = Customer::withoutEvents(function () use ($existing, $attrs, $ctx): Customer {
            if ($existing !== null && $ctx->force) {
                $mergedMeta = array_merge(is_array($existing->meta) ? $existing->meta : [], $attrs['meta']);
                $attrs['meta'] = $mergedMeta;

                return $existing->updateTrusted($attrs);
            }

            return Customer::createTrusted($attrs);
        });

        $ctx->mapId($ctx->customerMap, $oldId, (int) $customer->id);
        $ctx->bump($existing !== null && $ctx->force ? 'customers_updated' : 'customers_created');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{area_id: ?int, zone_id: ?int, subzone_id: ?int}
     */
    private function resolveZoneIds(ISPTrackImportContext $ctx, array $row): array
    {
        $boxOldId = ISPTrackJsonLoader::int($row, 'box_id');
        $subzoneId = $ctx->resolveMapped($ctx->boxMap, $boxOldId)
            ?? $ctx->resolveMapped($ctx->subzoneMap, ISPTrackJsonLoader::int($row, 'sub_zone_id', 'subzone_id'));

        $zoneId = $ctx->resolveMapped($ctx->zoneMap, ISPTrackJsonLoader::int($row, 'zone_id'));
        $zoneName = ISPTrackJsonLoader::str($row, 'zone_name', 'zone');
        $subName = ISPTrackJsonLoader::str($row, 'sub_zone_name', 'subzone_name', 'box_name');

        if ($zoneId === null && $zoneName !== '') {
            $zone = Zone::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ctx->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($zoneName)])
                ->first();
            $zoneId = $zone?->id;
        }

        if ($subzoneId === null && $subName !== '') {
            $query = Subzone::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ctx->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($subName)]);
            if ($zoneId) {
                $query->where('zone_id', $zoneId);
            }
            $subzoneId = $query->value('id');
        }

        $areaId = $zoneId
            ? Zone::query()->withoutGlobalScopes()->whereKey($zoneId)->value('area_id')
            : $ctx->ensureDefaultArea()->id;

        return [
            'area_id' => $areaId ? (int) $areaId : null,
            'zone_id' => $zoneId ? (int) $zoneId : null,
            'subzone_id' => $subzoneId ? (int) $subzoneId : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolvePackageId(ISPTrackImportContext $ctx, array $row): ?int
    {
        $mapped = $ctx->resolveMapped($ctx->packageMap, ISPTrackJsonLoader::int($row, 'package_id'));
        if ($mapped !== null) {
            return $mapped;
        }

        $name = ISPTrackJsonLoader::str($row, 'package_name', 'package');
        if ($name !== '') {
            $id = Package::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ctx->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        return Package::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('is_active', true)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveMikrotikId(ISPTrackImportContext $ctx, array $row): ?int
    {
        $mapped = $ctx->resolveMapped($ctx->mikrotikMap, ISPTrackJsonLoader::int($row, 'mikrotik_server_id'));
        if ($mapped !== null) {
            return $mapped;
        }

        $id = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $ctx->tenantId)
            ->where('is_enabled', true)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{district_id: ?int, upazila_id: ?int, district: ?string, thana: ?string}  $geo
     * @return array<string, mixed>
     */
    private function buildMeta(array $row, array $geo): array
    {
        return array_filter([
            'isptrack_id' => ISPTrackJsonLoader::int($row, 'id'),
            'connection_id' => ISPTrackJsonLoader::str($row, 'connection_id') ?: null,
            'installation_address' => ISPTrackJsonLoader::str($row, 'installation_address') ?: null,
            'trade_license' => ISPTrackJsonLoader::str($row, 'trade_license') ?: null,
            'ip_address' => ISPTrackJsonLoader::str($row, 'ip_address') ?: null,
            'mac_binding' => ISPTrackJsonLoader::str($row, 'mac_address', 'mac_binding') ?: null,
            'district' => $geo['district'],
            'thana' => $geo['thana'],
            'box_name' => ISPTrackJsonLoader::str($row, 'box_name') ?: null,
            'monthly_bill_snapshot' => ISPTrackJsonLoader::num($row, 'monthly_bill') ?: null,
            'connection_type' => ISPTrackJsonLoader::str($row, 'connection_type') ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'suspended' => CustomerStatus::SUSPENDED,
            'terminated' => CustomerStatus::TERMINATED,
            'inactive', 'left', 'expired' => CustomerStatus::EXPIRED,
            default => CustomerStatus::ACTIVE,
        };
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 10 ? $digits : $phone;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
