<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offline handling (500K-scale best practice)
    |--------------------------------------------------------------------------
    | Never auto-delete subscriber-linked ONU inventory when status goes offline.
    | Power loss, fiber cut, and OLT reboot all look "offline" — MAC must stay.
    */
    'offline_handling' => [
        'save_last_seen' => true,
        'save_offline_since' => true,
        'customer_profile_warning' => true,
        'ticket_suggest_on_offline' => true,
        /** Never delete inventory rows just because oper_status is offline/los. */
        'delete_offline_on_sync' => (bool) env('OPTICAL_DELETE_OFFLINE_ON_SYNC', false),
        /** Skip deleting ONU rows that are linked to a subscriber. */
        'protect_linked_onu_delete' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart automation rules (event / scheduled evaluation)
    |--------------------------------------------------------------------------
    */
    'smart_automation' => [
        'enabled' => (bool) env('OPTICAL_SMART_AUTOMATION', true),
        /** ONU offline longer than N hours + zero due → auto support ticket. */
        'offline_ticket_hours' => (int) env('OPTICAL_OFFLINE_TICKET_HOURS', 24),
        'offline_ticket_requires_zero_due' => true,
        'offline_ticket_priority' => 'medium',
        /** RX thresholds (dBm) — warning vs critical alarm. */
        'rx_warning_dbm' => (float) env('OPTICAL_RX_WARNING_DBM', -28),
        'rx_critical_dbm' => (float) env('OPTICAL_RX_CRITICAL_DBM', -30),
    ],

    /*
    |--------------------------------------------------------------------------
    | ONU replace / unauthorized workflow
    |--------------------------------------------------------------------------
    */
    'mac_archive' => [
        'enabled' => (bool) env('OPTICAL_MAC_ARCHIVE_ENABLED', true),
        'max_entries' => 10,
    ],

    'unauthorized_onu' => [
        'detect_on_sync' => true,
        'status_values' => ['unauthorized', 'auth_fail', 'illegal'],
    ],

];
