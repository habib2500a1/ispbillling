<?php

namespace App\Services\Search;

use App\Support\CustomerSearchSettings;

/** Apply dashboard / auto-detected customer search config to Scout runtime. */
final class CustomerSearchConfigurator
{
    public static function apply(): void
    {
        if (! CustomerSearchSettings::enabled()) {
            config([
                'customer_search.use_scout' => false,
                'scout.driver' => 'null',
            ]);

            return;
        }

        config([
            'customer_search.use_scout' => true,
            'scout.driver' => 'meilisearch',
            'scout.meilisearch.host' => CustomerSearchSettings::host(),
            'scout.meilisearch.key' => CustomerSearchSettings::masterKey(),
        ]);
    }
}
