<?php

namespace App\Services\Import;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * Pulls Network & Product fields from legacy portal customer details HTML into local meta.
 */
final class LegacyPortalCustomerDetailsSyncService
{
    public function __construct(
        private readonly ?LegacyPortalSessionClient $clientFactory = null,
    ) {}

    /**
     * @return array{updated: bool, fields: list<string>, pairs: array<string, string>, error: ?string}
     */
    public function syncCustomer(Customer $customer): array
    {
        $headerId = $this->resolveHeaderId($customer);
        if ($headerId === null) {
            return [
                'updated' => false,
                'fields' => [],
                'pairs' => [],
                'error' => 'Missing legacy portal CustomerHeaderId — run isp:import-legacy-portal first.',
            ];
        }

        $client = $this->client();
        if (! $client->isLoggedIn()) {
            $client->login();
        }
        $html = $client->fetchCustomerDetailsHtml($headerId);
        $pairs = $this->parseLabelValuePairs($html);
        $patch = $this->mapPairsToMeta($pairs, $customer);

        if ($patch === []) {
            return [
                'updated' => false,
                'fields' => [],
                'pairs' => $pairs,
                'error' => null,
            ];
        }

        $meta = array_merge(is_array($customer->meta) ? $customer->meta : [], $patch);
        $meta['legacy_portal_details_synced_at'] = now()->toIso8601String();
        $customer->forceFill(['meta' => $meta])->saveQuietly();

        return [
            'updated' => true,
            'fields' => array_keys($patch),
            'pairs' => $pairs,
            'error' => null,
        ];
    }

    private function client(): LegacyPortalSessionClient
    {
        if ($this->clientFactory !== null) {
            return $this->clientFactory;
        }

        return new LegacyPortalSessionClient(
            (string) config('legacy_portal.base_url'),
            (string) config('legacy_portal.username'),
            (string) config('legacy_portal.password'),
        );
    }

    private function resolveHeaderId(Customer $customer): ?int
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $raw = is_array($meta['legacy_portal_raw'] ?? null) ? $meta['legacy_portal_raw'] : [];
        $id = (int) ($raw['CustomerHeaderId'] ?? $meta['legacy_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, string>
     */
    public function parseLabelValuePairs(string $html): array
    {
        if (! preg_match_all(
            '#<div class="col-sm-5">([^<]+)</div>\s*<div class="col-sm-1">:</div>\s*<div class="col-sm-6[^"]*">([^<]*)</div>#',
            $html,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $pairs = [];
        foreach ($matches as $match) {
            $label = trim(html_entity_decode(strip_tags($match[1])));
            $value = trim(html_entity_decode(strip_tags($match[2])));
            if ($label === '' || strtoupper($value) === 'N/A') {
                continue;
            }
            $pairs[$label] = $value;
        }

        return $pairs;
    }

    /**
     * @param  array<string, string>  $pairs
     * @return array<string, mixed>
     */
    private function mapPairsToMeta(array $pairs, Customer $customer): array
    {
        $patch = [];
        $network = [];

        foreach ($pairs as $label => $value) {
            $key = Str::slug($label, '_');
            $network[$key] = $value;

            match (true) {
                str_contains($key, 'device_mac') || $key === 'device_mac_serial_no' => $this->applyOnuMac($patch, $value, $customer),
                $key === 'device' && $value !== '' => $patch['device'] = $value,
                str_contains($key, 'cable_requirement') => $patch['cable_length_m'] = (float) preg_replace('/[^0-9.]/', '', $value),
                $key === 'fiber_code' => $patch['fiber_code'] = $value,
                $key === 'number_of_core' => $patch['fiber_cores'] = $value,
                $key === 'purchase_date' => $patch['purchase_date'] = $value,
                $key === 'vendor' => $patch['device_vendor'] = $value,
                $key === 'monthly_bill' => $patch['legacy_portal_monthly_bill'] = (float) preg_replace('/[^0-9.]/', '', $value),
                $key === 'installation_fee' => $patch['installation_fee'] = (float) preg_replace('/[^0-9.]/', '', $value),
                $key === 'profile' => $patch['mikrotik_profile'] = $value,
                str_contains($key, 'onu_rent') || (str_contains($key, 'onu') && str_contains($key, 'rent')) => $patch['onu_rent'] = (float) preg_replace('/[^0-9.]/', '', $value),
                str_contains($key, 'router_rent') => $patch['router_rent'] = (float) preg_replace('/[^0-9.]/', '', $value),
                str_contains($key, 'onu_installment') => $patch['onu_installment'] = (float) preg_replace('/[^0-9.]/', '', $value),
                str_contains($key, 'onu_deposit') => $patch['onu_deposit'] = (float) preg_replace('/[^0-9.]/', '', $value),
                str_contains($key, 'collector') => $patch['legacy_portal_collector'] = $value,
                str_contains($key, 'technician') => $patch['legacy_portal_technician'] = $value,
                str_contains($key, 'assigned_employee') || $key === 'employee' => $patch['legacy_portal_assigned_employee'] = $value,
                default => null,
            };
        }

        $this->applyStaffNameLinks($patch);

        if ($network !== []) {
            $patch['legacy_portal_network'] = $network;
        }

        return $patch;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function applyOnuMac(array &$patch, string $value, Customer $customer): void
    {
        $mac = preg_replace('/[^A-Fa-f0-9:]/', '', $value) ?? '';
        if (strlen(str_replace(':', '', $mac)) < 12) {
            return;
        }

        $routerMac = strtoupper(str_replace([':', '-', '.'], '', (string) ($customer->meta['mac_binding'] ?? '')));
        $onuMac = strtoupper(str_replace([':', '-', '.'], '', $mac));
        if ($routerMac !== '' && $routerMac === $onuMac) {
            return;
        }

        $patch['onu_mac'] = $mac;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function applyStaffNameLinks(array &$patch): void
    {
        $collectorName = trim((string) ($patch['legacy_portal_collector'] ?? ''));
        if ($collectorName !== '') {
            $userId = \App\Models\User::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($collectorName)])
                ->value('id');
            if ($userId !== null) {
                $patch['collector_id'] = (int) $userId;
            }
        }

        $technicianName = trim((string) ($patch['legacy_portal_technician'] ?? ''));
        if ($technicianName !== '') {
            $userId = \App\Models\User::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($technicianName)])
                ->value('id');
            if ($userId !== null) {
                $patch['technician_id'] = (int) $userId;
            }
        }
    }
}
