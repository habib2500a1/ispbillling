<?php

namespace App\Support;

/**
 * Subscriber-facing traffic direction (what the customer downloads/uploads).
 *
 * MikroTik /ppp/active: bytes-in = upload to ISP, bytes-out = download to customer.
 * FreeRADIUS radacct: acctinputoctets = upload, acctoutputoctets = download.
 */
final class BandwidthDirection
{
    /**
     * @return array{download_bytes: int, upload_bytes: int}
     */
    public static function fromMikrotikCounters(int $routerBytesIn, int $routerBytesOut): array
    {
        return [
            'download_bytes' => $routerBytesOut,
            'upload_bytes' => $routerBytesIn,
        ];
    }

    /**
     * @return array{download_bytes: int, upload_bytes: int}
     */
    public static function fromRadiusCounters(int $acctInputOctets, int $acctOutputOctets): array
    {
        return [
            'download_bytes' => $acctOutputOctets,
            'upload_bytes' => $acctInputOctets,
        ];
    }

    public static function formatBps(?int $bps): string
    {
        if ($bps === null) {
            return '—';
        }

        if ($bps <= 0) {
            return '0 bps';
        }

        if ($bps >= 1_000_000) {
            return round($bps / 1_000_000, 2).' Mbps';
        }

        if ($bps >= 1_000) {
            return round($bps / 1_000, 1).' Kbps';
        }

        return $bps.' bps';
    }
}
