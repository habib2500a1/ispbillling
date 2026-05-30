<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Support\CustomerPppLoginResolver;
use App\Support\EponLabel;
use App\Support\MacAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CustomerOnuSmartLinkService
{
    public const REASON_LOGIN_EXACT = 'login_exact';

    public const REASON_EPON_EXACT = 'epon_exact';

    public const REASON_ONU_MAC_EXACT = 'onu_mac_exact';

    public const REASON_DESC_EXACT = 'desc_exact';

    /** Customer MAC learned behind the ONU on the OLT forwarding table == PPPoE caller_id. */
    public const REASON_OLT_FDB_MAC = 'olt_fdb_mac';

    /**
     * @return array{
     *   pruned: int,
     *   linked: int,
     *   skipped: int,
     *   conflicts: int,
     *   by_reason: array<string, int>
     * }
     */
    public function smartRelinkTenant(int $tenantId, bool $resetWrong = true): array
    {
        $stats = [
            'pruned' => 0,
            'linked' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'by_reason' => [],
        ];

        if ($resetWrong) {
            $stats['pruned'] = $this->pruneInvalidLinks($tenantId);
        }

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('activePppSession')
            ->orderBy('id')
            ->get();

        $onus = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->orderByDesc('rx_power_dbm')
            ->get();

        /** @var list<array{customer: Customer, onu: Device, score: int, reason: string}> $candidates */
        $candidates = [];

        foreach ($customers as $customer) {
            if ($this->findLinkedOnu($customer, $onus) !== null) {
                continue;
            }

            foreach ($this->scoreOnuCandidates($customer, $onus->whereNull('customer_id')) as $row) {
                if ($row['score'] >= $this->minAutoScore()) {
                    $candidates[] = [
                        'customer' => $customer,
                        'onu' => $row['onu'],
                        'score' => $row['score'],
                        'reason' => $row['reason'],
                    ];
                }
            }
        }

        usort($candidates, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $usedCustomers = [];
        $usedOnus = $onus->whereNotNull('customer_id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($candidates as $row) {
            $customerId = (int) $row['customer']->id;
            $onuId = (int) $row['onu']->id;

            if (isset($usedCustomers[$customerId]) || in_array($onuId, $usedOnus, true)) {
                $stats['conflicts']++;

                continue;
            }

            if (! $this->isUniqueBestMatch($row, $candidates)) {
                $stats['skipped']++;

                continue;
            }

            app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer(
                $row['customer'],
                $onuId,
                $row['reason'],
                $row['score'],
            );

            $usedCustomers[$customerId] = true;
            $usedOnus[] = $onuId;
            $stats['linked']++;
            $stats['by_reason'][$row['reason']] = ($stats['by_reason'][$row['reason']] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * @return array{onu: ?Device, score: int, reason: ?string}
     */
    public function findConfidentMatch(Customer $customer): array
    {
        $onus = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('type', 'onu')
            ->whereNull('customer_id')
            ->orderByDesc('rx_power_dbm')
            ->get();

        $scored = $this->scoreOnuCandidates($customer, $onus)
            ->filter(fn (array $r): bool => $r['score'] >= $this->minAutoScore())
            ->sortByDesc('score')
            ->values();

        if ($scored->isEmpty()) {
            return ['onu' => null, 'score' => 0, 'reason' => null];
        }

        $best = $scored->first();
        $second = $scored->get(1);

        if ($second !== null && ($best['score'] - $second['score']) < $this->minScoreGap()) {
            return ['onu' => null, 'score' => (int) $best['score'], 'reason' => null];
        }

        return [
            'onu' => $best['onu'],
            'score' => (int) $best['score'],
            'reason' => $best['reason'],
        ];
    }

    public function pruneInvalidLinks(int $tenantId): int
    {
        $pruned = 0;

        Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('customer_id')
            ->each(function (Device $onu) use (&$pruned): void {
                if ($this->shouldUnlinkOnu($onu)) {
                    $onu->forceFill(['customer_id' => null, 'status' => 'inventory'])->saveQuietly();
                    $pruned++;
                }
            });

        $this->dedupeCustomerOnuLinks($tenantId);

        return $pruned;
    }

    private function shouldUnlinkOnu(Device $onu): bool
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $linkedBy = (string) ($meta['linked_by'] ?? '');

        if ($linkedBy === 'manual') {
            return false;
        }

        if (str_starts_with((string) $onu->serial_number, 'SUB-')) {
            return true;
        }

        if (! empty($meta['auto_provisioned']) && $onu->rx_power_dbm === null) {
            return true;
        }

        if ($onu->customer_id === null) {
            return false;
        }

        $customer = Customer::query()->withoutGlobalScopes()->find($onu->customer_id);
        if ($customer === null) {
            return true;
        }

        $score = $this->scorePair($customer, $onu)['score'];

        return $score < $this->minKeepScore();
    }

    private function dedupeCustomerOnuLinks(int $tenantId): void
    {
        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['devices' => fn ($q) => $q->where('type', 'onu')->orderByDesc('rx_power_dbm')])
            ->each(function (Customer $customer): void {
                $onus = $customer->devices->where('type', 'onu')->values();
                if ($onus->count() <= 1) {
                    return;
                }
                foreach ($onus->skip(1) as $extra) {
                    $extra->forceFill(['customer_id' => null, 'status' => 'inventory'])->saveQuietly();
                }
            });
    }

    /**
     * @param  Collection<int, Device>  $onus
     */
    private function findLinkedOnu(Customer $customer, Collection $onus): ?Device
    {
        $fromRelation = app(CustomerOnuAutoProvisionService::class)->findOnuForCustomer($customer);
        if ($fromRelation !== null) {
            return $fromRelation;
        }

        return $onus->firstWhere('customer_id', $customer->id);
    }

    /**
     * @param  Collection<int, Device>  $onus
     * @return Collection<int, array{onu: Device, score: int, reason: string}>
     */
    private function scoreOnuCandidates(Customer $customer, Collection $onus): Collection
    {
        return $onus->map(fn (Device $onu): array => $this->scorePair($customer, $onu))
            ->filter(fn (array $r): bool => $r['score'] > 0)
            ->values();
    }

    /**
     * @return array{onu: Device, score: int, reason: string}
     */
    private function scorePair(Customer $customer, Device $onu): array
    {
        $login = CustomerPppLoginResolver::normalize($customer->pppLoginName());
        $meta = is_array($onu->meta) ? $onu->meta : [];

        $clientCode = trim((string) ($customer->customer_code ?? ''));
        $description = trim((string) ($meta['bdcom_description'] ?? ''));
        if ($clientCode !== '' && $description !== ''
            && ! \App\Support\BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel($description)
            && $clientCode === $description) {
            return ['onu' => $onu, 'score' => 100, 'reason' => self::REASON_DESC_EXACT];
        }

        if ($login !== '') {
            foreach ([
                (string) $onu->onu_external_id,
                (string) ($meta['ppp_login'] ?? ''),
                (string) ($meta['subscriber_login'] ?? ''),
                $description,
            ] as $value) {
                if ($value !== '' && CustomerPppLoginResolver::normalize($value) === $login) {
                    return ['onu' => $onu, 'score' => 100, 'reason' => self::REASON_LOGIN_EXACT];
                }
            }

            if (CustomerOnuMatcher::looksLikeLoginSerial((string) $onu->serial_number)
                && CustomerPppLoginResolver::normalize((string) $onu->serial_number) === $login) {
                return ['onu' => $onu, 'score' => 95, 'reason' => self::REASON_LOGIN_EXACT];
            }
        }

        foreach (CustomerOnuMatcher::eponHintsForCustomer($customer) as $label) {
            $normalized = EponLabel::normalize($label);
            if ($normalized !== null && strcasecmp((string) $onu->display_name, $normalized) === 0) {
                return ['onu' => $onu, 'score' => 92, 'reason' => self::REASON_EPON_EXACT];
            }
        }

        foreach (CustomerOnuMatcher::macCandidatesForCustomer($customer) as $macCompact) {
            $onuMac = MacAddress::normalizeCompact((string) $onu->mac_address);
            if ($onuMac !== null && $onuMac === $macCompact) {
                return ['onu' => $onu, 'score' => 98, 'reason' => self::REASON_ONU_MAC_EXACT];
            }
        }

        return ['onu' => $onu, 'score' => 0, 'reason' => ''];
    }

    /**
     * @param  array{customer: Customer, onu: Device, score: int, reason: string}  $row
     * @param  list<array{customer: Customer, onu: Device, score: int, reason: string}>  $all
     */
    private function isUniqueBestMatch(array $row, array $all): bool
    {
        $customerId = (int) $row['customer']->id;
        $onuId = (int) $row['onu']->id;

        foreach ($all as $other) {
            if ((int) $other['customer']->id === $customerId && (int) $other['onu']->id !== $onuId) {
                if ($other['score'] >= $row['score']) {
                    return false;
                }
            }
            if ((int) $other['onu']->id === $onuId && (int) $other['customer']->id !== $customerId) {
                if ($other['score'] >= $row['score']) {
                    return false;
                }
            }
        }

        return true;
    }

    private function minAutoScore(): int
    {
        return (int) config('optical.smart_link_min_score', 90);
    }

    private function minKeepScore(): int
    {
        return (int) config('optical.smart_link_min_keep_score', 85);
    }

    private function minScoreGap(): int
    {
        return (int) config('optical.smart_link_min_gap', 10);
    }
}
