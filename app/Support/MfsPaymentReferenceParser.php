<?php

namespace App\Support;

/**
 * Extract subscriber ID / PPPoE that customers type in bKash·Nagad reference (Counter) fields.
 */
final class MfsPaymentReferenceParser
{
    /**
     * Tokens from Ref/Counter/Reference labels and PPPoE logins only (no broad body scan).
     *
     * @return list<string>
     */
    public static function extractLabeledReferences(string $message, ?string $knownTrxId = null): array
    {
        $body = trim($message);
        if ($body === '') {
            return [];
        }

        $candidates = [];

        foreach ([
            '/\b(?:reference|counter|note|remarks?|memo|রেফারেন্স|কাউন্টার)\s*[:\s#-]+([A-Za-z0-9@._-]{2,64})/iu',
            '/\bRef\s*[:\s#-]+([A-Za-z0-9@._-]{2,64})/iu',
        ] as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $raw) {
                    $candidates[] = self::cleanToken((string) $raw, $knownTrxId);
                }
            }
        }

        if (preg_match_all('/[\w][\w.-]{1,62}@[\w][\w.-]{1,62}/u', $body, $pppoe)) {
            foreach ($pppoe[0] as $login) {
                $candidates[] = self::cleanToken($login, $knownTrxId);
            }
        }

        return self::uniqueTokens($candidates);
    }

    /**
     * @return list<string> Unique candidate tokens, longest first.
     */
    public static function extractFromMessage(string $message, ?string $knownTrxId = null): array
    {
        $body = trim($message);
        if ($body === '') {
            return [];
        }

        $candidates = self::extractLabeledReferences($message, $knownTrxId);

        $scrubbed = $body;
        if ($knownTrxId !== null && $knownTrxId !== '') {
            $scrubbed = str_ireplace($knownTrxId, ' ', $scrubbed);
        }
        $scrubbed = preg_replace('/\b(?:TRX(?:ID)?|TXN(?:ID)?|TRANSACTION)\s*(?:ID|NO)?[\s:#-]*[A-Z0-9]{6,20}/i', ' ', $scrubbed) ?? $scrubbed;
        $scrubbed = preg_replace('/01[3-9]\d{8}/', ' ', $scrubbed) ?? $scrubbed;
        $scrubbed = preg_replace('/(?:TK|TAKA|BDT)\s*[:\s]*[0-9]+(?:\.[0-9]{1,2})?/i', ' ', $scrubbed) ?? $scrubbed;
        $scrubbed = preg_replace('/[0-9]+(?:\.[0-9]{1,2})?\s*(?:TK|TAKA|BDT)/i', ' ', $scrubbed) ?? $scrubbed;

        if (preg_match_all('/\b([A-Za-z][A-Za-z0-9._-]{2,48})\b/u', $scrubbed, $alpha)) {
            foreach ($alpha[1] as $token) {
                $candidates[] = self::cleanToken($token, $knownTrxId);
            }
        }

        if (preg_match_all('/\b(\d{2,10})\b/', $scrubbed, $digits)) {
            foreach ($digits[1] as $token) {
                $candidates[] = self::cleanToken($token, $knownTrxId);
            }
        }

        return self::uniqueTokens($candidates);
    }

    /**
     * @param  list<?string>  $candidates
     * @return list<string>
     */
    private static function uniqueTokens(array $candidates): array
    {
        $unique = [];
        foreach ($candidates as $token) {
            if ($token === null || $token === '') {
                continue;
            }
            $key = strtolower($token);
            if (! isset($unique[$key])) {
                $unique[$key] = $token;
            }
        }

        $list = array_values($unique);
        usort($list, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $list;
    }

    /**
     * Numeric subscriber IDs: 0790 and 790 are equivalent (leading zeros ignored).
     */
    public static function numericVariants(string $code): array
    {
        $code = trim($code);
        if ($code === '' || ! preg_match('/^\d+$/', $code)) {
            return [];
        }

        $stripped = ltrim($code, '0');
        if ($stripped === '') {
            $stripped = '0';
        }

        $variants = [$code, $stripped];
        if (strlen($code) > strlen($stripped)) {
            $variants[] = str_pad($stripped, strlen($code), '0', STR_PAD_LEFT);
        }

        // Ref 782 must match panel ID 0782 (pad common ISP code widths).
        foreach ([3, 4, 5, 6] as $padLen) {
            if (strlen($stripped) <= $padLen) {
                $variants[] = str_pad($stripped, $padLen, '0', STR_PAD_LEFT);
            }
        }

        return array_values(array_unique($variants));
    }

    public static function normalizeReferenceToken(?string $raw, ?string $knownTrxId = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return self::cleanToken(trim($raw), $knownTrxId);
    }

    public static function messageHasReferenceIntent(string $message, ?string $explicitReference = null): bool
    {
        if (self::normalizeReferenceToken($explicitReference) !== null) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:reference|counter|note|remarks?|memo|রেফারেন্স|কাউন্টার)\s*[:\s#-]+\S/iu',
            $message,
        ) || (bool) preg_match('/\bRef\s*[:\s#-]+\S/i', $message);
    }

    private static function cleanToken(string $raw, ?string $knownTrxId): ?string
    {
        $token = trim($raw, " \t\n\r\0\x0B.,;:#-");
        if ($token === '') {
            return null;
        }

        if ($knownTrxId !== null && strcasecmp($token, $knownTrxId) === 0) {
            return null;
        }

        if (preg_match('/^\d+$/', $token) && strlen($token) >= 6 && str_starts_with($token, '01')) {
            return null;
        }

        if (preg_match('/^(?:trx|txn|fee|balance|received|credited|cash|send|money|bkash|nagad|rocket)$/i', $token)) {
            return null;
        }

        if (preg_match('/^\d+$/', $token) && strlen($token) >= 8) {
            return null;
        }

        return $token;
    }
}
