<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use Illuminate\Support\Str;

final class OnuOpticalWebhookIngestService
{
    public function __construct(
        private readonly OnuSignalCollectionService $collector,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{processed: int, skipped: int, created: int, skipped_details: list<array<string, mixed>>}
     */
    public function ingest(array $payload): array
    {
        $readings = $this->normalizeReadings($payload);
        $autoCreate = (bool) ($payload['create_missing'] ?? config('optical.webhook_auto_create_onu', true));
        $defaultOltId = isset($payload['olt_id']) ? (int) $payload['olt_id'] : null;
        $defaultTenantId = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;

        $processed = 0;
        $created = 0;
        $skipped = 0;
        $skippedDetails = [];

        foreach ($readings as $index => $row) {
            if (! is_array($row)) {
                $skipped++;
                $skippedDetails[] = ['index' => $index, 'reason' => 'invalid_row'];

                continue;
            }

            $onu = $this->resolveOnu($row, $defaultTenantId);
            if ($onu === null && $autoCreate) {
                $onu = $this->createOnuFromRow($row, $defaultOltId, $defaultTenantId);
                if ($onu !== null) {
                    $created++;
                }
            }

            if ($onu === null) {
                $skipped++;
                $skippedDetails[] = [
                    'index' => $index,
                    'reason' => 'onu_not_found',
                    'hint' => 'Add ONU in panel or send create_missing:true with olt_id',
                    'keys' => $this->rowIdentifiers($row),
                ];

                continue;
            }

            $rx = $this->pickFloat($row, ['rx_dbm', 'rx_power_dbm', 'rx_power', 'optical_rx', 'onu_rx_dbm', 'receive_power']);
            $tx = $this->pickFloat($row, ['tx_dbm', 'tx_power_dbm', 'tx_power', 'optical_tx', 'onu_tx_dbm', 'transmit_power']);
            $status = $this->pickString($row, ['onu_oper_status', 'status', 'oper_status', 'state']);

            $this->collector->ingestOnuReading(
                $onu,
                $rx,
                $tx,
                $status,
                ['source' => 'webhook', 'ingested_at' => now()->toIso8601String(), 'raw' => $row],
            );
            $processed++;
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'created' => $created,
            'skipped_details' => array_slice($skippedDetails, 0, 20),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeReadings(array $payload): array
    {
        foreach (['readings', 'onus', 'data', 'devices', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        if (isset($payload['serial']) || isset($payload['rx_dbm']) || isset($payload['onu_id'])) {
            return [$payload];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveOnu(array $row, ?int $tenantId): ?Device
    {
        $q = Device::query()->withoutGlobalScopes()->where('type', 'onu');

        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        if (! empty($row['onu_id'])) {
            return (clone $q)->find((int) $row['onu_id']);
        }

        $serial = $this->pickString($row, ['serial', 'serial_number', 'onu_serial', 'sn', 'device_sn']);
        if ($serial !== null) {
            $found = (clone $q)->where('serial_number', $serial)->first();
            if ($found) {
                return $found;
            }
        }

        $external = $this->pickString($row, ['onu_external_id', 'loid', 'external_id', 'onu_id_str']);
        if ($external !== null) {
            $found = (clone $q)->where('onu_external_id', $external)->first();
            if ($found) {
                return $found;
            }
        }

        $mac = $this->pickString($row, ['mac', 'mac_address', 'onu_mac']);
        if ($mac !== null) {
            $normalized = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac) ?? '');
            if (strlen($normalized) >= 12) {
                $found = (clone $q)->where('mac_address', 'like', '%'.substr($normalized, -8))->first();
                if ($found) {
                    return $found;
                }
            }
        }

        $customer = $this->resolveCustomer($row, $tenantId);
        if ($customer !== null) {
            $found = (clone $q)->where('customer_id', $customer->id)->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCustomer(array $row, ?int $tenantId): ?Customer
    {
        $code = $this->pickString($row, ['customer_code', 'subscriber_code', 'code']);
        $phone = $this->pickString($row, ['phone', 'mobile']);
        $login = $this->pickString($row, ['ppp_login', 'username', 'ppp_user', 'mikrotik_secret_name']);

        if ($code === null && $phone === null && $login === null) {
            return null;
        }

        $q = Customer::query()->withoutGlobalScopes();
        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->where(function ($query) use ($code, $phone, $login): void {
            if ($code !== null) {
                $query->orWhere('customer_code', $code);
            }
            if ($phone !== null) {
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                $query->orWhere('phone', $phone);
                if ($digits !== '') {
                    $query->orWhere('phone', $digits);
                }
            }
            if ($login !== null) {
                $query->orWhere('mikrotik_secret_name', $login)
                    ->orWhere('radius_username', $login);
            }
        })->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createOnuFromRow(array $row, ?int $defaultOltId, ?int $defaultTenantId): ?Device
    {
        $oltId = isset($row['olt_id']) ? (int) $row['olt_id'] : $defaultOltId;
        $olt = $oltId
            ? Device::query()->withoutGlobalScopes()->olts()->find($oltId)
            : Device::query()->withoutGlobalScopes()->olts()->when($defaultTenantId, fn ($q) => $q->where('tenant_id', $defaultTenantId))->first();

        if ($olt === null) {
            return null;
        }

        $serial = $this->pickString($row, ['serial', 'serial_number', 'onu_serial', 'sn'])
            ?? $this->pickString($row, ['onu_external_id', 'loid'])
            ?? $this->pickString($row, ['ppp_login', 'username'])
            ?? ('ONU-'.Str::upper(Str::random(8)));

        $customer = $this->resolveCustomer($row, (int) $olt->tenant_id);

        return Device::query()->create([
            'tenant_id' => $olt->tenant_id,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'customer_id' => $customer?->id,
            'serial_number' => $serial,
            'onu_external_id' => $this->pickString($row, ['onu_external_id', 'loid', 'external_id']),
            'display_name' => $this->pickString($row, ['display_name', 'name', 'label']),
            'mac_address' => $this->pickString($row, ['mac', 'mac_address']),
            'card_no' => isset($row['card_no']) ? (int) $row['card_no'] : null,
            'pon_no' => isset($row['pon_no']) ? (int) $row['pon_no'] : null,
            'onu_index' => isset($row['onu_index']) ? (int) $row['onu_index'] : null,
            'connection_type' => 'optical_fiber',
            'status' => 'assigned',
            'onu_oper_status' => strtolower($this->pickString($row, ['onu_oper_status', 'status']) ?? 'unknown'),
            'rx_power_dbm' => $this->pickFloat($row, ['rx_dbm', 'rx_power_dbm', 'rx_power']),
            'tx_power_dbm' => $this->pickFloat($row, ['tx_dbm', 'tx_power_dbm', 'tx_power']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function pickFloat(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && is_numeric($row[$key])) {
                return (float) $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function pickString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowIdentifiers(array $row): array
    {
        return array_filter([
            'serial' => $row['serial'] ?? $row['serial_number'] ?? null,
            'onu_id' => $row['onu_id'] ?? null,
            'customer_code' => $row['customer_code'] ?? null,
            'ppp_login' => $row['ppp_login'] ?? $row['username'] ?? null,
        ]);
    }
}
