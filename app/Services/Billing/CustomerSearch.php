<?php

namespace App\Services\Billing;

use App\Models\CustomersInfo;

final class CustomerSearch
{
    /**
     * @return list<array<string, mixed>>
     */
    public function suggest(string $q, int $limit = 10): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return CustomersInfo::query()
            ->with(['billing', 'pppUser:id,username'])
            ->search($q)
            ->orderBy('customer_name')
            ->limit($limit)
            ->get()
            ->map(function (CustomersInfo $c) {
                $due = max(0, (float) ($c->billing?->due_amount ?? 0));

                return [
                    'id' => $c->customer_unique_id,
                    'name' => (string) $c->customer_name,
                    'username' => (string) ($c->pppUser?->username ?? ''),
                    'mobile' => (string) ($c->mobile ?? ''),
                    'due' => $due,
                    'status' => (string) ($c->status ?? ''),
                    'url' => route('customers.show', encrypt($c->customer_unique_id)),
                ];
            })
            ->all();
    }
}
