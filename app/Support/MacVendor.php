<?php

namespace App\Support;

/**
 * Best-effort ONU/CPE vendor from a MAC OUI (first 3 octets). Returns a known vendor name where the
 * OUI is recognised, otherwise the formatted OUI itself (e.g. "80:D4:A5") so the field is always
 * informative and never shows a wrong guess.
 */
final class MacVendor
{
    /** OUI (6 hex, upper) → vendor label. Curated for common FTTH ONU / router vendors. */
    private const OUI = [
        '80D4A5' => 'HWTC',
        '00D39E' => 'HWTC',
        '8066F6' => 'BDCOM',
        '80662C' => 'BDCOM',
        'CC34CB' => 'BDCOM',
        '38D4A5' => 'HWTC',
        '001141' => 'HWTC',
        'E42D7B' => 'Realtek',
        '48AD08' => 'Huawei',
        '00E0FC' => 'Huawei',
        '281878' => 'Huawei',
        '345B11' => 'ZTE',
        '4C16F1' => 'ZTE',
        'D87495' => 'ZTE',
        'F8E71E' => 'Ruijie',
        '70A8E3' => 'VSOL',
        '00255E' => 'VSOL',
        'CC81DA' => 'CDATA',
        '0C8112' => 'CDATA',
    ];

    public static function lookup(?string $mac): ?string
    {
        $compact = MacAddress::normalizeCompact($mac);
        if ($compact === null) {
            return null;
        }

        $oui = substr($compact, 0, 6);

        return self::OUI[$oui]
            ?? implode(':', str_split($oui, 2)); // fall back to the raw OUI
    }
}
