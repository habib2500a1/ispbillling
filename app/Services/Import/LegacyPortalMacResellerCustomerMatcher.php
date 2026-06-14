<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Support\CustomerPppLoginResolver;

/**
 * Match MAC reseller client rows to existing legacy portal subscribers (not duplicate Sm* codes).
 */
final class LegacyPortalMacResellerCustomerMatcher
{
    public function find(array $row): ?Customer
    {
        $code = trim((string) ($row['CustomerId'] ?? ''));
        $username = trim((string) ($row['UserName'] ?? ''));
        $login = $username !== '' ? CustomerPppLoginResolver::normalize($username) : '';
        $isMacSmCode = $code !== '' && str_starts_with(strtoupper($code), 'SM');

        if ($code !== '') {
            $byCode = Customer::query()->where('customer_code', $code)->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        if ($username !== '') {
            $candidates = Customer::query()
                ->where(function ($q) use ($username, $login): void {
                    $q->where('mikrotik_secret_name', $username)
                        ->orWhere('radius_username', $username)
                        ->orWhereRaw('LOWER(mikrotik_secret_name) = ?', [$login])
                        ->orWhereRaw('LOWER(radius_username) = ?', [$login]);
                })
                ->orderByRaw("CASE WHEN customer_code NOT LIKE 'Sm%' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->get();

            foreach ($candidates as $candidate) {
                if ($isMacSmCode) {
                    if (strcasecmp($candidate->customer_code, $code) === 0) {
                        return $candidate;
                    }

                    if (! str_starts_with($candidate->customer_code, 'Sm')) {
                        return $candidate;
                    }

                    continue;
                }

                return $candidate;
            }
        }

        $headerId = (int) ($row['CustomerHeaderId'] ?? 0);
        if ($headerId > 0) {
            $byHeader = Customer::query()
                ->fromLegacyPortal()
                ->where('meta->legacy_id', (string) $headerId)
                ->first();

            if ($byHeader === null) {
                $byHeader = Customer::query()
                    ->fromLegacyPortal()
                    ->get()
                    ->first(fn (Customer $c): bool => (int) data_get($c->meta, 'legacy_portal_raw.CustomerHeaderId') === $headerId);
            }

            if ($byHeader !== null) {
                $codeMatches = $code !== '' && strcasecmp($byHeader->customer_code, $code) === 0;
                if ($codeMatches || ($login !== '' && $this->loginMatches($byHeader, $login))) {
                    return $byHeader;
                }
            }
        }

        if (! $isMacSmCode) {
            $phone = preg_replace('/\D+/', '', (string) ($row['MobileNumber'] ?? '')) ?? '';
            if (strlen($phone) >= 10) {
                $suffix = substr($phone, -10);
                $byPhone = Customer::query()
                    ->where('phone', 'like', '%'.$suffix)
                    ->when($login !== '', function ($q) use ($login): void {
                        $q->where(function ($inner) use ($login): void {
                            $inner->whereRaw('LOWER(mikrotik_secret_name) = ?', [$login])
                                ->orWhereRaw('LOWER(radius_username) = ?', [$login])
                                ->orWhereNull('mikrotik_secret_name')
                                ->orWhere('mikrotik_secret_name', '');
                        });
                    })
                    ->orderByRaw("CASE WHEN customer_code NOT LIKE 'Sm%' THEN 0 ELSE 1 END")
                    ->first();

                if ($byPhone !== null) {
                    return $byPhone;
                }
            }
        }

        return null;
    }

    private function loginMatches(Customer $customer, string $login): bool
    {
        if ($login === '') {
            return true;
        }

        foreach ([$customer->mikrotik_secret_name, $customer->radius_username] as $field) {
            if (filled($field) && CustomerPppLoginResolver::normalize((string) $field) === $login) {
                return true;
            }
        }

        return false;
    }
}
