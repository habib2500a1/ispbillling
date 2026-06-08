<?php

return [
    'enabled' => (bool) env('RESELLER_PORTAL_ENABLED', true),
    'default_password' => env('RESELLER_PORTAL_DEFAULT_PASSWORD', '123456'),
    'session' => [
        'remember_default' => (bool) env('RESELLER_REMEMBER_DEFAULT', env('AUTH_REMEMBER_DEFAULT', true)),
    ],
];
