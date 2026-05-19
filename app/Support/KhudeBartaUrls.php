<?php

namespace App\Support;

final class KhudeBartaUrls
{
    public static function dlrCallbackUrl(): string
    {
        $configured = trim((string) config('notifications.sms.khudebarta_dlr_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url', 'http://localhost'), '/').'/webhooks/sms/khudebarta/dlr';
    }
}
