<?php

return [
    'enabled' => (bool) env('RADIUS_ADMIN_ENABLED', false),
    'radcheck_table' => env('RADIUS_RADCHECK_TABLE', 'radcheck'),
    'radreply_table' => env('RADIUS_RADREPLY_TABLE', 'radreply'),
    'usergroup_table' => env('RADIUS_USERGROUP_TABLE', 'radusergroup'),
];
