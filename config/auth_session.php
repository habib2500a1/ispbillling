<?php

/**
 * Web + mobile session longevity — keep users signed in on app and website.
 */
return [

    /** Pre-check "Remember me" on portal, reseller, and admin login forms. */
    'remember_default' => (bool) env('AUTH_REMEMBER_DEFAULT', true),

    /** Idle web session (minutes) when "Remember me" is off. Default 30 days. */
    'web_session_minutes' => (int) env('SESSION_LIFETIME', 43200),

];
