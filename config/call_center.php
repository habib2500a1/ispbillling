<?php

return [
    'enabled' => (bool) env('CALL_CENTER_ENABLED', true),

    /**
     * log_only — manual logging only
     * webhook — accept CDR from external PBX
     * asterisk_ami — reserved for future AMI integration
     */
    'driver' => env('CALL_CENTER_DRIVER', 'log_only'),

    'webhook_secret' => env('CALL_CENTER_WEBHOOK_SECRET'),

    'auto_ticket_on_missed' => (bool) env('CALL_CENTER_AUTO_TICKET_ON_MISSED', true),

    'default_country_code' => env('CALL_CENTER_DEFAULT_COUNTRY_CODE', '880'),

    'websip_enabled' => (bool) env('CALL_CENTER_WEBSIP_ENABLED', false),

    /** When WSS URI is empty, try common wss://{sip_domain}:7443/ws etc. (same login as PortSIP). */
    'websip_auto_wss_candidates' => (bool) env('CALL_CENTER_WEBSIP_AUTO_WSS', true),

    /** Per-URI timeout while trying WSS endpoints (ms). */
    'websip_wss_connect_timeout_ms' => (int) env('CALL_CENTER_WEBSIP_WSS_TIMEOUT_MS', 8000),

    /** Max wait for SIP REGISTER before showing not-registered (ms). */
    'websip_register_wait_ms' => (int) env('CALL_CENTER_WEBSIP_REGISTER_WAIT_MS', 20000),

    /** Prefix for voice campaign SMS body (transcript delivered as text until voice gateway is wired). */
    'voice_sms_message_prefix' => env('CALL_CENTER_VOICE_SMS_PREFIX', '[Voice] '),

    /**
     * Outbound voice calls (IVR / voice blast). When SMS template has voice on but SMS off, dunning uses this.
     *
     * driver: log_only | http_webhook (POST JSON to VOICE_CALL_WEBHOOK_URL)
     */
    'voice_call' => [
        'enabled' => (bool) env('VOICE_CALL_ENABLED', false),
        'driver' => env('VOICE_CALL_DRIVER', 'log_only'),
        'webhook_url' => env('VOICE_CALL_WEBHOOK_URL'),
        'webhook_secret' => env('VOICE_CALL_WEBHOOK_SECRET'),
        'timeout' => (int) env('VOICE_CALL_TIMEOUT', 30),
    ],
];
