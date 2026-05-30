<?php

namespace App\Support;

final class ResellerPortalActivityLabels
{
    /** @return array<string, string> */
    public static function actions(): array
    {
        return [
            'customer.create' => 'Created subscriber',
            'customer.update' => 'Updated subscriber',
            'customer.renew' => 'Renewed service',
            'customer.suspend' => 'Suspended subscriber',
            'customer.reconnect' => 'Reconnected subscriber',
            'customer.password_change' => 'Changed PPPoE password',
            'payment.collect' => 'Collected payment',
            'invoice.generate' => 'Generated invoice',
            'invoice.send' => 'Sent invoice (SMS/email)',
            'ticket.create' => 'Created support ticket',
            'ticket.reply' => 'Replied to ticket',
            'network.disconnect' => 'Disconnected PPPoE session',
            'portal.login' => 'Portal login',
            'portal.login.staff' => 'Staff portal login',
            'api.login' => 'API login',
            'api.login.staff' => 'Staff API login',
        ];
    }

    public static function label(string $action): string
    {
        return self::actions()[$action] ?? str_replace(['.', '_'], ' ', $action);
    }
}
