<?php

namespace App\Support;

use App\Models\Device;

/**
 * BDCOM SNMP onu description is often a port tag (010H, ONU) — not a PPP username.
 */
final class BdcomOnuDescriptionHeuristic
{
    public static function isOltPlaceholderLabel(?string $text): bool
    {
        $text = trim((string) $text);
        if ($text === '' || $text === '—') {
            return true;
        }

        $upper = strtoupper($text);

        if (in_array($upper, ['ONU', 'EPON', 'XPON', 'GPON', 'OLT', 'N/A', 'NA', 'NULL'], true)) {
            return true;
        }

        if (preg_match('/^\-+$/', $text)) {
            return true;
        }

        // Short BDCOM tags: 010H, 010T, 310M, 1601
        if (preg_match('/^\d{2,4}[A-Z]{0,2}$/i', $text) && strlen($text) <= 6) {
            return true;
        }

        if (preg_match('/^[A-Z]{2,5}$/i', $text) && strlen($text) <= 5) {
            return true;
        }

        // Interface / Port labels: EPON0/1, GPON0/2:3, ONU4/1
        if (preg_match('/^(EPON|GPON|ONU|XPON)\d+\/\d+/i', $text)) {
            return true;
        }

        if (preg_match('/^PORT\d+/i', $text)) {
            return true;
        }

        return false;
    }

    /**
     * PPP / billing username for optical grid (not raw OLT description).
     */
    public static function resolveDisplayUsername(Device $onu, array $meta): string
    {
        $tenantId = (int) $onu->tenant_id;

        $hints = array_filter([
            $meta['ppp_login'] ?? null,
            $meta['subscriber_login'] ?? null,
            $meta['username'] ?? null,
            $meta['bdcom_description'] ?? null,
            $meta['aveis_label'] ?? null,
            $meta['aveis_description'] ?? null,
            $meta['huawei_description'] ?? null,
            $meta['zte_description'] ?? null,
            $onu->display_name,
            $onu->onu_external_id,
        ], fn ($v) => filled($v));

        foreach ($hints as $hint) {
            $hint = trim((string) $hint);
            if (self::isOltPlaceholderLabel($hint)) {
                continue;
            }

            $customer = CustomerPppLoginResolver::resolve($tenantId, $hint);
            if ($customer !== null) {
                return $customer->pppLoginName();
            }

            if (self::looksLikePppUsername($hint)) {
                return $hint;
            }
        }

        return '—';
    }

    public static function looksLikePppUsername(string $login): bool
    {
        if (self::isOltPlaceholderLabel($login)) {
            return false;
        }

        if (str_contains($login, '.') || str_contains($login, '@') || str_contains($login, '_')) {
            return true;
        }

        return strlen($login) >= 5 && preg_match('/[a-z]/i', $login) === 1;
    }

    public static function sanitizePppLoginHint(?string $description, int $tenantId): ?string
    {
        $description = trim((string) $description);
        if ($description === '' || self::isOltPlaceholderLabel($description)) {
            return null;
        }

        $customer = CustomerPppLoginResolver::resolve($tenantId, $description);
        if ($customer !== null) {
            return $customer->pppLoginName();
        }

        return self::looksLikePppUsername($description) ? $description : null;
    }
}
