<?php

return [
    /*
    | Bangladesh operations: Asia/Dhaka (BDT, UTC+6). All schedules (billing, automatic process) use app timezone.
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),
    'timezone_label' => env('APP_TIMEZONE_LABEL', 'BDT'),

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

    'invoice_show_logo' => env('ISP_INVOICE_SHOW_LOGO', true),
    'invoice_footer' => env('ISP_INVOICE_FOOTER', 'Thank you for your business. For billing questions, contact us with your invoice number.'),
    'invoice_terms' => env('ISP_INVOICE_TERMS', ''),

    /*
    | When set (e.g. isp.example.com), host "{slug}.isp.example.com" resolves the tenant by slug
    | for data scoping before login. Super-admins bypass the User model tenant scope while logged in.
    */
    'tenant_base_domain' => env('ISP_TENANT_BASE_DOMAIN', ''),

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
        'invoice_show_logo' => filter_var(env('ISP_INVOICE_SHOW_LOGO', true), FILTER_VALIDATE_BOOL),
        'invoice_footer' => (string) env('ISP_INVOICE_FOOTER', 'Thank you for your business. For billing questions, contact us with your invoice number.'),
        'invoice_terms' => (string) env('ISP_INVOICE_TERMS', ''),
        'timezone' => (string) env('APP_TIMEZONE', 'Asia/Dhaka'),
        'timezone_label' => (string) env('APP_TIMEZONE_LABEL', 'BDT'),
    ],
];
