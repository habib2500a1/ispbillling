<?php

return [

    'log_delivery_only' => (bool) env('NOTIFICATIONS_LOG_ONLY', false),

    'email' => [
        'enabled' => (bool) env('NOTIFICATIONS_EMAIL_ENABLED', true),
        'from_name' => env('NOTIFICATIONS_EMAIL_FROM_NAME', env('MAIL_FROM_NAME', 'ISP Billing')),
    ],

    'sms' => [
        'enabled' => (bool) env('NOTIFICATIONS_SMS_ENABLED', false),
        'provider' => env('NOTIFICATIONS_SMS_PROVIDER', 'bulksmsbd'),
        'api_url' => env('NOTIFICATIONS_SMS_API_URL', 'https://bulksmsbd.net/api/smsapi'),
        'api_key' => env('NOTIFICATIONS_SMS_API_KEY'),
        'secret_key' => env('NOTIFICATIONS_SMS_SECRET_KEY'),
        'sender_id' => env('NOTIFICATIONS_SMS_SENDER_ID', 'ISP'),
        'timeout' => (int) env('NOTIFICATIONS_SMS_TIMEOUT', 30),
        /** KhudeBarta hash: apikey_secretkey_callerID_toUser_messageContent | apikey_secretkey_toUser_messageContent | secretkey_toUser_messageContent */
        'khudebarta_hash_formula' => env('KHUDEBARTA_HASH_FORMULA', 'apikey_secretkey_callerID_toUser_messageContent'),
        'khudebarta_hash_uppercase' => (bool) env('KHUDEBARTA_HASH_UPPERCASE', false),
        /** Full DLR callback URL for KhudeBarta portal (Delivery API → Query). Empty = APP_URL + /webhooks/sms/khudebarta/dlr */
        'khudebarta_dlr_url' => env('KHUDEBARTA_DLR_URL'),
        /** Optional balance endpoint from KhudeBarta (if provided by vendor). */
        'khudebarta_balance_url' => env('KHUDEBARTA_BALANCE_URL'),
        'balance_cache_minutes' => (int) env('NOTIFICATIONS_SMS_BALANCE_CACHE_MINUTES', 5),
    ],

    'whatsapp' => [
        'enabled' => (bool) env('NOTIFICATIONS_WHATSAPP_ENABLED', false),
        'api_version' => env('NOTIFICATIONS_WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('NOTIFICATIONS_WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('NOTIFICATIONS_WHATSAPP_ACCESS_TOKEN'),
    ],

    'telegram' => [
        'enabled' => (bool) env('NOTIFICATIONS_TELEGRAM_ENABLED', false),
        'bot_token' => env('NOTIFICATIONS_TELEGRAM_BOT_TOKEN'),
        'ops_chat_id' => env('NOTIFICATIONS_TELEGRAM_OPS_CHAT_ID'),
    ],

    'events' => [
        'payment_success' => [
            'enabled' => (bool) env('NOTIFICATIONS_PAYMENT_SUCCESS', true),
            'channels' => ['email', 'sms'],
            'telegram_ops' => true,
        ],
        'invoice_due' => [
            'enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
            'days_before' => (int) env('SMS_REMINDERS_DAYS', 3),
            'channels' => ['email', 'sms'],
            'telegram_ops' => false,
        ],
        'invoice_due_soon' => [
            'enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
            'channels' => ['email', 'sms'],
            'telegram_ops' => false,
        ],
        'invoice_due_today' => [
            'enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
            'channels' => ['email', 'sms', 'whatsapp'],
            'telegram_ops' => false,
        ],
        'invoice_overdue_3' => [
            'enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
            'channels' => ['email', 'sms', 'whatsapp'],
            'telegram_ops' => false,
        ],
        'invoice_overdue_7' => [
            'enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
            'channels' => ['email', 'sms', 'whatsapp'],
            'telegram_ops' => true,
        ],
        'invoice_overdue_14' => [
            'enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
            'channels' => ['email', 'sms', 'whatsapp'],
            'telegram_ops' => true,
        ],
        'fup_warning' => [
            'enabled' => (bool) env('BILLING_FUP_ALERTS_ENABLED', true),
            'channels' => ['email', 'sms'],
            'telegram_ops' => false,
        ],
        'fup_critical' => [
            'enabled' => (bool) env('BILLING_FUP_ALERTS_ENABLED', true),
            'channels' => ['email', 'sms', 'whatsapp'],
            'telegram_ops' => false,
        ],
        'outage' => [
            'enabled' => true,
            'channels' => ['email', 'sms', 'whatsapp'],
            'telegram_ops' => true,
        ],
        'pending_gateway_payment' => [
            'enabled' => true,
            'channels' => [],
            'telegram_ops' => true,
        ],
        'session_integrity' => [
            'enabled' => true,
            'channels' => [],
            'telegram_ops' => true,
        ],
        'portal_otp' => [
            'enabled' => true,
            'channels' => ['email', 'sms'],
        ],
        'reseller_commission' => [
            'enabled' => (bool) env('NOTIFICATIONS_RESELLER_COMMISSION', true),
            'channels' => ['sms', 'email'],
        ],
        'reseller_commission_payout' => [
            'enabled' => (bool) env('NOTIFICATIONS_RESELLER_PAYOUT', true),
            'channels' => ['sms', 'email'],
        ],
    ],

    'templates' => [
        'payment_success' => "Dear {name},\n\nPayment of {amount} BDT received for invoice {invoice_number}. Receipt: {receipt_number}.\n\nThank you.",
        'invoice_due' => "Dear {name},\n\nInvoice {invoice_number} balance {balance} BDT is due on {due_date}. Please pay via the customer portal.",
        'invoice_due_soon' => "Dear {name},\n\nReminder: Invoice {invoice_number} — {balance} BDT due on {due_date}. Pay: {payment_url}",
        'invoice_due_today' => "Dear {name},\n\nInvoice {invoice_number} ({balance} BDT) is due TODAY ({due_date}). Pay now: {payment_url}",
        'invoice_overdue_3' => "Dear {name},\n\nOVERDUE: Invoice {invoice_number} — {balance} BDT was due {due_date}. Pay: {payment_url}",
        'invoice_overdue_7' => "Dear {name},\n\nURGENT: Invoice {invoice_number} — {balance} BDT is 7+ days overdue. Pay: {payment_url}",
        'invoice_overdue_14' => "Dear {name},\n\nFINAL NOTICE: Invoice {invoice_number} — {balance} BDT overdue since {due_date}. Pay immediately: {payment_url}",
        'fup_warning' => "Dear {name},\n\nData usage alert: {gb_used} GB used of {gb_allowed} GB ({percent}%) this billing period. Period ends {period_end}.",
        'fup_critical' => "Dear {name},\n\nData limit reached: {gb_used} GB of {gb_allowed} GB ({percent}%). Extra usage may be billed. Period ends {period_end}.",
        'outage' => "Dear {name},\n\nService notice: {message}\n\nWe apologize for the inconvenience.",
        'portal_otp' => "Your portal login code is {code}. Valid for {minutes} minutes. Do not share this code.",
        'payment_success_ops' => "Payment received: {name} — {amount} BDT ({invoice_number})",
        'outage_ops' => "Outage broadcast sent to {count} subscriber(s): {message}",
        'pending_gateway_payment_ops' => 'Pending {gateway} payment: {transaction_id} — {amount} BDT ({name}). Approve in admin.',
        'session_integrity_ops' => 'Session alert [{alert_type}] {login}: {message}',
        'reseller_commission_accrued' => 'Reseller commission {amount} BDT earned (payment {gross} BDT). Your code: {code}',
        'reseller_commission_paid' => 'Commission {amount} BDT credited to your reseller wallet. Code: {code}',
    ],

    'env_defaults' => [
        'log_delivery_only' => (bool) env('NOTIFICATIONS_LOG_ONLY', false),
        'email.enabled' => (bool) env('NOTIFICATIONS_EMAIL_ENABLED', true),
        'sms.enabled' => (bool) env('NOTIFICATIONS_SMS_ENABLED', false),
        'sms.provider' => env('NOTIFICATIONS_SMS_PROVIDER', 'bulksmsbd'),
        'sms.api_url' => env('NOTIFICATIONS_SMS_API_URL', 'https://bulksmsbd.net/api/smsapi'),
        'sms.api_key' => env('NOTIFICATIONS_SMS_API_KEY'),
        'sms.secret_key' => env('NOTIFICATIONS_SMS_SECRET_KEY'),
        'sms.sender_id' => env('NOTIFICATIONS_SMS_SENDER_ID', 'ISP'),
        'whatsapp.enabled' => (bool) env('NOTIFICATIONS_WHATSAPP_ENABLED', false),
        'whatsapp.phone_number_id' => env('NOTIFICATIONS_WHATSAPP_PHONE_NUMBER_ID'),
        'whatsapp.access_token' => env('NOTIFICATIONS_WHATSAPP_ACCESS_TOKEN'),
        'telegram.enabled' => (bool) env('NOTIFICATIONS_TELEGRAM_ENABLED', false),
        'telegram.bot_token' => env('NOTIFICATIONS_TELEGRAM_BOT_TOKEN'),
        'telegram.ops_chat_id' => env('NOTIFICATIONS_TELEGRAM_OPS_CHAT_ID'),
        'events.payment_success.enabled' => (bool) env('NOTIFICATIONS_PAYMENT_SUCCESS', true),
        'events.invoice_due.enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
        'events.invoice_due.days_before' => (int) env('SMS_REMINDERS_DAYS', 3),
    ],
];
