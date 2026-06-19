<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer full-text search (Laravel Scout + Meilisearch)
    |--------------------------------------------------------------------------
    | Managed from admin → Settings → Customer search. No extra .env keys required.
    */
    'enabled' => (bool) env('CUSTOMER_SEARCH_ENABLED', true),

    'use_scout' => true,

    'meilisearch_host' => env('MEILISEARCH_HOST'),

    /** Optional override — normally auto from APP_KEY via CustomerSearchSettings. */
    'meilisearch_key' => env('MEILISEARCH_KEY'),

    'sql_fallback' => (bool) env('CUSTOMER_SCOUT_SQL_FALLBACK', true),

    'auto_index_on_deploy' => (bool) env('CUSTOMER_SEARCH_AUTO_INDEX', true),

    'fallback_master_key' => 'isp_meili_docker_internal_v1',

    'env_defaults' => [
        'enabled' => true,
        'sql_fallback' => true,
    ],

];
