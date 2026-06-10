<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ISPTrack source database (for isp:export-isptrack-json)
    |--------------------------------------------------------------------------
    */
    'db' => [
        'host' => env('ISPTRACK_DB_HOST', '127.0.0.1'),
        'port' => env('ISPTRACK_DB_PORT', '3306'),
        'database' => env('ISPTRACK_DB_DATABASE', 'isptrack'),
        'username' => env('ISPTRACK_DB_USERNAME', 'root'),
        'password' => env('ISPTRACK_DB_PASSWORD', ''),
    ],

    'default_area_name' => env('ISPTRACK_DEFAULT_AREA', 'Main coverage'),
    'default_area_code' => env('ISPTRACK_DEFAULT_AREA_CODE', 'MAIN'),
];
