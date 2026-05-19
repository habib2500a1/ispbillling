<?php

namespace App\Services\Import;

use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Subzone;
use App\Models\Zone;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use Carbon\Carbon;
final class IspDigitalCustomerImporter
{
    public function __construct(
        private readonly int $tenantId = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public function importRow(array $row, bool $force = false): Customer
    {
        $code = trim((string) ($row['CustomerId'] ?? ''));
        if ($code === '') {
            throw new \InvalidArgumentException('Row missing CustomerId');
        }

        $existing = Customer::query()->where('customer_code', $code)->first();
        if ($existing !== null && ! $force) {
            return $existing;
        }

        $phone = $this->normalizePhone((string) ($row['MobileNumber'] ?? ''));
        $username = trim((string) ($row['UserName'] ?? $phone));
        $packageId = $this->resolvePackageId($row);
        $zoneIds = $this->resolveZoneIds($row);
        $mikrotikId = $this->resolveMikrotikServerId((string) ($row['Server'] ?? ''));

        $attrs = [
            'tenant_id' => $this->tenantId,
            'customer_code' => $code,
            'name' => trim((string) ($row['CustomerName'] ?? $username)),
            'phone' => $phone,
            'email' => filled($row['EmailAddress'] ?? null) ? trim((string) $row['EmailAddress']) : null,
            'nid_number' => filled($row['NationalId'] ?? null) ? trim((string) $row['NationalId']) : null,
            'address' => $this->buildAddress($row),
            'area_id' => $zoneIds['area_id'],
            'zone_id' => $zoneIds['zone_id'],
            'subzone_id' => $zoneIds['subzone_id'],
            'package_id' => $packageId,
            'status' => $this->mapStatus($row),
            'subscriber_type' => ($row['IsVIPClient'] ?? false) ? SubscriberType::VIP : SubscriberType::STANDARD,
            'billing_mode' => 'postpaid',
            'billing_day' => is_numeric($row['BillingLastDate'] ?? null) ? (int) $row['BillingLastDate'] : 1,
            'joined_at' => $this->parseDate($row['ClientJoiningDate'] ?? null)?->toDateString() ?? now()->toDateString(),
            'service_expires_at' => $this->parseDate($row['EffectiveTo'] ?? null)?->toDateString(),
            'network_access_state' => strtolower((string) ($row['ShortStatus'] ?? 'active')) === 'active' ? 'active' : 'suspended',
            'mikrotik_secret_name' => $username,
            'radius_username' => $username,
            'mikrotik_server_id' => $mikrotikId,
            'mikrotik_ppp_password' => filled($row['Password'] ?? null) ? (string) $row['Password'] : null,
            'notes' => trim((string) ($row['Remarks'] ?? '')),
            'import_source' => 'isp_digital',
            'meta' => $this->buildMeta($row),
        ];

        $customer = Customer::withoutEvents(function () use ($existing, $attrs): Customer {
            if ($existing !== null) {
                return $existing->updateTrusted($attrs);
            }

            return Customer::createTrusted($attrs);
        });

        $this->syncContacts($customer, $row, $phone);

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{area_id: ?int, zone_id: ?int, subzone_id: ?int}
     */
    private function resolveZoneIds(array $row): array
    {
        $zoneName = trim((string) ($row['ZoneName'] ?? ''));
        $subName = trim((string) ($row['SubZoneName'] ?? ''));

        $zone = $zoneName !== ''
            ? Zone::query()->whereRaw('LOWER(name) = ?', [strtolower($zoneName)])->first()
            : null;

        $subzone = $subName !== ''
            ? Subzone::query()->whereRaw('LOWER(name) = ?', [strtolower($subName)])->first()
            : null;

        if ($zone === null && $zoneName !== '') {
            $area = Area::query()->first() ?? Area::query()->create(['tenant_id' => $this->tenantId, 'name' => 'Default']);
            $zone = Zone::query()->create([
                'tenant_id' => $this->tenantId,
                'area_id' => $area->id,
                'name' => $zoneName,
                'is_active' => true,
            ]);
        }

        if ($subzone === null && $subName !== '' && $zone !== null) {
            $subzone = Subzone::query()->create([
                'tenant_id' => $this->tenantId,
                'zone_id' => $zone->id,
                'name' => $subName,
                'is_active' => true,
            ]);
        }

        return [
            'area_id' => $zone?->area_id ?? Area::query()->value('id'),
            'zone_id' => $zone?->id,
            'subzone_id' => $subzone?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolvePackageId(array $row): ?int
    {
        $name = trim((string) ($row['Package'] ?? $row['PackageSpeed'] ?? ''));
        if ($name === '') {
            return Package::query()->where('is_active', true)->value('id');
        }

        $pkg = Package::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->value('id');

        if ($pkg !== null) {
            return (int) $pkg;
        }

        $monthly = (float) ($row['MonthlyBill'] ?? 0);
        $speed = (string) ($row['Speed'] ?? '');
        $mbps = 10;
        if (preg_match('/(\d+)\s*mbps/i', $name.$speed, $m)) {
            $mbps = (int) $m[1];
        }

        return Package::query()->create([
            'tenant_id' => $this->tenantId,
            'name' => $name,
            'type' => 'residential',
            'download_mbps' => $mbps,
            'upload_mbps' => $mbps,
            'price_monthly' => $monthly > 0 ? $monthly : 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ])->id;
    }

    private function resolveMikrotikServerId(string $serverName): ?int
    {
        if ($serverName === '') {
            return MikrotikServer::query()->value('id');
        }

        $id = MikrotikServer::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($serverName)])
            ->value('id');

        return $id !== null ? (int) $id : MikrotikServer::query()->value('id');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildMeta(array $row): array
    {
        return [
            'legacy_id' => (string) ($row['CustomerHeaderId'] ?? ''),
            'legacy_client_id' => (string) ($row['CustomerId'] ?? ''),
            'static_ip' => filled($row['CustomerRealIP'] ?? null) ? (string) $row['CustomerRealIP'] : null,
            'mac_binding' => filled($row['MACAddress'] ?? null) ? (string) $row['MACAddress'] : null,
            'mikrotik_comment' => (string) ($row['Remarks'] ?? ''),
            'connection_type' => (string) ($row['ConnectionType'] ?? ''),
            'customer_type' => (string) ($row['CustomerType'] ?? ''),
            'protocol' => (string) ($row['Protocol'] ?? ''),
            'server_name' => (string) ($row['Server'] ?? ''),
            'box_name' => (string) ($row['BoxName'] ?? ''),
            'road_no' => (string) ($row['RoadNo'] ?? ''),
            'house_no' => (string) ($row['HouseNo'] ?? ''),
            'thana' => (string) ($row['Thana'] ?? ''),
            'district' => (string) ($row['District'] ?? ''),
            'monthly_bill_snapshot' => $row['MonthlyBill'] ?? null,
            'is_online_snapshot' => $row['IsOnline'] ?? null,
            'connectivity_status' => (string) ($row['ConnectivityStatus'] ?? ''),
            'mac_bind_status' => (bool) ($row['MACBindStatus'] ?? false),
            'remote_address' => (string) ($row['RemoteAddress'] ?? ''),
            'notify_sms' => true,
            'auto_invoice' => true,
            'auto_pppoe' => true,
            'auto_onu' => false,
            'auto_activate' => true,
            'auto_suspend' => true,
            'tag_vip' => (bool) ($row['IsVIPClient'] ?? false),
            'isp_digital_raw' => $row,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildAddress(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['HouseNo'] ?? '')),
            trim((string) ($row['RoadNo'] ?? '')),
            trim((string) ($row['Address'] ?? '')),
            trim((string) ($row['SubZoneName'] ?? '')),
            trim((string) ($row['ZoneName'] ?? '')),
        ]);

        return $parts !== [] ? implode(', ', $parts) : '—';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapStatus(array $row): string
    {
        $short = strtolower((string) ($row['ShortStatus'] ?? 'active'));
        $status = strtolower((string) ($row['Status'] ?? ''));

        if (str_contains($status, 'suspend') || $short === 'suspended') {
            return CustomerStatus::SUSPENDED;
        }
        if (str_contains($status, 'expir') || $short === 'expired') {
            return CustomerStatus::EXPIRED;
        }
        if (str_contains($status, 'terminat')) {
            return CustomerStatus::TERMINATED;
        }

        return CustomerStatus::ACTIVE;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim((string) $value);
        foreach (['d-M-Y', 'M-d-y', 'd-M-y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : '01700000000';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncContacts(Customer $customer, array $row, string $phone): void
    {
        CustomerContact::query()->where('customer_id', $customer->id)->delete();

        CustomerContact::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'label' => 'mobile',
            'phone' => $phone,
            'is_primary' => true,
            'is_whatsapp' => true,
        ]);
    }
}
