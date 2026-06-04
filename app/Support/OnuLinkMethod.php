<?php

namespace App\Support;

/**
 * Human labels for how an ONU was matched to its subscriber (Device.meta['linked_by']).
 * Shared by the admin optical presenter and the customer portal.
 */
final class OnuLinkMethod
{
    public static function label(string $linkedBy): string
    {
        return match ($linkedBy) {
            'olt_fdb_mac' => 'Auto-detected from OLT (customer MAC behind ONU)',
            'onu_mac_exact' => 'Matched by ONU/router MAC',
            'login_exact' => 'Matched by PPP login',
            'desc_exact' => 'Matched by ONU description',
            'epon_exact' => 'Matched by EPON port',
            'manual' => 'Linked manually',
            default => $linkedBy !== '' ? 'Linked ('.$linkedBy.')' : '',
        };
    }

    /** True when the ONU was auto-detected from the OLT forwarding table. */
    public static function isAuto(string $linkedBy): bool
    {
        return $linkedBy === 'olt_fdb_mac';
    }
}
