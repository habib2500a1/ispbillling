<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Support\BdPhoneNormalizer;
use App\Support\MfsPaymentReferenceParser;
use Illuminate\Support\Collection;

final class MfsCustomerReferenceMatcher
{
    /**
     * @return array{
     *   customer: ?Customer,
     *   customers: list<Customer>,
     *   token: ?string,
     *   matched_by: ?string,
     *   candidates: list<string>,
     * }
     */
    public function resolve(
        int $tenantId,
        string $message,
        ?string $explicitReference = null,
        ?string $knownTrxId = null,
        ?string $senderPhone = null,
    ): array {
        $refIntent = MfsPaymentReferenceParser::messageHasReferenceIntent($message, $explicitReference);

        $explicitToken = MfsPaymentReferenceParser::normalizeReferenceToken($explicitReference, $knownTrxId);

        if ($refIntent) {
            $tokens = $explicitToken !== null ? [$explicitToken] : [];
            $tokens = array_values(array_unique([
                ...$tokens,
                ...MfsPaymentReferenceParser::extractLabeledReferences($message, $knownTrxId),
            ]));
        } else {
            $tokens = [];
            if ($explicitToken !== null) {
                $tokens[] = $explicitToken;
            }
            $tokens = array_values(array_unique([
                ...$tokens,
                ...MfsPaymentReferenceParser::extractFromMessage($message, $knownTrxId),
            ]));
        }

        $matches = collect();

        foreach ($tokens as $token) {
            $customer = $this->findByToken($tenantId, $token);
            if ($customer !== null) {
                $matches->put($customer->id, ['customer' => $customer, 'token' => $token, 'matched_by' => 'sms_reference']);
            }
        }

        if ($matches->count() === 1) {
            $row = $matches->first();

            return [
                'customer' => $row['customer'],
                'customers' => [$row['customer']],
                'token' => $row['token'],
                'matched_by' => $row['matched_by'],
                'candidates' => $tokens,
            ];
        }

        // Ref/Counter দেওয়া থাকলে ফোন দিয়ে অন্য ID-তে লাগানো যাবে না — admin assign বা pending।
        if ($refIntent) {
            return [
                'customer' => null,
                'customers' => $matches->pluck('customer')->values()->all(),
                'token' => $tokens[0] ?? null,
                'matched_by' => $matches->count() > 1 ? 'sms_reference_ambiguous' : 'sms_reference_unmatched',
                'candidates' => $tokens,
            ];
        }

        if (! (bool) config('mfs_personal.sms_ingest.match_sender_phone', true)) {
            return [
                'customer' => null,
                'customers' => [],
                'token' => null,
                'matched_by' => null,
                'candidates' => $tokens,
            ];
        }

        $phoneMatches = $this->findAllByPhone($tenantId, $senderPhone);
        if ($phoneMatches->isNotEmpty()) {
            if ($phoneMatches->count() === 1) {
                $customer = $phoneMatches->first();

                return [
                    'customer' => $customer,
                    'customers' => [$customer],
                    'token' => BdPhoneNormalizer::normalize($senderPhone),
                    'matched_by' => 'sms_sender_phone',
                    'candidates' => $tokens,
                ];
            }

            if ($matches->isNotEmpty()) {
                $phoneMatches = $phoneMatches->filter(
                    fn (Customer $c): bool => $matches->has($c->id),
                )->values();
            }

            if ($phoneMatches->count() === 1) {
                $customer = $phoneMatches->first();

                return [
                    'customer' => $customer,
                    'customers' => [$customer],
                    'token' => BdPhoneNormalizer::normalize($senderPhone),
                    'matched_by' => 'sms_sender_phone',
                    'candidates' => $tokens,
                ];
            }

            if ($phoneMatches->count() > 1 && (bool) config('mfs_personal.sms_ingest.split_same_phone_customers', true)) {
                return [
                    'customer' => null,
                    'customers' => $phoneMatches->all(),
                    'token' => BdPhoneNormalizer::normalize($senderPhone),
                    'matched_by' => 'sms_sender_phone_split',
                    'candidates' => $tokens,
                ];
            }
        }

        return [
            'customer' => null,
            'customers' => [],
            'token' => null,
            'matched_by' => null,
            'candidates' => $tokens,
        ];
    }

    public function findByToken(int $tenantId, string $token): ?Customer
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $query = Customer::query()->withoutGlobalScopes()->where('tenant_id', $tenantId);

        if (str_contains($token, '@')) {
            $login = strtolower($token);

            return (clone $query)
                ->where(function ($q) use ($login, $token): void {
                    $q->whereRaw('LOWER(radius_username) = ?', [$login])
                        ->orWhereRaw('LOWER(mikrotik_secret_name) = ?', [$login])
                        ->orWhere('radius_username', $token)
                        ->orWhere('mikrotik_secret_name', $token);
                })
                ->first();
        }

        if (preg_match('/^\d+$/', $token)) {
            $variants = MfsPaymentReferenceParser::numericVariants($token);

            return (clone $query)
                ->where(function ($q) use ($variants): void {
                    $q->whereIn('customer_code', $variants)
                        ->orWhereIn('radius_username', $variants)
                        ->orWhereIn('mikrotik_secret_name', $variants);
                })
                ->first();
        }

        return (clone $query)
            ->where(function ($q) use ($token): void {
                $lower = strtolower($token);
                $q->where('customer_code', $token)
                    ->orWhereRaw('LOWER(customer_code) = ?', [$lower])
                    ->orWhereRaw('LOWER(radius_username) = ?', [$lower])
                    ->orWhereRaw('LOWER(mikrotik_secret_name) = ?', [$lower])
                    ->orWhere('radius_username', $token)
                    ->orWhere('mikrotik_secret_name', $token);
            })
            ->first();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function findAllByPhone(int $tenantId, ?string $senderPhone): Collection
    {
        $variants = BdPhoneNormalizer::variants($senderPhone);
        if ($variants === []) {
            return collect();
        }

        return Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($variants): void {
                foreach ($variants as $phone) {
                    $q->orWhere('phone', $phone)
                        ->orWhere('phone', 'like', '%'.substr($phone, -10))
                        ->orWhereHas('contacts', function ($cq) use ($phone): void {
                            $cq->where('phone', $phone)
                                ->orWhere('phone', 'like', '%'.substr($phone, -10));
                        });
                }
            })
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();
    }
}
