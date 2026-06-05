<?php

return [
    /*
    | Bangladesh operations: Asia/Dhaka (BDT, UTC+6). All schedules (billing, automatic process) use app timezone.
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),
    'timezone_label' => env('APP_TIMEZONE_LABEL', 'BDT'),

    /** Default map center when subscriber GPS is not set yet (Dhaka). */
    'default_map_lat' => (float) env('ISP_DEFAULT_MAP_LAT', 23.8103),
    'default_map_lng' => (float) env('ISP_DEFAULT_MAP_LNG', 90.4125),

    'admin_email' => env('ISP_ADMIN_EMAIL', 'admin@isp.local'),
    'admin_password' => env('ISP_ADMIN_PASSWORD', 'changeme123!'),

    'company_name' => env('ISP_COMPANY_NAME', 'Radiant Communications Ltd'),
    'company_tagline' => env('ISP_COMPANY_TAGLINE', 'ISP billing & network operations'),
    'company_phone' => env('ISP_COMPANY_PHONE', ''),
    'company_email' => env('ISP_COMPANY_EMAIL', ''),
    'company_address' => env('ISP_COMPANY_ADDRESS', ''),
    'company_website' => env('ISP_COMPANY_WEBSITE', ''),
    'company_tax_id' => env('ISP_COMPANY_TAX_ID', ''),
    'company_logo_url' => env('ISP_COMPANY_LOGO_URL', ''),
    'company_logo_path' => env('ISP_COMPANY_LOGO_PATH', ''),
    'company_favicon_path' => env('ISP_COMPANY_FAVICON_PATH', ''),

    'invoice_show_logo' => env('ISP_INVOICE_SHOW_LOGO', true),
    'invoice_footer' => env('ISP_INVOICE_FOOTER', 'Thank you for your business. For billing questions, contact us with your invoice number.'),
    'invoice_terms' => env('ISP_INVOICE_TERMS', ''),

    /*
    | When set (e.g. isp.example.com), host "{slug}.isp.example.com" resolves the tenant by slug
    | for data scoping before login. Super-admins bypass the User model tenant scope while logged in.
    */
    'tenant_base_domain' => env('ISP_TENANT_BASE_DOMAIN', ''),

    /*
    | saas = you host many ISPs (rent). on_premise = sold copy on customer server (license).
    */
    'deployment_mode' => env('ISP_DEPLOYMENT_MODE', 'saas'),

    'license' => [
        'enforce' => env('ISP_LICENSE_ENFORCE', false),
        'key' => env('ISP_LICENSE_KEY', ''),
        'public_key_path' => env('ISP_LICENSE_PUBLIC_KEY_PATH', base_path('resources/license/public.pem')),
        'private_key_path' => env('ISP_LICENSE_PRIVATE_KEY_PATH', storage_path('license/private.pem')),
    ],

    /*
    | CSS: production uses one bundled file per area (fewer HTTP requests).
    | Run `php artisan isp:build-styles` after editing public/css/admin/** modules.
    */
    'assets' => [
        'bundle_css' => env('ISP_BUNDLE_CSS', env('APP_ENV') === 'production'),
        /** admin-saas.css bundle also contains utilities + responsive when built via isp:build-styles */
        'bundle_includes_extras' => true,
        /** Bump when admin CSS/JS changes so browsers skip stale cached bundles. */
        'version_salt' => (int) env('ISP_ASSET_VERSION_SALT', 7),
    ],

    /*
    | Demo / training instance (Sheba-Fi demo style). Disables live SMS, MikroTik push, WebSIP.
    */
    'demo' => [
        'enabled' => env('ISP_DEMO_MODE', false),
        'banner_label' => env('ISP_DEMO_BANNER_LABEL', 'DEMO'),
        'banner_message' => env('ISP_DEMO_BANNER_MESSAGE', 'Demo mode — no real SMS, router push, or live calls.'),
    ],

    /*
    | Snapshot of .env at bootstrap (safe when config is cached). Used when clearing DB overrides.
    */
    'env_defaults' => [
        'tenant_base_domain' => (string) env('ISP_TENANT_BASE_DOMAIN', ''),
        'company_name' => (string) env('ISP_COMPANY_NAME', 'Radiant Communications Ltd'),
        'company_tagline' => (string) env('ISP_COMPANY_TAGLINE', 'ISP billing & network operations'),
        'company_phone' => (string) env('ISP_COMPANY_PHONE', ''),
        'company_email' => (string) env('ISP_COMPANY_EMAIL', ''),
        'company_address' => (string) env('ISP_COMPANY_ADDRESS', ''),
        'company_website' => (string) env('ISP_COMPANY_WEBSITE', ''),
        'company_tax_id' => (string) env('ISP_COMPANY_TAX_ID', ''),
        'company_logo_url' => (string) env('ISP_COMPANY_LOGO_URL', ''),
        'company_logo_path' => (string) env('ISP_COMPANY_LOGO_PATH', ''),
        'company_favicon_path' => (string) env('ISP_COMPANY_FAVICON_PATH', ''),
        'platform_logo_path' => (string) env('ISP_PLATFORM_LOGO_PATH', ''),
        'platform_favicon_path' => (string) env('ISP_PLATFORM_FAVICON_PATH', ''),
        'invoice_show_logo' => filter_var(env('ISP_INVOICE_SHOW_LOGO', true), FILTER_VALIDATE_BOOL),
        'invoice_footer' => (string) env('ISP_INVOICE_FOOTER', 'Thank you for your business. For billing questions, contact us with your invoice number.'),
        'invoice_terms' => (string) env('ISP_INVOICE_TERMS', ''),
        'timezone' => (string) env('APP_TIMEZONE', 'Asia/Dhaka'),
        'timezone_label' => (string) env('APP_TIMEZONE_LABEL', 'BDT'),
    ],
];
