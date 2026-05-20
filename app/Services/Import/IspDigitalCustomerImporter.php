<?php

namespace App\Services\Import;

use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Subzone;
use App\Models\Zone;
use App\Support\BillingDefaults;
use App\Support\CustomerStatus;
use App\Support\IspDigitalPackageSpeed;
use App\Support\SubscriberType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
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
        $packageId = $this->resolvePackageIdForRow($row);
        $zoneIds = $this->resolveZoneIds($row);
        $mikrotikId = $this->resolveMikrotikServerId((string) ($row['Server'] ?? ''));

        $online = $this->isPppOnline($row);
        $portalPassword = $this->resolvePortalPassword($row);

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
            'billing_mode' => $this->resolveBillingMode($row),
            'billing_day' => $this->resolveBillingDay($row),
            'joined_at' => $this->parseDate($row['ClientJoiningDate'] ?? null)?->toDateString() ?? now()->toDateString(),
            'service_expires_at' => $this->resolveServiceExpiresAt($row)?->toDateString(),
            'network_access_state' => $this->mapNetworkAccessState($row),
            'is_ppp_online' => $online,
            'ppp_last_seen_at' => $online ? now() : null,
            'mikrotik_secret_name' => $username,
            'radius_username' => $username,
            'mikrotik_server_id' => $mikrotikId,
            'mikrotik_ppp_password' => filled($row['Password'] ?? null) ? (string) $row['Password'] : null,
            'notes' => trim((string) ($row['Remarks'] ?? '')),
            'import_source' => 'isp_digital',
            'meta' => $this->buildMeta($row),
        ];

        if ($portalPassword !== null) {
            $attrs['portal_password'] = $portalPassword;
        }

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
        if ($subName === '') {
            $subName = trim((string) ($row['BoxName'] ?? ''));
        }

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
     * Resolve local package id from ISP Digital row (Package + PackageSpeed).
     *
     * @param  array<string, mixed>  $row
     */
    public function resolvePackageIdForRow(array $row): ?int
    {
        $parsed = IspDigitalPackageSpeed::parse($row);
        $name = $parsed['display_name'];
        $profile = $parsed['mikrotik_profile'];

        if ($name === '') {
            return Package::query()->where('is_active', true)->value('id');
        }

        $package = Package::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($package !== null) {
            if ($profile !== null && $package->mikrotik_profile_name !== $profile) {
                $package->update(['mikrotik_profile_name' => $profile]);
            }

            return $package->id;
        }

        if ($profile !== null) {
            $byProfile = Package::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(mikrotik_profile_name) = ?', [strtolower($profile)])
                ->first();

            if ($byProfile !== null) {
                return $byProfile->id;
            }
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
            'mikrotik_profile_name' => $profile,
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
            'package_speed' => (string) ($row['PackageSpeed'] ?? ''),
            'mikrotik_profile' => IspDigitalPackageSpeed::parse($row)['mikrotik_profile'],
            'monthly_bill_snapshot' => $row['MonthlyBill'] ?? null,
            'is_online_snapshot' => $row['IsOnline'] ?? null,
            'is_connected_to_mikrotik' => (bool) ($row['IsConnectedToMikrotik'] ?? false),
            'connectivity_status' => (string) ($row['ConnectivityStatus'] ?? ''),
            'billing_last_date' => (string) ($row['BillingLastDate'] ?? ''),
            'effective_to_remarks' => (string) ($row['EffectiveToRemarks'] ?? ''),
            'portal_login_username' => (string) ($row['LoginUserName'] ?? ''),
            'has_portal_access' => (bool) ($row['HasLoginAccess'] ?? false),
            'device' => (string) ($row['Device'] ?? ''),
            'purchase_date' => (string) ($row['PurchaseDate'] ?? ''),
            'assigned_employee' => (string) ($row['AssignedEmployee'] ?? ''),
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
        if ($this->isDisabled($row)) {
            return CustomerStatus::SUSPENDED;
        }

        $short = strtolower((string) ($row['ShortStatus'] ?? 'active'));
        $status = strtolower((string) ($row['Status'] ?? ''));

        if (str_contains($status, 'suspend') || $short === 'suspended' || $short === 'off') {
            return CustomerStatus::SUSPENDED;
        }
        if (str_contains($status, 'expir') || $short === 'expired') {
            return CustomerStatus::EXPIRED;
        }
        if (str_contains($status, 'terminat') || str_contains($status, 'left')) {
            return CustomerStatus::TERMINATED;
        }

        return CustomerStatus::ACTIVE;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapNetworkAccessState(array $row): string
    {
        if ($this->isDisabled($row)) {
            return 'suspended';
        }

        $short = strtolower((string) ($row['ShortStatus'] ?? 'active'));
        if (in_array($short, ['suspended', 'off', 'disabled', 'expired'], true)) {
            return 'suspended';
        }

        return 'active';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isPppOnline(array $row): bool
    {
        if (filter_var($row['IsOnline'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $connectivity = strtolower((string) ($row['ConnectivityStatus'] ?? ''));

        return str_contains($connectivity, 'online');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isDisabled(array $row): bool
    {
        return filter_var($row['Disabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveBillingMode(array $row): string
    {
        $expires = $this->resolveServiceExpiresAt($row);
        if ($expires !== null && $expires->isFuture() && $expires->year >= 2000 && $expires->year < 2038) {
            return 'prepaid';
        }

        return 'postpaid';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveBillingDay(array $row): int
    {
        $day = (int) preg_replace('/\D+/', '', (string) ($row['BillingLastDate'] ?? ''));

        return ($day >= 1 && $day <= 28) ? $day : BillingDefaults::billingDay();
    }

    /**
     * ISP Digital: EffectiveTo when set; otherwise expire day from BillingLastDate (day of month).
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveServiceExpiresAt(array $row): ?Carbon
    {
        $effective = $this->parseDate($row['EffectiveTo'] ?? null);
        if ($effective !== null) {
            return $effective;
        }

        $expireDay = (int) preg_replace('/\D+/', '', (string) ($row['BillingLastDate'] ?? ''));
        if ($expireDay >= 1 && $expireDay <= 31) {
            return Carbon::parse(BillingDefaults::dateFromExpireDay($expireDay));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolvePortalPassword(array $row): ?string
    {
        if (! filter_var($row['HasLoginAccess'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $plain = trim((string) ($row['LoginPassword'] ?? ''));
        if ($plain === '') {
            return null;
        }

        return Hash::make($plain);
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
