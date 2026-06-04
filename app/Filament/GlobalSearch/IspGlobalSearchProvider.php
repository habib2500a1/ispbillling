<?php

namespace App\Filament\GlobalSearch;

use App\Services\Search\GlobalSmartSearchService;
use Filament\GlobalSearch\Contracts\GlobalSearchProvider;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;

final class IspGlobalSearchProvider implements GlobalSearchProvider
{
    public function __construct(
        private readonly GlobalSmartSearchService $search,
    ) {}

    public function getResults(string $query): ?GlobalSearchResults
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return null;
        }

        $items = $this->search->search($query, 15);
        if ($items === []) {
            return null;
        }

        $builder = GlobalSearchResults::make();
        $grouped = [];

        foreach ($items as $item) {
            $category = match ($item['type']) {
                'customer' => 'Subscribers',
                'online' => 'Online PPP',
                'invoice' => 'Invoices',
                'payment' => 'Payments',
                'ticket' => 'Support',
                'onu' => 'ONU / devices',
                'router' => 'MikroTik',
                default => 'Other',
            };

            $grouped[$category][] = new GlobalSearchResult(
                title: $item['label'],
                url: $item['url'],
                details: $item['sublabel'] !== '' ? ['Info' => $item['sublabel']] : [],
            );
        }

        foreach ($grouped as $category => $results) {
            $builder->category($category, $results);
        }

        return $builder;
    }
}
