<?php

return [

    /** MikroTik / POP PPTP server (control channel). */
    'server' => env('OLT_PPTP_SERVER', '103.29.127.228'),

    /** Routed via tunnel when PPP is up. */
    'olt_subnet' => env('OLT_PPTP_SUBNET', '103.29.127.0/24'),

    /** systemd unit name on app server (legacy single-tunnel). */
    'systemd_unit' => 'isp-olt-pptp',

    /** Root-only control script (www-data sudo). */
    'ctl_script' => env('OLT_VPN_CTL_SCRIPT', env('OLT_PPTP_CTL_SCRIPT', base_path('scripts/olt-pptp/isp-olt-vpn-ctl'))),
];
