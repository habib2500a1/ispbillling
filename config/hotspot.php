<?php

return [
    'enabled' => (bool) env('HOTSPOT_PORTAL_ENABLED', true),
    'welcome_message' => env('HOTSPOT_WELCOME_MESSAGE', 'Enter your voucher code to connect.'),

    /** Create /ip/hotspot/user on MikroTik when a voucher is redeemed (PHPNuxBill-style). */
    'provision_enabled' => filter_var(env('HOTSPOT_PROVISION_ENABLED', true), FILTER_VALIDATE_BOOL),

    /** RouterOS hotspot profile when package has no mikrotik_profile_name. */
    'default_profile' => env('HOTSPOT_DEFAULT_PROFILE', 'default'),

    /** Fallback MikroTik server id when package has no mikrotik_server_id. */
    'default_mikrotik_server_id' => (int) env('HOTSPOT_DEFAULT_MIKROTIK_SERVER_ID', 0),
];
