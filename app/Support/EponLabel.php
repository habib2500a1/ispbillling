<?php

namespace App\Support;

final class EponLabel
{
    /**
     * @return list<string>
     */
    public static function extractFromText(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $found = [];
        if (preg_match_all('/EPON\s*\d+\s*\/\s*\d+\s*:\s*\d+/i', $text, $matches)) {
            foreach ($matches[0] as $match) {
                $normalized = self::normalize($match);
                if ($normalized !== null) {
                    $found[$normalized] = $normalized;
                }
            }
        }

        return array_values($found);
    }

    public static function normalize(?string $label): ?string
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        if (! preg_match('/EPON\s*(\d+)\s*\/\s*(\d+)\s*:\s*(\d+)/i', trim($label), $m)) {
            return null;
        }

        return sprintf('EPON%d/%d:%d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
}
