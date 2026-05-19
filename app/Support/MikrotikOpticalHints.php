<?php

namespace App\Support;

/**
 * Parse MikroTik PPP secret comment / caller-id fields for OLT auto-link hints.
 */
final class MikrotikOpticalHints
{
    /**
     * @param  array<string, mixed>  $secretRow  fetchPppSecrets row (incl. raw)
     * @return array{
     *   epon_ports: list<string>,
     *   mac_compacts: list<string>,
     *   comment: ?string,
     *   caller_id: ?string,
     *   last_caller_id: ?string
     * }
     */
    public static function fromPppSecret(array $secretRow): array
    {
        $raw = is_array($secretRow['raw'] ?? null) ? $secretRow['raw'] : [];
        $comment = filled($secretRow['comment'] ?? null)
            ? trim((string) $secretRow['comment'])
            : (filled($raw['comment'] ?? null) ? trim((string) $raw['comment']) : null);

        $callerId = self::pickMac($raw['caller-id'] ?? $raw['caller_id'] ?? null);
        $lastCallerId = self::pickMac($raw['last-caller-id'] ?? $raw['last_caller_id'] ?? null);

        $texts = array_filter([$comment, $secretRow['name'] ?? null]);
        $eponPorts = [];
        $macCompacts = [];

        foreach ($texts as $text) {
            $eponPorts = array_merge($eponPorts, EponLabel::extractFromText((string) $text));
            $macCompacts = array_merge($macCompacts, self::extractMacsFromText((string) $text));
        }

        foreach ([$callerId, $lastCallerId] as $mac) {
            $compact = MacAddress::normalizeCompact($mac);
            if ($compact !== null) {
                $macCompacts[] = $compact;
            }
        }

        return [
            'epon_ports' => array_values(array_unique($eponPorts)),
            'mac_compacts' => array_values(array_unique($macCompacts)),
            'comment' => $comment,
            'caller_id' => $callerId,
            'last_caller_id' => $lastCallerId,
        ];
    }

    /**
     * @param  array<string, mixed>  $activeSession  normalized active session row
     */
    public static function fromActiveSession(array $activeSession): array
    {
        $callerId = self::pickMac($activeSession['caller_id'] ?? $activeSession['caller-id'] ?? null);
        $compact = MacAddress::normalizeCompact($callerId);

        return [
            'epon_ports' => [],
            'mac_compacts' => $compact !== null ? [$compact] : [],
            'comment' => null,
            'caller_id' => $callerId,
            'last_caller_id' => null,
        ];
    }

    /**
     * Merge hint sets (epon + mac lists).
     *
     * @param  array{epon_ports: list<string>, mac_compacts: list<string>}  ...$sets
     * @return array{epon_ports: list<string>, mac_compacts: list<string>}
     */
    public static function merge(array ...$sets): array
    {
        $epon = [];
        $macs = [];
        foreach ($sets as $set) {
            foreach ($set['epon_ports'] ?? [] as $p) {
                $epon[$p] = $p;
            }
            foreach ($set['mac_compacts'] ?? [] as $m) {
                $macs[$m] = $m;
            }
        }

        return [
            'epon_ports' => array_values($epon),
            'mac_compacts' => array_values($macs),
        ];
    }

    /**
     * @return list<string> 12-hex compact
     */
    public static function extractMacsFromText(string $text): array
    {
        $out = [];
        if (preg_match_all('/\b([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}\b/', $text, $m)) {
            foreach ($m[0] as $match) {
                $compact = MacAddress::normalizeCompact($match);
                if ($compact !== null) {
                    $out[] = $compact;
                }
            }
        }
        if (preg_match('/\b([0-9A-Fa-f]{12})\b/', $text, $m)) {
            $compact = MacAddress::normalizeCompact($m[1]);
            if ($compact !== null) {
                $out[] = $compact;
            }
        }

        return $out;
    }

    private static function pickMac(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return MacAddress::normalizeColon(trim($value));
    }
}
