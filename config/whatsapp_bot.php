<?php

return [
    'enabled' => (bool) env('WHATSAPP_BOT_ENABLED', false),
    'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'isp-webhook-verify'),
    'default_department' => env('WHATSAPP_BOT_DEFAULT_DEPARTMENT', 'billing'),
    'send_payment_links' => (bool) env('WHATSAPP_BOT_SEND_PAY_LINKS', true),
];
