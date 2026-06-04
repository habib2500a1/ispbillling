<?php

namespace App\Support;

final class BillingPortalLabel
{
    public static function name(): string
    {
        $label = trim((string) config('legacy_portal.portal_label', 'Online portal'));

        return $label !== '' ? $label : 'Online portal';
    }

    public static function collectionFilter(): string
    {
        return self::name();
    }

    public static function paymentSource(): string
    {
        return self::name();
    }
}
