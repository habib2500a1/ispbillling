<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Support\Collection;

/**
 * Enriches customer search rows with duplicate-name hints for mobile/web collection UI.
 */
final class CustomerSearchPresenter
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function annotateDuplicateNames(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $grouped = $rows->groupBy(fn (array $row): string => $this->normalizeName((string) ($row['name'] ?? '')));

        $systemCounts = $this->systemNameCounts(
            $grouped->keys()->filter(fn (string $k): bool => $k !== '')->all(),
        );

        return $rows->map(function (array $row) use ($grouped, $systemCounts): array {
            $key = $this->normalizeName((string) ($row['name'] ?? ''));
            if ($key === '') {
                return array_merge($row, [
                    'duplicate_name_count' => 1,
                    'has_duplicate_name' => false,
                    'same_name_codes' => [],
                    'same_name_hint' => null,
                ]);
            }

            $inResults = $grouped[$key] ?? collect();
            $resultCount = $inResults->count();
            $systemCount = (int) ($systemCounts[$key] ?? $resultCount);
            $codes = $inResults
                ->pluck('customer_code')
                ->filter()
                ->values()
                ->all();

            $others = array_values(array_filter(
                $codes,
                fn (string $code): bool => $code !== (string) ($row['customer_code'] ?? ''),
            ));

            $hasDuplicate = $systemCount > 1 || $resultCount > 1;

            return array_merge($row, [
                'duplicate_name_count' => max($systemCount, $resultCount),
                'has_duplicate_name' => $hasDuplicate,
                'same_name_codes' => $others,
                'same_name_hint' => $hasDuplicate
                    ? $this->hint($systemCount, $resultCount, $row['customer_code'] ?? '', $others)
                    : null,
            ]);
        });
    }

    /**
     * @param  list<string>  $normalizedNames
     * @return array<string, int>
     */
    private function systemNameCounts(array $normalizedNames): array
    {
        if ($normalizedNames === []) {
            return [];
        }

        $counts = [];
        foreach ($normalizedNames as $name) {
            $counts[$name] = (int) Customer::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                ->count();
        }

        return $counts;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * @param  list<string>  $otherCodes
     */
    private function hint(int $systemCount, int $resultCount, string $selfCode, array $otherCodes): string
    {
        if ($resultCount > 1) {
            $list = array_filter([$selfCode, ...$otherCodes]);

            return 'Same name in results: '.implode(', ', $list)." ({$resultCount})";
        }

        if ($systemCount > 1) {
            $extra = $otherCodes !== [] ? ' — also: '.implode(', ', array_slice($otherCodes, 0, 5)) : '';

            return "Same name exists ({$systemCount} clients){$extra}";
        }

        return 'Same name';
    }
}
