<?php

namespace App\Support;

final class NotificationChannel
{
    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const WHATSAPP = 'whatsapp';

    public const TELEGRAM = 'telegram';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::TELEGRAM => 'Telegram',
        ];
    }

    /**
     * @return list<string>
     */
    public static function customerChannels(): array
    {
        return [self::EMAIL, self::SMS, self::WHATSAPP];
    }
}
