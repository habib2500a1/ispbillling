<?php

namespace App\Services\Billing;

use App\Models\CustomersInfo;

class CustomerIdAllocator
{
    public function prefix(): string
    {
        $raw = siteUrlSettings('customer_id_prefix');

        return $raw === null ? 'FCNET' : trim((string) $raw);
    }

    public function startNumber(): int
    {
        return max(1, (int) (siteUrlSettings('customer_id_start') ?: 100));
    }

    public function highestNumber(): int
    {
        $prefix = $this->prefix();
        $max = $this->startNumber() - 1;

        CustomersInfo::query()
            ->select(['id', 'customer_unique_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($prefix, &$max) {
                foreach ($rows as $row) {
                    $num = $this->numericPart((string) $row->customer_unique_id, $prefix);
                    if ($num !== null && $num > $max) {
                        $max = $num;
                    }
                }
            });

        return $max;
    }

    public function next(?int &$seed = null): string
    {
        if ($seed === null) {
            $seed = $this->highestNumber();
        }

        $n = max($seed + 1, $this->startNumber());
        $id = $this->format($n);

        while (CustomersInfo::where('customer_unique_id', $id)->exists()) {
            $n++;
            $id = $this->format($n);
        }

        $seed = $n;

        return $id;
    }

    public function format(int $number): string
    {
        return $this->prefix().$number;
    }

    protected function numericPart(string $id, string $prefix): ?int
    {
        if ($prefix !== '' && str_starts_with($id, $prefix)) {
            $tail = substr($id, strlen($prefix));
            if ($tail !== '' && ctype_digit($tail)) {
                return (int) $tail;
            }
        }

        if ($prefix === '' && ctype_digit($id)) {
            return (int) $id;
        }

        if (($prefix === '' || str_starts_with($id, $prefix)) && preg_match('/(\d+)$/', $id, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
