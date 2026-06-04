<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Map legacy portal payment "ReceivedBy" and imported app users to subscriber collector_id.
 */
final class LegacyPortalCollectorSyncService
{
    /**
     * @return array{customers_updated: int, unmatched: int}
     */
    public function syncAll(int $tenantId = 1): array
    {
        $lookup = $this->buildStaffLookup($tenantId);
        $updated = 0;
        $unmatched = 0;

        Customer::query()
            ->fromLegacyPortal()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->chunkById(100, function ($customers) use ($lookup, &$updated, &$unmatched): void {
                foreach ($customers as $customer) {
                    $collector = $this->resolveCollectorForCustomer($customer, $lookup);
                    if ($collector === null) {
                        $unmatched++;

                        continue;
                    }

                    $meta = is_array($customer->meta) ? $customer->meta : [];
                    if ((int) ($meta['collector_id'] ?? 0) === $collector['id']) {
                        continue;
                    }

                    $meta['collector_id'] = $collector['id'];
                    $meta['legacy_portal_collector'] = $collector['label'];
                    $customer->forceFill(['meta' => $meta])->saveQuietly();
                    $updated++;
                }
            });

        return ['customers_updated' => $updated, 'unmatched' => $unmatched];
    }

    /**
     * @return array<string, array{id: int, label: string}>
     */
    private function buildStaffLookup(int $tenantId): array
    {
        $lookup = [];

        foreach (User::query()->where('tenant_id', $tenantId)->get(['id', 'name', 'email']) as $user) {
            $lookup[mb_strtolower($user->name)] = ['id' => (int) $user->id, 'label' => $user->name];

            if (str_contains($user->email, '@import.local')) {
                $local = Str::contains($user->email, 'legacyportal+')
                    ? Str::before(Str::after($user->email, 'legacyportal+'), '@')
                    : Str::before(Str::after($user->email, 'ispdigital+'), '@');
                if ($local !== '') {
                    $lookup[mb_strtolower($local)] = ['id' => (int) $user->id, 'label' => $user->name];
                }
            }
        }

        return $lookup;
    }

    /**
     * @param  array<string, array{id: int, label: string}>  $lookup
     * @return array{id: int, label: string}|null
     */
    private function resolveCollectorForCustomer(Customer $customer, array $lookup): ?array
    {
        $payment = Payment::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('meta')
            ->orderByDesc('paid_at')
            ->first();

        if ($payment !== null) {
            $meta = is_array($payment->meta) ? $payment->meta : [];
            $receivedBy = trim((string) ($meta['received_by'] ?? ''));
            if ($receivedBy !== '') {
                $key = mb_strtolower($receivedBy);
                if (isset($lookup[$key])) {
                    return $lookup[$key];
                }
            }
        }

        $customerMeta = is_array($customer->meta) ? $customer->meta : [];
        $assigned = trim((string) ($customerMeta['assigned_employee'] ?? $customerMeta['legacy_portal_assigned_employee'] ?? ''));
        if ($assigned !== '') {
            $key = mb_strtolower($assigned);

            return $lookup[$key] ?? null;
        }

        return null;
    }
}
