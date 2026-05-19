<?php

namespace App\Support;

final class NotificationEvent
{
    public const PAYMENT_SUCCESS = 'payment_success';

    public const INVOICE_DUE = 'invoice_due';

    public const INVOICE_DUE_SOON = 'invoice_due_soon';

    public const INVOICE_DUE_TODAY = 'invoice_due_today';

    public const INVOICE_OVERDUE_3 = 'invoice_overdue_3';

    public const INVOICE_OVERDUE_7 = 'invoice_overdue_7';

    public const INVOICE_OVERDUE_14 = 'invoice_overdue_14';

    public const FUP_WARNING = 'fup_warning';

    public const FUP_CRITICAL = 'fup_critical';

    public const OUTAGE = 'outage';

    public const PENDING_GATEWAY_PAYMENT = 'pending_gateway_payment';

    public const SESSION_INTEGRITY = 'session_integrity';

    public const PORTAL_OTP = 'portal_otp';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PAYMENT_SUCCESS => 'Payment success',
            self::INVOICE_DUE => 'Invoice due reminder',
            self::INVOICE_DUE_SOON => 'Invoice due soon (3 days)',
            self::INVOICE_DUE_TODAY => 'Invoice due today',
            self::INVOICE_OVERDUE_3 => 'Invoice 3 days overdue',
            self::INVOICE_OVERDUE_7 => 'Invoice 7 days overdue',
            self::INVOICE_OVERDUE_14 => 'Invoice final notice (14 days)',
            self::FUP_WARNING => 'FUP usage warning (80%+)',
            self::FUP_CRITICAL => 'FUP limit reached',
            self::OUTAGE => 'Outage / maintenance',
            self::PENDING_GATEWAY_PAYMENT => 'Pending gateway payment (ops)',
            self::SESSION_INTEGRITY => 'MikroTik session integrity (ops)',
            self::PORTAL_OTP => 'Portal login OTP',
        ];
    }
}
